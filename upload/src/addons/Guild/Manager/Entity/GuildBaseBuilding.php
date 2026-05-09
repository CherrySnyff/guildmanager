<?php

namespace Guild\Manager\Entity;

use XF;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/** Здание на базе. Таблица xf_guild_base_building. */
class GuildBaseBuilding extends Entity
{
    protected function verifyBuildingName(&$text): bool
    {
        $text = trim((string)$text);
        if ($text === '') {
            $this->error(XF::phrase('please_enter_valid_value'), 'building_name');
            return false;
        }

        return true;
    }

    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_guild_base_building';
        $structure->shortName = 'Guild\Manager:GuildBaseBuilding';
        $structure->primaryKey = 'guild_base_building_id';
        $structure->columns = [
            'guild_base_building_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'guild_base_id' => ['type' => self::UINT, 'required' => true],
            'guild_id' => ['type' => self::UINT, 'required' => true],
            'building_name' => ['type' => self::STR, 'maxLength' => 200, 'required' => true, 'verify' => 'verifyBuildingName'],
            'building_level' => ['type' => self::STR, 'maxLength' => 20, 'default' => ''],
            'direction_text' => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
            'lieutenant_name' => ['type' => self::STR, 'maxLength' => 150, 'default' => ''],
            'bonus_text' => ['type' => self::STR, 'maxLength' => 600, 'default' => ''],
            'followers_text' => ['type' => self::STR, 'maxLength' => 120, 'default' => ''],
            'description_bbcode' => ['type' => self::STR, 'default' => ''],
            'description_rendered' => ['type' => self::STR, 'default' => ''],
            'display_order' => ['type' => self::UINT, 'default' => 0],
            'created_date' => ['type' => self::UINT, 'default' => 0],
            'last_update' => ['type' => self::UINT, 'default' => 0],
        ];
        $structure->relations = [];

        return $structure;
    }
}
