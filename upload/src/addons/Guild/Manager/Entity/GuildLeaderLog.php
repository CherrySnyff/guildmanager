<?php

namespace Guild\Manager\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/** Запись о смене лидера. Таблица xf_guild_leader_log. */
class GuildLeaderLog extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_guild_leader_log';
        $structure->shortName = 'Guild\Manager:GuildLeaderLog';
        $structure->primaryKey = 'leader_log_id';
        $structure->columns = [
            'leader_log_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'guild_id' => ['type' => self::UINT, 'required' => true],
            'old_user_id' => ['type' => self::UINT, 'default' => 0],
            'new_user_id' => ['type' => self::UINT, 'default' => 0],
            'changed_by_user_id' => ['type' => self::UINT, 'default' => 0],
            'change_date' => ['type' => self::UINT, 'default' => 0],
        ];
        $structure->getters = [];
        $structure->relations = [];

        return $structure;
    }
}
