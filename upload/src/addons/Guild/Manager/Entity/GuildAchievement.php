<?php

namespace Guild\Manager\Entity;

use XF;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/** Достижение гильдии (маршрут /achievements). Таблица xf_guild_achievement при наличии в схеме. */
class GuildAchievement extends Entity
{
    protected function verifyAchievementBbcode(&$text): bool
    {
        $text = (string)$text;
        if (trim($text) === '') {
            $this->error(XF::phrase('please_enter_valid_value'), 'achievement_bbcode');
            return false;
        }

        return true;
    }

    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_guild_achievement';
        $structure->shortName = 'Guild\Manager:GuildAchievement';
        $structure->primaryKey = 'achievement_id';
        $structure->columns = [
            'achievement_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'guild_id' => ['type' => self::UINT, 'required' => true],
            'achievement_bbcode' => ['type' => self::STR, 'default' => '', 'verify' => 'verifyAchievementBbcode'],
            'achievement_rendered' => ['type' => self::STR, 'default' => ''],
            'display_order' => ['type' => self::UINT, 'default' => 0],
            'created_by_user_id' => ['type' => self::UINT, 'default' => 0],
            'created_date' => ['type' => self::UINT, 'default' => 0],
        ];
        $structure->getters = [];
        $structure->relations = [];

        return $structure;
    }
}
