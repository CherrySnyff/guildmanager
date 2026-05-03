<?php

namespace Guild\Manager\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/** Одна выбранная направленность в слоте (display_order 1–4 → focus_key). Таблица xf_guild_focus. */
class GuildFocus extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_guild_focus';
        $structure->shortName = 'Guild\Manager:GuildFocus';
        $structure->primaryKey = 'guild_focus_id';
        $structure->columns = [
            'guild_focus_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'guild_id' => ['type' => self::UINT, 'required' => true],
            'focus_key' => ['type' => self::STR, 'maxLength' => 50, 'required' => true],
            'display_order' => ['type' => self::UINT, 'default' => 1],
            'created_date' => ['type' => self::UINT, 'default' => 0],
        ];
        $structure->getters = [];
        $structure->relations = [];

        return $structure;
    }
}
