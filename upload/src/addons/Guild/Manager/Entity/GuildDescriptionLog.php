<?php

namespace Guild\Manager\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/** История правок текста описания гильдии. Таблица xf_guild_description_log. */
class GuildDescriptionLog extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_guild_description_log';
        $structure->shortName = 'Guild\Manager:GuildDescriptionLog';
        $structure->primaryKey = 'description_log_id';
        $structure->columns = [
            'description_log_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'guild_id' => ['type' => self::UINT, 'required' => true],
            'old_description' => ['type' => self::STR, 'default' => '', 'maxLength' => 16777215],
            'new_description' => ['type' => self::STR, 'default' => '', 'maxLength' => 16777215],
            'changed_by_user_id' => ['type' => self::UINT, 'default' => 0],
            'change_date' => ['type' => self::UINT, 'default' => 0],
            'change_note' => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
        ];
        $structure->getters = [];
        $structure->relations = [];

        return $structure;
    }
}
