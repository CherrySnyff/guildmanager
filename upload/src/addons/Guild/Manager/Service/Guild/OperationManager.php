<?php

namespace Guild\Manager\Service\Guild;

use Guild\Manager\Entity\Guild;
use XF\Entity\User;
use XF\PrintableException;
use XF\Service\AbstractService;

/**
 * Операции над журналами и связанными сущностями гильдии (POST-формы, CLI).
 *
 * Здесь: валидация сумм и URL, добавление строк в xf_guild_*_log, синхронизация казны/последователей/уровня
 * через Aggregator (см. persistGuild*, persistGuildAfterReputationMutation и др.).
 */
class OperationManager extends AbstractService
{
    protected function getAmountColumnType(string $tableName): string
    {
        return (string)$this->db()->fetchOne(
            '
                SELECT COLUMN_TYPE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND COLUMN_NAME = ?
                LIMIT 1
            ',
            [$tableName, 'amount']
        );
    }

    protected function assertAmountColumnSupportsNegative(string $tableName): void
    {
        $columnType = $this->getAmountColumnType($tableName);

        if ($columnType !== '' && stripos($columnType, 'unsigned') !== false) {
            // Self-heal schema if ACP upgrade step was skipped or failed to apply.
            try {
                $this->db()->query(
                    'ALTER TABLE `' . $tableName . '` MODIFY `amount` INT NOT NULL DEFAULT 0'
                );
            } catch (\Throwable $e) {
                // Ignore and validate below; user will get actionable message.
            }

            $columnType = $this->getAmountColumnType($tableName);
            if ($columnType !== '' && stripos($columnType, 'unsigned') !== false) {
                throw new PrintableException(
                    'Колонка amount в таблице ' . $tableName
                    . ' имеет тип UNSIGNED. Выполните SQL: ALTER TABLE `' . $tableName
                    . '` MODIFY `amount` INT NOT NULL DEFAULT 0;'
                );
            }
        }
    }

    /* ===================== Казна (xf_guild_treasury_log) ===================== */

    public function addTreasuryOperation(
        Guild $guild,
        User $actor,
        string $characterName,
        string $sourceUrl,
        int $amount,
        string $operationType,
        string $reason,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildTreasuryLog {
        $db = $this->db();
        /** @var Aggregator $aggregator */
        $aggregator = $this->service('Guild\Manager:Guild\Aggregator');
        /** @var ActionLogger $actionLogger */
        $actionLogger = $this->service('Guild\Manager:Guild\ActionLogger');
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanAddTreasuryOperation($guild, $actor, $guildRole);
        $this->assertAmountColumnSupportsNegative('xf_guild_treasury_log');

        if ($operationType === 'deposit') {
            $amount = abs($amount);
        } elseif ($operationType === 'withdraw') {
            $amount = -abs($amount);
        } else {
            throw new PrintableException('Выберите тип операции.');
        }

        if ($amount === 0) {
            throw new PrintableException('Сумма должна быть больше 0.');
        }

        $currentBalance = (int)$db->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) FROM xf_guild_treasury_log WHERE guild_id = ?',
            $guild->guild_id
        );
        if ($currentBalance + $amount < 0) {
            throw new PrintableException('Недостаточно средств в казне.');
        }

        $db->beginTransaction();
        try {
            /** @var \Guild\Manager\Entity\GuildTreasuryLog $log */
            $log = $this->em()->create('Guild\Manager:GuildTreasuryLog');
            // Важен порядок: сначала тип, затем amount (валидация amount зависит от operation_type).
            $log->guild_id = $guild->guild_id;
            $log->character_name = $characterName;
            $log->source_url = $sourceUrl;
            $log->operation_type = $operationType;
            $log->amount = $amount;
            $log->reason = $reason;
            $log->created_by_user_id = $actor->user_id;
            $log->created_date = \XF::$time;
            $log->save();

            $treasuryBalance = $aggregator->recalculateTreasury($guild);
            $db->query(
                'UPDATE xf_guild SET treasury_balance = ?, last_update = ? WHERE guild_id = ?',
                [$treasuryBalance, \XF::$time, $guild->guild_id]
            );
            $actionLogger->log(
                $guild,
                $actor,
                'treasury',
                ActionLogger::ACTION_ADD,
                'Казна: ' . $characterName . ', сумма ' . $amount . ', причина: ' . $reason
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        return $log;
    }

    public function updateTreasuryLogEntry(
        Guild $guild,
        User $actor,
        int $treasuryLogId,
        string $characterName,
        string $sourceUrl,
        int $amount,
        string $operationType,
        string $reason,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildTreasuryLog {
        $db = $this->db();
        /** @var Aggregator $aggregator */
        $aggregator = $this->service('Guild\Manager:Guild\Aggregator');
        /** @var ActionLogger $actionLogger */
        $actionLogger = $this->service('Guild\Manager:Guild\ActionLogger');
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanEditTreasuryLogEntry($guild, $actor, $guildRole);
        $this->assertAmountColumnSupportsNegative('xf_guild_treasury_log');

        /** @var \Guild\Manager\Entity\GuildTreasuryLog|null $log */
        $log = $this->em()->find('Guild\Manager:GuildTreasuryLog', $treasuryLogId);
        if (!$log || (int)$log->guild_id !== (int)$guild->guild_id) {
            throw new PrintableException('Запись не найдена.');
        }

        if ($operationType === 'deposit') {
            $amount = abs($amount);
        } elseif ($operationType === 'withdraw') {
            $amount = -abs($amount);
        } else {
            throw new PrintableException('Выберите тип операции.');
        }

        if ($amount === 0) {
            throw new PrintableException('Сумма должна быть больше 0.');
        }

        $currentBalance = (int)$db->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) FROM xf_guild_treasury_log WHERE guild_id = ?',
            $guild->guild_id
        );
        $newBalance = $currentBalance - (int)$log->amount + $amount;
        if ($newBalance < 0) {
            throw new PrintableException('Недостаточно средств в казне.');
        }

        $db->beginTransaction();
        try {
            $log->character_name = $characterName;
            $log->source_url = $sourceUrl;
            $log->operation_type = $operationType;
            $log->amount = $amount;
            $log->reason = $reason;
            $log->save();

            $treasuryBalance = $aggregator->recalculateTreasury($guild);
            $db->query(
                'UPDATE xf_guild SET treasury_balance = ?, last_update = ? WHERE guild_id = ?',
                [$treasuryBalance, \XF::$time, $guild->guild_id]
            );
            $actionLogger->log(
                $guild,
                $actor,
                'treasury',
                ActionLogger::ACTION_UPDATE,
                'Казна изменена: ' . $characterName . ', сумма ' . $amount
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        return $log;
    }

    public function deleteTreasuryLogEntry(
        Guild $guild,
        User $actor,
        int $treasuryLogId,
        ?string $guildRole = null
    ): void {
        $db = $this->db();
        /** @var Aggregator $aggregator */
        $aggregator = $this->service('Guild\Manager:Guild\Aggregator');
        /** @var ActionLogger $actionLogger */
        $actionLogger = $this->service('Guild\Manager:Guild\ActionLogger');
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanDeleteTreasuryLogEntry($guild, $actor, $guildRole);

        /** @var \Guild\Manager\Entity\GuildTreasuryLog|null $log */
        $log = $this->em()->find('Guild\Manager:GuildTreasuryLog', $treasuryLogId);
        if (!$log || (int)$log->guild_id !== (int)$guild->guild_id) {
            throw new PrintableException('Запись не найдена.');
        }

        $db->beginTransaction();
        try {
            $summary = 'Казна удалена: ' . (string)$log->character_name . ', сумма ' . (int)$log->amount;
            $log->delete();

            $treasuryBalance = $aggregator->recalculateTreasury($guild);
            $db->query(
                'UPDATE xf_guild SET treasury_balance = ?, last_update = ? WHERE guild_id = ?',
                [$treasuryBalance, \XF::$time, $guild->guild_id]
            );
            $actionLogger->log($guild, $actor, 'treasury', ActionLogger::ACTION_DELETE, $summary);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /* ===================== Последователи (xf_guild_follower_log) ===================== */

    public function addFollowerOperation(
        Guild $guild,
        User $actor,
        string $characterName,
        string $sourceUrl,
        int $amount,
        string $operationType,
        string $eventDateText,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildFollowerLog {
        $db = $this->db();
        /** @var Aggregator $aggregator */
        $aggregator = $this->service('Guild\Manager:Guild\Aggregator');
        /** @var ActionLogger $actionLogger */
        $actionLogger = $this->service('Guild\Manager:Guild\ActionLogger');
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanAddFollowerOperation($guild, $actor, $guildRole);
        $this->assertAmountColumnSupportsNegative('xf_guild_follower_log');

        $currentTotal = (int)$db->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) FROM xf_guild_follower_log WHERE guild_id = ?',
            $guild->guild_id
        );
        if ($currentTotal + $amount < 0) {
            throw new PrintableException('Количество последователей не может быть меньше 0.');
        }

        $db->beginTransaction();
        try {
            /** @var \Guild\Manager\Entity\GuildFollowerLog $log */
            $log = $this->em()->create('Guild\Manager:GuildFollowerLog');
            $log->bulkSet([
                'guild_id' => $guild->guild_id,
                'character_name' => $characterName,
                'source_url' => $sourceUrl,
                'operation_type' => $operationType,
                'amount' => $amount,
                'event_date_text' => $eventDateText,
                'created_by_user_id' => $actor->user_id,
                'created_date' => \XF::$time
            ]);
            $log->save();

            $followersTotal = $aggregator->recalculateFollowers($guild);
            $aggregator->recalculateOrganizationLevel($guild);

            $db->query(
                'UPDATE xf_guild SET followers_total = ?, organization_level = ?, organization_size_label = ?, last_update = ? WHERE guild_id = ?',
                [$followersTotal, $guild->organization_level, $guild->organization_size_label, \XF::$time, $guild->guild_id]
            );
            $actionLogger->log(
                $guild,
                $actor,
                'followers',
                ActionLogger::ACTION_ADD,
                'Последователи: ' . $characterName . ', количество ' . $amount . ', дата ' . $eventDateText
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        return $log;
    }

    public function updateFollowerLogEntry(
        Guild $guild,
        User $actor,
        int $followerLogId,
        string $characterName,
        string $sourceUrl,
        int $amount,
        string $operationType,
        string $eventDateText,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildFollowerLog {
        $db = $this->db();
        /** @var Aggregator $aggregator */
        $aggregator = $this->service('Guild\Manager:Guild\Aggregator');
        /** @var ActionLogger $actionLogger */
        $actionLogger = $this->service('Guild\Manager:Guild\ActionLogger');
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanEditFollowerLogEntry($guild, $actor, $guildRole);
        $this->assertAmountColumnSupportsNegative('xf_guild_follower_log');

        /** @var \Guild\Manager\Entity\GuildFollowerLog|null $log */
        $log = $this->em()->find('Guild\Manager:GuildFollowerLog', $followerLogId);
        if (!$log || (int)$log->guild_id !== (int)$guild->guild_id) {
            throw new PrintableException('Запись не найдена.');
        }

        $oldAmount = (int)$log->amount;
        $currentTotal = (int)$db->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) FROM xf_guild_follower_log WHERE guild_id = ?',
            $guild->guild_id
        );
        $newTotal = $currentTotal - $oldAmount + $amount;
        if ($newTotal < 0) {
            throw new PrintableException('Количество последователей не может быть меньше 0.');
        }

        $db->beginTransaction();
        try {
            $log->bulkSet([
                'character_name' => $characterName,
                'source_url' => $sourceUrl,
                'operation_type' => $operationType,
                'amount' => $amount,
                'event_date_text' => $eventDateText,
            ]);
            $log->save();

            $followersTotal = $aggregator->recalculateFollowers($guild);
            $aggregator->recalculateOrganizationLevel($guild);

            $db->query(
                'UPDATE xf_guild SET followers_total = ?, organization_level = ?, organization_size_label = ?, last_update = ? WHERE guild_id = ?',
                [$followersTotal, $guild->organization_level, $guild->organization_size_label, \XF::$time, $guild->guild_id]
            );
            $actionLogger->log(
                $guild,
                $actor,
                'followers',
                ActionLogger::ACTION_UPDATE,
                'Последователи изменены: ' . $characterName . ', количество ' . $amount
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        return $log;
    }

    public function deleteFollowerLogEntry(
        Guild $guild,
        User $actor,
        int $followerLogId,
        ?string $guildRole = null
    ): void {
        $db = $this->db();
        /** @var Aggregator $aggregator */
        $aggregator = $this->service('Guild\Manager:Guild\Aggregator');
        /** @var ActionLogger $actionLogger */
        $actionLogger = $this->service('Guild\Manager:Guild\ActionLogger');
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanDeleteFollowerLogEntry($guild, $actor, $guildRole);

        /** @var \Guild\Manager\Entity\GuildFollowerLog|null $log */
        $log = $this->em()->find('Guild\Manager:GuildFollowerLog', $followerLogId);
        if (!$log || (int)$log->guild_id !== (int)$guild->guild_id) {
            throw new PrintableException('Запись не найдена.');
        }

        $oldAmount = (int)$log->amount;
        $currentTotal = (int)$db->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) FROM xf_guild_follower_log WHERE guild_id = ?',
            $guild->guild_id
        );
        if ($currentTotal - $oldAmount < 0) {
            throw new PrintableException('Количество последователей не может быть меньше 0.');
        }

        $db->beginTransaction();
        try {
            $summary = 'Последователи удалены: ' . (string)$log->character_name . ', количество ' . (int)$log->amount;
            $log->delete();

            $followersTotal = $aggregator->recalculateFollowers($guild);
            $aggregator->recalculateOrganizationLevel($guild);

            $db->query(
                'UPDATE xf_guild SET followers_total = ?, organization_level = ?, organization_size_label = ?, last_update = ? WHERE guild_id = ?',
                [$followersTotal, $guild->organization_level, $guild->organization_size_label, \XF::$time, $guild->guild_id]
            );
            $actionLogger->log($guild, $actor, 'followers', ActionLogger::ACTION_DELETE, $summary);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /* ===================== Репутация (xf_guild_reputation_log → уровень, influence_cache) ===================== */

    public function addReputationOperation(
        Guild $guild,
        User $actor,
        string $regionKey,
        string $characterName,
        string $sourceUrl,
        int $amount,
        string $operationType,
        string $factionName,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildReputationLog {
        $db = $this->db();
        /** @var ActionLogger $actionLogger */
        $actionLogger = $this->service('Guild\Manager:Guild\ActionLogger');
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanAddReputationOperation($guild, $actor, $guildRole);
        $this->assertAmountColumnSupportsNegative('xf_guild_reputation_log');

        $db->beginTransaction();
        try {
            /** @var \Guild\Manager\Entity\GuildReputationLog $log */
            $log = $this->em()->create('Guild\Manager:GuildReputationLog');
            $log->bulkSet([
                'guild_id' => $guild->guild_id,
                'region_key' => $regionKey,
                'character_name' => $characterName,
                'source_url' => $sourceUrl,
                'operation_type' => $operationType,
                'amount' => $amount,
                'faction_name' => $factionName,
                'created_by_user_id' => $actor->user_id,
                'created_date' => \XF::$time
            ]);
            $log->save();

            $this->persistGuildAfterReputationMutation($guild);
            $actionLogger->log(
                $guild,
                $actor,
                'reputation',
                ActionLogger::ACTION_ADD,
                'Репутация: ' . $characterName . ', сумма ' . $amount . ', фракция ' . $factionName
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        return $log;
    }

    public function updateReputationLogEntry(
        Guild $guild,
        User $actor,
        int $reputationLogId,
        string $regionKey,
        string $characterName,
        string $sourceUrl,
        int $amount,
        string $operationType,
        string $factionName,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildReputationLog {
        $db = $this->db();
        /** @var ActionLogger $actionLogger */
        $actionLogger = $this->service('Guild\Manager:Guild\ActionLogger');
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanEditReputationLogEntry($guild, $actor, $guildRole);
        $this->assertAmountColumnSupportsNegative('xf_guild_reputation_log');

        /** @var \Guild\Manager\Entity\GuildReputationLog|null $log */
        $log = $this->em()->find('Guild\Manager:GuildReputationLog', $reputationLogId);
        if (!$log || (int)$log->guild_id !== (int)$guild->guild_id) {
            throw new PrintableException('Запись не найдена.');
        }

        $db->beginTransaction();
        try {
            $log->bulkSet([
                'region_key' => $regionKey,
                'character_name' => $characterName,
                'source_url' => $sourceUrl,
                'operation_type' => $operationType,
                'amount' => $amount,
                'faction_name' => $factionName,
            ]);
            $log->save();

            $this->persistGuildAfterReputationMutation($guild);
            $actionLogger->log(
                $guild,
                $actor,
                'reputation',
                ActionLogger::ACTION_UPDATE,
                'Репутация изменена: ' . $characterName . ', сумма ' . $amount . ', фракция ' . $factionName
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        return $log;
    }

    public function deleteReputationLogEntry(
        Guild $guild,
        User $actor,
        int $reputationLogId,
        ?string $guildRole = null
    ): void {
        $db = $this->db();
        /** @var ActionLogger $actionLogger */
        $actionLogger = $this->service('Guild\Manager:Guild\ActionLogger');
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanDeleteReputationLogEntry($guild, $actor, $guildRole);

        /** @var \Guild\Manager\Entity\GuildReputationLog|null $log */
        $log = $this->em()->find('Guild\Manager:GuildReputationLog', $reputationLogId);
        if (!$log || (int)$log->guild_id !== (int)$guild->guild_id) {
            throw new PrintableException('Запись не найдена.');
        }

        $db->beginTransaction();
        try {
            $summary = 'Репутация удалена: ' . (string)$log->character_name . ', сумма ' . (int)$log->amount . ', фракция ' . (string)$log->faction_name;
            $log->delete();

            $this->persistGuildAfterReputationMutation($guild);
            $actionLogger->log($guild, $actor, 'reputation', ActionLogger::ACTION_DELETE, $summary);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * После мутаций репутации синхронизируем организационный уровень (учёт порога «мировой известности»), кеш биомов и followers_total в xf_guild.
     */
    protected function persistGuildAfterReputationMutation(Guild $guild): void
    {
        $db = $this->db();
        /** @var Aggregator $aggregator */
        $aggregator = $this->service('Guild\Manager:Guild\Aggregator');
        $aggregator->recalculateFollowers($guild);
        $aggregator->recalculateOrganizationLevel($guild);
        $influenceCache = $aggregator->recalculateInfluenceCache($guild);
        $db->query(
            'UPDATE xf_guild SET influence_cache = ?, organization_level = ?, organization_size_label = ?, followers_total = ?, last_update = ? WHERE guild_id = ?',
            [
                json_encode($influenceCache),
                $guild->organization_level,
                $guild->organization_size_label,
                $guild->followers_total,
                \XF::$time,
                $guild->guild_id,
            ]
        );
    }

    /* ===================== Транспорт (xf_guild_vehicle) ===================== */

    public function addVehicle(
        Guild $guild,
        User $actor,
        string $vehicleName,
        string $vehicleStatus,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildVehicle {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanAddFollowerOperation($guild, $actor, $guildRole);
        /** @var ActionLogger $actionLogger */
        $actionLogger = $this->service('Guild\Manager:Guild\ActionLogger');

        /** @var \Guild\Manager\Entity\GuildVehicle $vehicle */
        $vehicle = $this->em()->create('Guild\Manager:GuildVehicle');
        $vehicle->bulkSet([
            'guild_id' => $guild->guild_id,
            'vehicle_name' => $vehicleName,
            'vehicle_status' => $vehicleStatus,
            'display_order' => 0,
            'created_date' => \XF::$time,
            'last_update' => \XF::$time,
        ]);
        $vehicle->save();
        $actionLogger->log($guild, $actor, 'transport', ActionLogger::ACTION_ADD, 'Транспорт добавлен: ' . $vehicleName);

        return $vehicle;
    }

    public function updateVehicle(
        Guild $guild,
        User $actor,
        int $vehicleId,
        string $vehicleName,
        string $vehicleStatus,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildVehicle {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanEditVehicle($guild, $actor, $guildRole);
        /** @var ActionLogger $actionLogger */
        $actionLogger = $this->service('Guild\Manager:Guild\ActionLogger');

        /** @var \Guild\Manager\Entity\GuildVehicle|null $vehicle */
        $vehicle = $this->em()->find('Guild\Manager:GuildVehicle', $vehicleId);
        if (!$vehicle || (int)$vehicle->guild_id !== (int)$guild->guild_id) {
            throw new PrintableException('Запись не найдена.');
        }

        $vehicle->bulkSet([
            'vehicle_name' => $vehicleName,
            'vehicle_status' => $vehicleStatus,
            'last_update' => \XF::$time,
        ]);
        $vehicle->save();
        $actionLogger->log($guild, $actor, 'transport', ActionLogger::ACTION_UPDATE, 'Транспорт изменен: ' . $vehicleName);

        return $vehicle;
    }

    public function deleteVehicle(
        Guild $guild,
        User $actor,
        int $vehicleId,
        ?string $guildRole = null
    ): void {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanDeleteVehicle($guild, $actor, $guildRole);
        /** @var ActionLogger $actionLogger */
        $actionLogger = $this->service('Guild\Manager:Guild\ActionLogger');

        /** @var \Guild\Manager\Entity\GuildVehicle|null $vehicle */
        $vehicle = $this->em()->find('Guild\Manager:GuildVehicle', $vehicleId);
        if (!$vehicle || (int)$vehicle->guild_id !== (int)$guild->guild_id) {
            throw new PrintableException('Запись не найдена.');
        }

        $summary = 'Транспорт удален: ' . (string)$vehicle->vehicle_name;
        $vehicle->delete();
        $actionLogger->log($guild, $actor, 'transport', ActionLogger::ACTION_DELETE, $summary);
    }

    /* ===================== Смена лидера (xf_guild + xf_guild_leader_log + роли member) ===================== */

    public function changeLeader(
        Guild $guild,
        User $actor,
        int $newUserId,
        string $newUsername,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildLeaderLog {
        $db = $this->db();
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanChangeLeader($guild, $actor, $guildRole);
        /** @var MembershipManager $membershipManager */
        $membershipManager = $this->service('Guild\Manager:Guild\MembershipManager');

        /** @var User|null $newLeader */
        $newLeader = $this->em()->find('XF:User', $newUserId);
        if (!$newLeader) {
            throw new PrintableException('Requested user for leader change was not found.');
        }

        $db->beginTransaction();
        try {
            /** @var \Guild\Manager\Entity\GuildLeaderLog $log */
            $log = $this->em()->create('Guild\Manager:GuildLeaderLog');
            $log->bulkSet([
                'guild_id' => $guild->guild_id,
                'old_user_id' => $guild->leader_user_id,
                'new_user_id' => $newUserId,
                'changed_by_user_id' => $actor->user_id,
                'change_date' => \XF::$time
            ]);
            $log->save();

            $db->update('xf_guild', [
                'leader_user_id' => $newUserId,
                'leader_username' => $newUsername,
                'last_update' => \XF::$time
            ], 'guild_id = ?', $guild->guild_id);

            // Important: promote the new leader first so we never have zero active leaders.
            $membershipManager->setMemberRole($guild, $newLeader, PermissionPreset::ROLE_LEADER);

            if ($guild->leader_user_id > 0 && $guild->leader_user_id !== $newUserId) {
                /** @var User|null $oldLeader */
                $oldLeader = $this->em()->find('XF:User', $guild->leader_user_id);
                if ($oldLeader) {
                    $membershipManager->setMemberRole($guild, $oldLeader, PermissionPreset::ROLE_MEMBER);
                }
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        return $log;
    }
}
