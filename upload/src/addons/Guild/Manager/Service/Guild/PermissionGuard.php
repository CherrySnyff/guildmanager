<?php

namespace Guild\Manager\Service\Guild;

use Guild\Manager\Entity\Guild;
use XF;
use XF\Entity\User;
use XF\PrintableException;
use XF\Service\AbstractService;

/**
 * Центральная точка решения «может ли действие User над Guild» на публичной стороне.
 *
 * Сначала проверяются глобальные permission XenForo (guild_manager:*, в т.ч. *Any); иначе — роль в гильдии
 * через MembershipManager и матрица PermissionPreset.
 */
class PermissionGuard extends AbstractService
{
    protected function resolveGuildRole(Guild $guild, User $actor, ?string $guildRole): ?string
    {
        if ($guildRole !== null && $guildRole !== '') {
            return $guildRole;
        }

        /** @var MembershipManager $membershipManager */
        $membershipManager = $this->service('Guild\Manager:Guild\MembershipManager');
        return $membershipManager->getUserGuildRole($guild, $actor);
    }

    protected function canByPreset(?string $guildRole, string $action): bool
    {
        if ($guildRole === null || $guildRole === '') {
            return false;
        }

        /** @var PermissionPreset $preset */
        $preset = $this->service('Guild\Manager:Guild\PermissionPreset');
        return $preset->canRole($guildRole, $action);
    }

    public function canManageGuild(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        $guildRole = $this->resolveGuildRole($guild, $actor, $guildRole);

        if ($actor->hasPermission('guild_manager', 'manageGuildAny')) {
            return true;
        }

        if ($this->canByPreset($guildRole, PermissionPreset::ACTION_MANAGE_GUILD)) {
            return true;
        }

        return $guild->leader_user_id > 0 && $guild->leader_user_id === $actor->user_id;
    }

    public function canAddTreasuryOperation(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        $guildRole = $this->resolveGuildRole($guild, $actor, $guildRole);

        if ($actor->hasPermission('guild_manager', 'manageTreasuryAny')) {
            return true;
        }

        if ($this->canByPreset($guildRole, PermissionPreset::ACTION_ADD_TREASURY)) {
            return true;
        }

        return $this->canManageGuild($guild, $actor, $guildRole);
    }

    public function canAddFollowerOperation(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        $guildRole = $this->resolveGuildRole($guild, $actor, $guildRole);

        if ($actor->hasPermission('guild_manager', 'manageMembersAny')) {
            return true;
        }

        if ($this->canByPreset($guildRole, PermissionPreset::ACTION_ADD_FOLLOWER)) {
            return true;
        }

        return $this->canManageGuild($guild, $actor, $guildRole);
    }

    public function canAddReputationOperation(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        $guildRole = $this->resolveGuildRole($guild, $actor, $guildRole);

        if ($actor->hasPermission('guild_manager', 'manageReputationAny')) {
            return true;
        }

        if ($this->canByPreset($guildRole, PermissionPreset::ACTION_ADD_REPUTATION)) {
            return true;
        }

        return $this->canManageGuild($guild, $actor, $guildRole);
    }

    public function canChangeLeader(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        $guildRole = $this->resolveGuildRole($guild, $actor, $guildRole);

        if ($this->canByPreset($guildRole, PermissionPreset::ACTION_CHANGE_LEADER)) {
            return true;
        }

        return $this->canManageGuild($guild, $actor, $guildRole);
    }

    public function canEditDescription(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        $guildRole = $this->resolveGuildRole($guild, $actor, $guildRole);

        if ($actor->hasPermission('guild_manager', 'manageGuildAny')) {
            return true;
        }

        if ($this->canByPreset($guildRole, PermissionPreset::ACTION_EDIT_DESCRIPTION)) {
            return true;
        }

        return $guild->leader_user_id > 0 && $guild->leader_user_id === $actor->user_id;
    }

    public function assertCanAddTreasuryOperation(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canAddTreasuryOperation($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function assertCanAddFollowerOperation(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canAddFollowerOperation($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function assertCanAddReputationOperation(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canAddReputationOperation($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function assertCanChangeLeader(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canChangeLeader($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    /** Назначение офицера по ID: лидер гильдии или глобальное право manageGuildAny. */
    public function canAppointGuildOfficer(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        if ($actor->hasPermission('guild_manager', 'manageGuildAny')) {
            return true;
        }

        return $guild->leader_user_id > 0 && (int)$guild->leader_user_id === (int)$actor->user_id;
    }

    public function assertCanAppointGuildOfficer(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canAppointGuildOfficer($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function assertCanEditDescription(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canEditDescription($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function canManageStorage(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        $guildRole = $this->resolveGuildRole($guild, $actor, $guildRole);

        if ($actor->hasPermission('guild_manager', 'manageGuildAny')) {
            return true;
        }

        if ($this->canByPreset($guildRole, PermissionPreset::ACTION_MANAGE_STORAGE)) {
            return true;
        }

        return $this->canManageGuild($guild, $actor, $guildRole);
    }

    public function canManageAchievements(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        $guildRole = $this->resolveGuildRole($guild, $actor, $guildRole);

        if ($actor->hasPermission('guild_manager', 'manageGuildAny')) {
            return true;
        }

        if ($this->canByPreset($guildRole, PermissionPreset::ACTION_MANAGE_ACHIEVEMENTS)) {
            return true;
        }

        return $this->canManageGuild($guild, $actor, $guildRole);
    }

    public function canManageMembersBlock(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        $guildRole = $this->resolveGuildRole($guild, $actor, $guildRole);

        if ($actor->hasPermission('guild_manager', 'manageGuildAny')) {
            return true;
        }

        if ($this->canByPreset($guildRole, PermissionPreset::ACTION_MANAGE_MEMBERS_BLOCK)) {
            return true;
        }

        return $this->canManageGuild($guild, $actor, $guildRole);
    }

    public function canManageImportantNpcs(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        $guildRole = $this->resolveGuildRole($guild, $actor, $guildRole);

        if (
            $actor->hasPermission('guild_manager', 'manageGuildAny')
            || $actor->hasPermission('guild_manager', 'manageImportantNpcsAny')
        ) {
            return true;
        }

        if ($this->canByPreset($guildRole, PermissionPreset::ACTION_MANAGE_IMPORTANT_NPCS)) {
            return true;
        }

        return $guild->leader_user_id > 0 && $guild->leader_user_id === $actor->user_id;
    }

    public function canDeleteImportantNpcs(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        $guildRole = $this->resolveGuildRole($guild, $actor, $guildRole);

        if (
            $actor->hasPermission('guild_manager', 'manageGuildAny')
            || $actor->hasPermission('guild_manager', 'manageImportantNpcsAny')
        ) {
            return true;
        }

        if ($this->canByPreset($guildRole, PermissionPreset::ACTION_DELETE_IMPORTANT_NPCS)) {
            return true;
        }

        return $guild->leader_user_id > 0 && $guild->leader_user_id === $actor->user_id;
    }

    public function canManageDirections(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        $guildRole = $this->resolveGuildRole($guild, $actor, $guildRole);

        if ($actor->hasPermission('guild_manager', 'editGuildDirectionsAny')) {
            return true;
        }

        if ($this->canByPreset($guildRole, PermissionPreset::ACTION_MANAGE_DIRECTIONS)) {
            return true;
        }

        return $guild->leader_user_id > 0 && $guild->leader_user_id === $actor->user_id;
    }

    public function assertCanManageStorage(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canManageStorage($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function assertCanManageAchievements(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canManageAchievements($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function assertCanManageMembersBlock(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canManageMembersBlock($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function assertCanManageImportantNpcs(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canManageImportantNpcs($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function assertCanDeleteImportantNpcs(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canDeleteImportantNpcs($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function assertCanManageDirections(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canManageDirections($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    /** Базы гильдии: администратор форума, лидер и офицер (внутри своей гильдии). */
    public function canManageGuildBases(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        $guildRole = $this->resolveGuildRole($guild, $actor, $guildRole);

        if ($actor->hasPermission('guild_manager', 'manageGuildAny')) {
            return true;
        }

        if ($this->canByPreset($guildRole, PermissionPreset::ACTION_MANAGE_BASES)) {
            return true;
        }

        return $guild->leader_user_id > 0 && $guild->leader_user_id === $actor->user_id;
    }

    public function assertCanManageGuildBases(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canManageGuildBases($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function canEditTreasuryLogEntry(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        return $actor->hasPermission('guild_manager', 'manageGuildAny')
            || $actor->hasPermission('guild_manager', 'manageTreasuryAny');
    }

    /**
     * Удаление строк казны — только глобальные админские права (ТЗ: лучше только админ).
     */
    public function canDeleteTreasuryLogEntry(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        return $actor->hasPermission('guild_manager', 'manageGuildAny')
            || $actor->hasPermission('guild_manager', 'manageTreasuryAny');
    }

    public function canEditFollowerLogEntry(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        return $actor->hasPermission('guild_manager', 'manageGuildAny')
            || $actor->hasPermission('guild_manager', 'manageMembersAny');
    }

    public function canDeleteFollowerLogEntry(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        return $actor->hasPermission('guild_manager', 'manageGuildAny')
            || $actor->hasPermission('guild_manager', 'manageMembersAny');
    }

    public function canEditReputationLogEntry(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        return $actor->hasPermission('guild_manager', 'manageGuildAny')
            || $actor->hasPermission('guild_manager', 'manageReputationAny');
    }

    public function canDeleteReputationLogEntry(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        return $actor->hasPermission('guild_manager', 'manageGuildAny')
            || $actor->hasPermission('guild_manager', 'manageReputationAny');
    }

    public function canDeleteStorageItem(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        return $actor->hasPermission('guild_manager', 'manageGuildAny');
    }

    public function canDeleteVehicle(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        return $actor->hasPermission('guild_manager', 'manageGuildAny');
    }

    public function canEditVehicle(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        return $this->canAddFollowerOperation($guild, $actor, $guildRole);
    }

    public function assertCanEditTreasuryLogEntry(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canEditTreasuryLogEntry($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function assertCanDeleteTreasuryLogEntry(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canDeleteTreasuryLogEntry($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function assertCanEditFollowerLogEntry(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canEditFollowerLogEntry($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function assertCanDeleteFollowerLogEntry(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canDeleteFollowerLogEntry($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function assertCanEditReputationLogEntry(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canEditReputationLogEntry($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function assertCanDeleteReputationLogEntry(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canDeleteReputationLogEntry($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function assertCanDeleteStorageItem(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canDeleteStorageItem($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    /**
     * Редактирование строк склада — только глобальное право «управление любой гильдией» (как колонка «Действия»).
     */
    public function assertCanEditStorageItemAsForumAdmin(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$actor->hasPermission('guild_manager', 'manageGuildAny')) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function assertCanDeleteVehicle(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canDeleteVehicle($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function assertCanEditVehicle(Guild $guild, User $actor, ?string $guildRole = null): void
    {
        if (!$this->canEditVehicle($guild, $actor, $guildRole)) {
            throw new PrintableException(XF::phrase('no_permission'));
        }
    }

    public function canSearchLeaderUsers(Guild $guild, User $actor, ?string $guildRole = null): bool
    {
        return $this->canChangeLeader($guild, $actor, $guildRole);
    }
}
