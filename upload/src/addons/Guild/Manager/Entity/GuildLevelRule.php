<?php

namespace Guild\Manager\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/** Справочная строка диапазона последователей для уровня (агрегирующие поля size_label здесь вторичны). Таблица xf_guild_level_rule. */
class GuildLevelRule extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_guild_level_rule';
        $structure->shortName = 'Guild\Manager:GuildLevelRule';
        $structure->primaryKey = 'level';
        $structure->columns = [
            'level' => ['type' => self::UINT, 'required' => true, 'max' => 20],
            'followers_min' => ['type' => self::UINT, 'default' => 0],
            'followers_max' => ['type' => self::UINT, 'nullable' => true, 'default' => null],
            'size_label' => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
        ];
        $structure->getters = [];
        $structure->relations = [];

        return $structure;
    }
}
