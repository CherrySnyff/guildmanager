<?php

namespace Guild\Manager\Service\Guild;

use Guild\Manager\Entity\Guild;
use Guild\Manager\Helper\BbCodeContent;
use XF\Entity\User;
use XF\PrintableException;
use XF\Service\AbstractService;

/**
 * Высокоуровневые сценарии вокруг гильдии: создание, смена лидера, приглашения, перенос контента (BBCode),
 * при необходимости вызывает MembershipManager и Aggregator.
 */
class GuildWorkflow extends AbstractService
{
    public function createGuild(
        User $actor,
        string $title,
        string $description = '',
        ?User $leader = null,
        int $threadId = 0
    ): Guild
    {
        if (!$actor->hasPermission('guild_manager', 'createGuild')) {
            throw new PrintableException('You do not have permission to create guilds.');
        }

        $leader = $leader ?: $actor;

        $db = $this->db();
        /** @var MembershipManager $membershipManager */
        $membershipManager = $this->service('Guild\Manager:Guild\MembershipManager');

        $db->beginTransaction();
        try {
            /** @var Guild $guild */
            $guild = $this->em()->create('Guild\Manager:Guild');
            $guild->bulkSet([
                'title' => $title,
                'description' => $description,
                'thread_id' => max(0, $threadId),
                'leader_user_id' => $leader->user_id,
                'leader_username' => $leader->username,
                'created_date' => \XF::$time,
                'last_update' => \XF::$time,
                'guild_state' => 'active'
            ]);
            $guild->save();

            $membershipManager->setMemberRole($guild, $leader, PermissionPreset::ROLE_LEADER);
            $membershipManager->syncMemberCount($guild);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        return $guild;
    }

    public function inviteMember(Guild $guild, User $actor, User $target, ?string $guildRole = null): void
    {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanAddFollowerOperation($guild, $actor, $guildRole);

        /** @var MembershipManager $membershipManager */
        $membershipManager = $this->service('Guild\Manager:Guild\MembershipManager');
        $membershipManager->addMember($guild, $target, PermissionPreset::ROLE_MEMBER, 'invited');
    }

    public function acceptInvite(Guild $guild, User $actor): void
    {
        /** @var MembershipManager $membershipManager */
        $membershipManager = $this->service('Guild\Manager:Guild\MembershipManager');
        $membershipManager->addMember($guild, $actor, PermissionPreset::ROLE_MEMBER, 'active');
    }

    public function updateDescription(
        Guild $guild,
        User $actor,
        string $description,
        string $changeNote = '',
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildDescriptionLog {
        /** @var DescriptionManager $descriptionManager */
        $descriptionManager = $this->service('Guild\Manager:Guild\DescriptionManager');
        return $descriptionManager->updateDescription($guild, $actor, $description, $changeNote, $guildRole);
    }

    public function depositTreasury(
        Guild $guild,
        User $actor,
        string $characterName,
        string $sourceUrl,
        int $amount,
        string $reason = '',
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildTreasuryLog {
        /** @var OperationManager $operationManager */
        $operationManager = $this->service('Guild\Manager:Guild\OperationManager');

        return $operationManager->addTreasuryOperation(
            $guild,
            $actor,
            $characterName,
            $sourceUrl,
            abs($amount),
            'deposit',
            $reason,
            $guildRole
        );
    }

    public function withdrawTreasury(
        Guild $guild,
        User $actor,
        string $characterName,
        string $sourceUrl,
        int $amount,
        string $reason = '',
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildTreasuryLog {
        /** @var OperationManager $operationManager */
        $operationManager = $this->service('Guild\Manager:Guild\OperationManager');

        return $operationManager->addTreasuryOperation(
            $guild,
            $actor,
            $characterName,
            $sourceUrl,
            -abs($amount),
            'withdraw',
            $reason,
            $guildRole
        );
    }

    public function addFollowers(
        Guild $guild,
        User $actor,
        string $characterName,
        string $sourceUrl,
        int $amount,
        string $eventDateText = '',
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildFollowerLog {
        /** @var OperationManager $operationManager */
        $operationManager = $this->service('Guild\Manager:Guild\OperationManager');

        return $operationManager->addFollowerOperation(
            $guild,
            $actor,
            $characterName,
            $sourceUrl,
            abs($amount),
            'gain',
            $eventDateText,
            $guildRole
        );
    }

    public function removeFollowers(
        Guild $guild,
        User $actor,
        string $characterName,
        string $sourceUrl,
        int $amount,
        string $eventDateText = '',
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildFollowerLog {
        /** @var OperationManager $operationManager */
        $operationManager = $this->service('Guild\Manager:Guild\OperationManager');

        return $operationManager->addFollowerOperation(
            $guild,
            $actor,
            $characterName,
            $sourceUrl,
            -abs($amount),
            'loss',
            $eventDateText,
            $guildRole
        );
    }

    public function addReputation(
        Guild $guild,
        User $actor,
        string $regionKey,
        string $characterName,
        string $sourceUrl,
        int $amount,
        string $factionName = '',
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildReputationLog {
        /** @var OperationManager $operationManager */
        $operationManager = $this->service('Guild\Manager:Guild\OperationManager');

        return $operationManager->addReputationOperation(
            $guild,
            $actor,
            $regionKey,
            $characterName,
            $sourceUrl,
            abs($amount),
            'gain',
            $factionName,
            $guildRole
        );
    }

    public function removeReputation(
        Guild $guild,
        User $actor,
        string $regionKey,
        string $characterName,
        string $sourceUrl,
        int $amount,
        string $factionName = '',
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildReputationLog {
        /** @var OperationManager $operationManager */
        $operationManager = $this->service('Guild\Manager:Guild\OperationManager');

        return $operationManager->addReputationOperation(
            $guild,
            $actor,
            $regionKey,
            $characterName,
            $sourceUrl,
            -abs($amount),
            'loss',
            $factionName,
            $guildRole
        );
    }

    public function transferLeadership(
        Guild $guild,
        User $actor,
        User $newLeader,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildLeaderLog {
        /** @var OperationManager $operationManager */
        $operationManager = $this->service('Guild\Manager:Guild\OperationManager');
        /** @var MembershipManager $membershipManager */
        $membershipManager = $this->service('Guild\Manager:Guild\MembershipManager');

        $db = $this->db();
        $db->beginTransaction();
        try {
            $log = $operationManager->changeLeader(
                $guild,
                $actor,
                $newLeader->user_id,
                $newLeader->username,
                $guildRole
            );

            $membershipManager->syncMemberCount($guild);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        return $log;
    }

    public function setGuildFocuses(Guild $guild, User $actor, array $focusKeys, ?string $guildRole = null): void
    {
        /** @var FocusManager $focusManager */
        $focusManager = $this->service('Guild\Manager:Guild\FocusManager');
        $focusManager->setGuildFocuses($guild, $actor, $focusKeys, $guildRole);
    }

    public function updateLeaderByUserId(
        Guild $guild,
        User $actor,
        int $newLeaderUserId,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildLeaderLog {
        /** @var User|null $newLeader */
        $newLeader = $this->em()->find('XF:User', $newLeaderUserId);
        if (!$newLeader) {
            throw new PrintableException('Пользователь не найден.');
        }

        return $this->transferLeadership($guild, $actor, $newLeader, $guildRole);
    }

    public function appointOfficerByUserId(
        Guild $guild,
        User $actor,
        int $officerUserId,
        ?string $guildRole = null
    ): void {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanAppointGuildOfficer($guild, $actor, $guildRole);

        if ($officerUserId <= 0) {
            throw new PrintableException('Укажите корректный ID пользователя.');
        }

        if ((int)$guild->leader_user_id === $officerUserId) {
            throw new PrintableException('Лидер гильдии уже имеет высшую роль; назначать офицером его не нужно.');
        }

        /** @var User|null $target */
        $target = $this->em()->find('XF:User', $officerUserId);
        if (!$target) {
            throw new PrintableException('Пользователь не найден.');
        }

        /** @var MembershipManager $membershipManager */
        $membershipManager = $this->service('Guild\Manager:Guild\MembershipManager');
        $membershipManager->setMemberRole($guild, $target, PermissionPreset::ROLE_OFFICER);
    }

    /** Снятие роли офицера с выбранных пользователей (остаются участниками member, active). */
    public function removeGuildOfficersByUserIds(
        Guild $guild,
        User $actor,
        array $userIds,
        ?string $guildRole = null
    ): void {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanAppointGuildOfficer($guild, $actor, $guildRole);

        $clean = [];
        foreach ($userIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $clean[$id] = $id;
            }
        }
        if ($clean === []) {
            throw new PrintableException('Отметьте хотя бы одного офицера для снятия роли.');
        }

        /** @var \Guild\Manager\Repository\GuildMember $memberRepo */
        $memberRepo = $this->repository('Guild\Manager:GuildMember');
        /** @var MembershipManager $membershipManager */
        $membershipManager = $this->service('Guild\Manager:Guild\MembershipManager');

        $removed = 0;
        foreach ($clean as $userId) {
            if ((int)$guild->leader_user_id === $userId) {
                continue;
            }
            /** @var \Guild\Manager\Entity\GuildMember|null $gm */
            $gm = $memberRepo->findGuildMember($guild->guild_id, $userId)->fetchOne();
            if (
                !$gm
                || $gm->member_state !== 'active'
                || $gm->role !== PermissionPreset::ROLE_OFFICER
            ) {
                continue;
            }
            /** @var User|null $user */
            $user = $this->em()->find('XF:User', $userId);
            if (!$user) {
                continue;
            }
            $membershipManager->setMemberRole($guild, $user, PermissionPreset::ROLE_MEMBER);
            $removed++;
        }

        if ($removed === 0) {
            throw new PrintableException('Среди отмеченных нет активных офицеров для снятия роли.');
        }
    }

    /** Снять офицера from и назначить офицером to (один к одному). */
    public function replaceGuildOfficer(
        Guild $guild,
        User $actor,
        int $fromUserId,
        int $toUserId,
        ?string $guildRole = null
    ): void {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanAppointGuildOfficer($guild, $actor, $guildRole);

        if ($fromUserId <= 0 || $toUserId <= 0) {
            throw new PrintableException('Укажите корректные ID пользователей.');
        }
        if ($fromUserId === $toUserId) {
            throw new PrintableException('Старый и новый ID совпадают.');
        }
        if ((int)$guild->leader_user_id === $toUserId) {
            throw new PrintableException('Лидер гильдии уже имеет высшую роль; назначать офицером его не нужно.');
        }

        /** @var \Guild\Manager\Repository\GuildMember $memberRepo */
        $memberRepo = $this->repository('Guild\Manager:GuildMember');
        /** @var \Guild\Manager\Entity\GuildMember|null $fromMember */
        $fromMember = $memberRepo->findGuildMember($guild->guild_id, $fromUserId)->fetchOne();
        if (
            !$fromMember
            || $fromMember->member_state !== 'active'
            || $fromMember->role !== PermissionPreset::ROLE_OFFICER
        ) {
            throw new PrintableException('Указанный пользователь не является активным офицером.');
        }

        /** @var User|null $fromUser */
        $fromUser = $this->em()->find('XF:User', $fromUserId);
        /** @var User|null $toUser */
        $toUser = $this->em()->find('XF:User', $toUserId);
        if (!$fromUser || !$toUser) {
            throw new PrintableException('Пользователь не найден.');
        }

        /** @var MembershipManager $membershipManager */
        $membershipManager = $this->service('Guild\Manager:Guild\MembershipManager');
        $membershipManager->setMemberRole($guild, $fromUser, PermissionPreset::ROLE_MEMBER);
        $membershipManager->setMemberRole($guild, $toUser, PermissionPreset::ROLE_OFFICER);
    }

    public function addStorageItem(
        Guild $guild,
        User $actor,
        string $itemName,
        string $itemDescription,
        string $rarity,
        string $itemUrl,
        string $sourceUrl,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildStorage {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanManageStorage($guild, $actor, $guildRole);
        /** @var ActionLogger $actionLogger */
        $actionLogger = $this->service('Guild\Manager:Guild\ActionLogger');

        /** @var \Guild\Manager\Entity\GuildStorage $row */
        $row = $this->em()->create('Guild\Manager:GuildStorage');
        $row->bulkSet([
            'guild_id' => $guild->guild_id,
            'item_name' => $itemName,
            'item_description' => $itemDescription,
            'rarity' => $rarity,
            'item_url' => $itemUrl,
            'source_url' => $sourceUrl,
            'created_by_user_id' => $actor->user_id,
            'created_date' => \XF::$time,
        ]);
        $row->save();
        $actionLogger->log($guild, $actor, 'storage', ActionLogger::ACTION_ADD, 'Склад: добавлен предмет ' . $itemName);

        return $row;
    }

    public function addAchievement(
        Guild $guild,
        User $actor,
        string $bbcode,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildAchievement {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanManageAchievements($guild, $actor, $guildRole);

        $rendered = BbCodeContent::renderToHtml($this->app, $bbcode);

        /** @var \Guild\Manager\Entity\GuildAchievement $row */
        $row = $this->em()->create('Guild\Manager:GuildAchievement');
        $row->bulkSet([
            'guild_id' => $guild->guild_id,
            'achievement_bbcode' => $bbcode,
            'achievement_rendered' => $rendered,
            'display_order' => \XF::$time,
            'created_by_user_id' => $actor->user_id,
            'created_date' => \XF::$time,
        ]);
        $row->save();

        return $row;
    }

    public function deleteAchievement(
        Guild $guild,
        User $actor,
        int $achievementId,
        ?string $guildRole = null
    ): void {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanManageAchievements($guild, $actor, $guildRole);

        /** @var \Guild\Manager\Entity\GuildAchievement|null $row */
        $row = $this->em()->find('Guild\Manager:GuildAchievement', $achievementId);
        if (!$row || (int)$row->guild_id !== (int)$guild->guild_id) {
            throw new PrintableException('Запись не найдена.');
        }

        $row->delete();
    }

    public function updateMembersBlock(
        Guild $guild,
        User $actor,
        string $bbcode,
        ?string $guildRole = null
    ): void {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanManageMembersBlock($guild, $actor, $guildRole);

        $rendered = BbCodeContent::renderToHtml($this->app, $bbcode, true);
        $guild->members_bbcode = $bbcode;
        $guild->members_bbcode_rendered = $rendered;
        $guild->last_update = \XF::$time;
        $guild->save();
    }

    public function addImportantNpc(
        Guild $guild,
        User $actor,
        string $npcName,
        string $bbcode,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildImportantNpc {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanManageImportantNpcs($guild, $actor, $guildRole);

        $rendered = BbCodeContent::renderToHtml($this->app, $bbcode, true);

        /** @var \Guild\Manager\Entity\GuildImportantNpc $row */
        $row = $this->em()->create('Guild\Manager:GuildImportantNpc');
        $row->bulkSet([
            'guild_id' => $guild->guild_id,
            'npc_name' => $npcName,
            'npc_bbcode' => $bbcode,
            'npc_rendered' => $rendered,
            'display_order' => \XF::$time,
            'created_by_user_id' => $actor->user_id,
            'created_date' => \XF::$time,
            'last_update' => \XF::$time,
        ]);
        $row->save();

        return $row;
    }

    public function updateImportantNpc(
        Guild $guild,
        User $actor,
        int $npcId,
        string $npcName,
        string $bbcode,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildImportantNpc {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanManageImportantNpcs($guild, $actor, $guildRole);

        /** @var \Guild\Manager\Entity\GuildImportantNpc|null $row */
        $row = $this->em()->find('Guild\Manager:GuildImportantNpc', $npcId);
        if (!$row || (int)$row->guild_id !== (int)$guild->guild_id) {
            throw new PrintableException('Запись не найдена.');
        }

        $row->bulkSet([
            'npc_name' => $npcName,
            'npc_bbcode' => $bbcode,
            'npc_rendered' => BbCodeContent::renderToHtml($this->app, $bbcode, true),
            'last_update' => \XF::$time,
        ]);
        $row->save();

        return $row;
    }

    public function deleteImportantNpc(
        Guild $guild,
        User $actor,
        int $npcId,
        ?string $guildRole = null
    ): void {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanDeleteImportantNpcs($guild, $actor, $guildRole);

        /** @var \Guild\Manager\Entity\GuildImportantNpc|null $row */
        $row = $this->em()->find('Guild\Manager:GuildImportantNpc', $npcId);
        if (!$row || (int)$row->guild_id !== (int)$guild->guild_id) {
            throw new PrintableException('Запись не найдена.');
        }

        $row->delete();
    }

    public function addVehicle(
        Guild $guild,
        User $actor,
        string $name,
        string $status,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildVehicle {
        /** @var OperationManager $operationManager */
        $operationManager = $this->service('Guild\Manager:Guild\OperationManager');

        return $operationManager->addVehicle($guild, $actor, $name, $status, $guildRole);
    }

    public function updateTreasuryLog(
        Guild $guild,
        User $actor,
        int $logId,
        string $characterName,
        string $sourceUrl,
        string $type,
        int $amount,
        string $reason,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildTreasuryLog {
        /** @var OperationManager $operationManager */
        $operationManager = $this->service('Guild\Manager:Guild\OperationManager');

        if ($type === 'income' || $type === 'deposit') {
            return $operationManager->updateTreasuryLogEntry(
                $guild,
                $actor,
                $logId,
                $characterName,
                $sourceUrl,
                abs($amount),
                'deposit',
                $reason,
                $guildRole
            );
        }

        return $operationManager->updateTreasuryLogEntry(
            $guild,
            $actor,
            $logId,
            $characterName,
            $sourceUrl,
            -abs($amount),
            'withdraw',
            $reason,
            $guildRole
        );
    }

    public function deleteTreasuryLog(
        Guild $guild,
        User $actor,
        int $logId,
        ?string $guildRole = null
    ): void {
        /** @var OperationManager $operationManager */
        $operationManager = $this->service('Guild\Manager:Guild\OperationManager');
        $operationManager->deleteTreasuryLogEntry($guild, $actor, $logId, $guildRole);
    }

    public function updateFollowerLog(
        Guild $guild,
        User $actor,
        int $logId,
        string $characterName,
        string $sourceUrl,
        string $type,
        int $amount,
        string $date,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildFollowerLog {
        /** @var OperationManager $operationManager */
        $operationManager = $this->service('Guild\Manager:Guild\OperationManager');

        if ($type === 'gain') {
            return $operationManager->updateFollowerLogEntry(
                $guild,
                $actor,
                $logId,
                $characterName,
                $sourceUrl,
                abs($amount),
                'gain',
                $date,
                $guildRole
            );
        }

        return $operationManager->updateFollowerLogEntry(
            $guild,
            $actor,
            $logId,
            $characterName,
            $sourceUrl,
            -abs($amount),
            'loss',
            $date,
            $guildRole
        );
    }

    public function deleteFollowerLog(Guild $guild, User $actor, int $logId, ?string $guildRole = null): void
    {
        /** @var OperationManager $operationManager */
        $operationManager = $this->service('Guild\Manager:Guild\OperationManager');
        $operationManager->deleteFollowerLogEntry($guild, $actor, $logId, $guildRole);
    }

    public function updateReputationLog(
        Guild $guild,
        User $actor,
        int $logId,
        string $regionKey,
        string $characterName,
        string $sourceUrl,
        string $type,
        int $amount,
        string $factionName,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildReputationLog {
        /** @var OperationManager $operationManager */
        $operationManager = $this->service('Guild\Manager:Guild\OperationManager');

        if ($type === 'gain') {
            return $operationManager->updateReputationLogEntry(
                $guild,
                $actor,
                $logId,
                $regionKey,
                $characterName,
                $sourceUrl,
                abs($amount),
                'gain',
                $factionName,
                $guildRole
            );
        }

        return $operationManager->updateReputationLogEntry(
            $guild,
            $actor,
            $logId,
            $regionKey,
            $characterName,
            $sourceUrl,
            -abs($amount),
            'loss',
            $factionName,
            $guildRole
        );
    }

    public function deleteReputationLog(Guild $guild, User $actor, int $logId, ?string $guildRole = null): void
    {
        /** @var OperationManager $operationManager */
        $operationManager = $this->service('Guild\Manager:Guild\OperationManager');
        $operationManager->deleteReputationLogEntry($guild, $actor, $logId, $guildRole);
    }

    public function updateStorageItem(
        Guild $guild,
        User $actor,
        int $storageId,
        string $itemName,
        string $itemDescription,
        string $rarity,
        string $itemUrl,
        string $sourceUrl,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildStorage {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanEditStorageItemAsForumAdmin($guild, $actor, $guildRole);
        /** @var ActionLogger $actionLogger */
        $actionLogger = $this->service('Guild\Manager:Guild\ActionLogger');

        /** @var \Guild\Manager\Entity\GuildStorage|null $row */
        $row = $this->em()->find('Guild\Manager:GuildStorage', $storageId);
        if (!$row || (int)$row->guild_id !== (int)$guild->guild_id) {
            throw new PrintableException('Запись не найдена.');
        }

        $row->bulkSet([
            'item_name' => $itemName,
            'item_description' => $itemDescription,
            'rarity' => $rarity,
            'item_url' => $itemUrl,
            'source_url' => $sourceUrl,
        ]);
        $row->save();
        $actionLogger->log($guild, $actor, 'storage', ActionLogger::ACTION_UPDATE, 'Склад: изменен предмет ' . $itemName);

        return $row;
    }

    public function deleteStorageItem(Guild $guild, User $actor, int $storageId, ?string $guildRole = null): void
    {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanDeleteStorageItem($guild, $actor, $guildRole);
        /** @var ActionLogger $actionLogger */
        $actionLogger = $this->service('Guild\Manager:Guild\ActionLogger');

        /** @var \Guild\Manager\Entity\GuildStorage|null $row */
        $row = $this->em()->find('Guild\Manager:GuildStorage', $storageId);
        if (!$row || (int)$row->guild_id !== (int)$guild->guild_id) {
            throw new PrintableException('Запись не найдена.');
        }

        $summary = 'Склад: удален предмет ' . (string)$row->item_name;
        $row->delete();
        $actionLogger->log($guild, $actor, 'storage', ActionLogger::ACTION_DELETE, $summary);
    }

    public function updateVehicle(
        Guild $guild,
        User $actor,
        int $vehicleId,
        string $name,
        string $status,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildVehicle {
        /** @var OperationManager $operationManager */
        $operationManager = $this->service('Guild\Manager:Guild\OperationManager');

        return $operationManager->updateVehicle($guild, $actor, $vehicleId, $name, $status, $guildRole);
    }

    public function deleteVehicle(Guild $guild, User $actor, int $vehicleId, ?string $guildRole = null): void
    {
        /** @var OperationManager $operationManager */
        $operationManager = $this->service('Guild\Manager:Guild\OperationManager');
        $operationManager->deleteVehicle($guild, $actor, $vehicleId, $guildRole);
    }

    public function addGuildBase(Guild $guild, User $actor, string $baseName, string $bbcode, ?string $guildRole = null): \Guild\Manager\Entity\GuildBase
    {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanManageGuildBases($guild, $actor, $guildRole);

        $rendered = BbCodeContent::renderToHtml($this->app, $bbcode, true);

        $nextOrder = (int)$this->db()->fetchOne(
            '
                SELECT COALESCE(MAX(display_order), 0) + 1
                FROM xf_guild_base
                WHERE guild_id = ?
            ',
            [$guild->guild_id]
        );

        /** @var \Guild\Manager\Entity\GuildBase $row */
        $row = $this->em()->create('Guild\Manager:GuildBase');
        $row->bulkSet([
            'guild_id' => $guild->guild_id,
            'base_name' => $baseName,
            'base_bbcode' => $bbcode,
            'base_rendered' => $rendered,
            'display_order' => $nextOrder ?: \XF::$time,
            'created_by_user_id' => $actor->user_id,
            'created_date' => \XF::$time,
            'last_update' => \XF::$time,
        ]);
        $row->save();

        return $row;
    }

    public function updateGuildBase(Guild $guild, User $actor, int $baseId, string $baseName, string $bbcode, ?string $guildRole = null): \Guild\Manager\Entity\GuildBase
    {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanManageGuildBases($guild, $actor, $guildRole);

        /** @var \Guild\Manager\Entity\GuildBase|null $row */
        $row = $this->em()->find('Guild\Manager:GuildBase', $baseId);
        if (!$row || (int)$row->guild_id !== (int)$guild->guild_id) {
            throw new PrintableException('Запись не найдена.');
        }

        $rendered = BbCodeContent::renderToHtml($this->app, $bbcode, true);
        $row->bulkSet([
            'base_name' => $baseName,
            'base_bbcode' => $bbcode,
            'base_rendered' => $rendered,
            'last_update' => \XF::$time,
        ]);
        $row->save();

        return $row;
    }

    public function deleteGuildBase(Guild $guild, User $actor, int $baseId, ?string $guildRole = null): void
    {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanManageGuildBases($guild, $actor, $guildRole);

        /** @var \Guild\Manager\Entity\GuildBase|null $row */
        $row = $this->em()->find('Guild\Manager:GuildBase', $baseId);
        if (!$row || (int)$row->guild_id !== (int)$guild->guild_id) {
            throw new PrintableException('Запись не найдена.');
        }

        foreach ($this->finder('Guild\Manager:GuildBaseBuilding')->where('guild_base_id', $baseId)->fetch() as $b) {
            $b->delete();
        }

        $row->delete();
    }

    public function assertBaseBelongsGuild(Guild $guild, int $baseId): \Guild\Manager\Entity\GuildBase
    {
        /** @var \Guild\Manager\Entity\GuildBase|null $row */
        $row = $this->em()->find('Guild\Manager:GuildBase', $baseId);
        if (!$row || (int)$row->guild_id !== (int)$guild->guild_id) {
            throw new PrintableException('База не найдена.');
        }

        return $row;
    }

    public function addGuildBaseBuilding(
        Guild $guild,
        User $actor,
        int $baseId,
        string $name,
        string $buildingLevel,
        string $directionText,
        string $lieutenantName,
        string $bonusText,
        string $followersText,
        string $descriptionBbcode,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildBaseBuilding {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanManageGuildBases($guild, $actor, $guildRole);
        $this->assertBaseBelongsGuild($guild, $baseId);

        $rendered = $descriptionBbcode !== ''
            ? BbCodeContent::renderToHtml($this->app, $descriptionBbcode, true)
            : '';

        $nextOrder = (int)$this->db()->fetchOne(
            '
                SELECT COALESCE(MAX(display_order), 0) + 1
                FROM xf_guild_base_building
                WHERE guild_base_id = ?
            ',
            [$baseId]
        );

        /** @var \Guild\Manager\Entity\GuildBaseBuilding $row */
        $row = $this->em()->create('Guild\Manager:GuildBaseBuilding');
        $row->bulkSet([
            'guild_base_id' => $baseId,
            'guild_id' => $guild->guild_id,
            'building_name' => $name,
            'building_level' => $buildingLevel,
            'direction_text' => $directionText,
            'lieutenant_name' => $lieutenantName,
            'bonus_text' => $bonusText,
            'followers_text' => $followersText,
            'description_bbcode' => $descriptionBbcode,
            'description_rendered' => $rendered,
            'display_order' => $nextOrder ?: \XF::$time,
            'created_date' => \XF::$time,
            'last_update' => \XF::$time,
        ]);
        $row->save();

        return $row;
    }

    public function updateGuildBaseBuilding(
        Guild $guild,
        User $actor,
        int $baseId,
        int $buildingId,
        string $name,
        string $buildingLevel,
        string $directionText,
        string $lieutenantName,
        string $bonusText,
        string $followersText,
        string $descriptionBbcode,
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildBaseBuilding {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanManageGuildBases($guild, $actor, $guildRole);
        $this->assertBaseBelongsGuild($guild, $baseId);

        /** @var \Guild\Manager\Entity\GuildBaseBuilding|null $row */
        $row = $this->em()->find('Guild\Manager:GuildBaseBuilding', $buildingId);
        if (!$row
            || (int)$row->guild_base_id !== $baseId
            || (int)$row->guild_id !== (int)$guild->guild_id) {
            throw new PrintableException('Запись не найдена.');
        }

        $rendered = $descriptionBbcode !== ''
            ? BbCodeContent::renderToHtml($this->app, $descriptionBbcode, true)
            : '';

        $row->bulkSet([
            'building_name' => $name,
            'building_level' => $buildingLevel,
            'direction_text' => $directionText,
            'lieutenant_name' => $lieutenantName,
            'bonus_text' => $bonusText,
            'followers_text' => $followersText,
            'description_bbcode' => $descriptionBbcode,
            'description_rendered' => $rendered,
            'last_update' => \XF::$time,
        ]);
        $row->save();

        return $row;
    }

    public function deleteGuildBaseBuilding(Guild $guild, User $actor, int $baseId, int $buildingId, ?string $guildRole = null): void
    {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanManageGuildBases($guild, $actor, $guildRole);
        $this->assertBaseBelongsGuild($guild, $baseId);

        /** @var \Guild\Manager\Entity\GuildBaseBuilding|null $row */
        $row = $this->em()->find('Guild\Manager:GuildBaseBuilding', $buildingId);
        if (!$row
            || (int)$row->guild_base_id !== $baseId
            || (int)$row->guild_id !== (int)$guild->guild_id) {
            throw new PrintableException('Запись не найдена.');
        }

        $row->delete();
    }
}
