<?php

namespace Guild\Manager\Service\Guild;

use XF\Service\AbstractService;

/**
 * Статическая матрица «роль leader/officer/member → разрешённые действия» без обращений к XenForо permission напрямую.
 */
class PermissionPreset extends AbstractService
{
    public const ROLE_LEADER = 'leader';
    public const ROLE_OFFICER = 'officer';
    public const ROLE_MEMBER = 'member';

    public const ACTION_MANAGE_GUILD = 'manage_guild';
    public const ACTION_ADD_TREASURY = 'add_treasury_operation';
    public const ACTION_ADD_FOLLOWER = 'add_follower_operation';
    public const ACTION_ADD_REPUTATION = 'add_reputation_operation';
    public const ACTION_CHANGE_LEADER = 'change_leader';
    public const ACTION_EDIT_DESCRIPTION = 'edit_description';
    public const ACTION_MANAGE_STORAGE = 'manage_storage';
    public const ACTION_MANAGE_ACHIEVEMENTS = 'manage_achievements';
    public const ACTION_MANAGE_MEMBERS_BLOCK = 'manage_members_block';

    public function getRoleMatrix(): array
    {
        return [
            self::ROLE_LEADER => [
                self::ACTION_MANAGE_GUILD => true,
                self::ACTION_ADD_TREASURY => true,
                self::ACTION_ADD_FOLLOWER => true,
                self::ACTION_ADD_REPUTATION => true,
                self::ACTION_CHANGE_LEADER => true,
                self::ACTION_EDIT_DESCRIPTION => true,
                self::ACTION_MANAGE_STORAGE => true,
                self::ACTION_MANAGE_ACHIEVEMENTS => true,
                self::ACTION_MANAGE_MEMBERS_BLOCK => true,
            ],
            self::ROLE_OFFICER => [
                self::ACTION_MANAGE_GUILD => false,
                self::ACTION_ADD_TREASURY => true,
                self::ACTION_ADD_FOLLOWER => true,
                self::ACTION_ADD_REPUTATION => true,
                self::ACTION_CHANGE_LEADER => false,
                self::ACTION_EDIT_DESCRIPTION => true,
                self::ACTION_MANAGE_STORAGE => true,
                self::ACTION_MANAGE_ACHIEVEMENTS => true,
                self::ACTION_MANAGE_MEMBERS_BLOCK => true,
            ],
            self::ROLE_MEMBER => [
                self::ACTION_MANAGE_GUILD => false,
                self::ACTION_ADD_TREASURY => false,
                self::ACTION_ADD_FOLLOWER => false,
                self::ACTION_ADD_REPUTATION => false,
                self::ACTION_CHANGE_LEADER => false,
                self::ACTION_EDIT_DESCRIPTION => false,
                self::ACTION_MANAGE_STORAGE => false,
                self::ACTION_MANAGE_ACHIEVEMENTS => false,
                self::ACTION_MANAGE_MEMBERS_BLOCK => false,
            ],
        ];
    }

    public function isKnownRole(string $role): bool
    {
        return array_key_exists($role, $this->getRoleMatrix());
    }

    public function canRole(string $role, string $action): bool
    {
        $matrix = $this->getRoleMatrix();
        if (!isset($matrix[$role])) {
            return false;
        }

        return !empty($matrix[$role][$action]);
    }
}
