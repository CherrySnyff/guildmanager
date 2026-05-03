<?php

namespace Guild\Manager\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/** Участник гильдии: user_id, роль, состояние (active/invited/banned). Таблица xf_guild_member. */
class GuildMember extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_guild_member';
        $structure->shortName = 'Guild\Manager:GuildMember';
        $structure->primaryKey = 'guild_member_id';
        $structure->columns = [
            'guild_member_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'guild_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'username' => ['type' => self::STR, 'maxLength' => 100, 'default' => ''],
            'role' => ['type' => self::STR, 'allowedValues' => ['leader', 'officer', 'member'], 'default' => 'member'],
            'member_state' => ['type' => self::STR, 'allowedValues' => ['active', 'invited', 'banned'], 'default' => 'active'],
            'joined_date' => ['type' => self::UINT, 'default' => 0],
            'last_update' => ['type' => self::UINT, 'default' => 0],
        ];
        $structure->getters = [];
        $structure->relations = [];

        return $structure;
    }
}
