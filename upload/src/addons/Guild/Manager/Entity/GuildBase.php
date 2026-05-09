<?php

namespace Guild\Manager\Entity;

use XF;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/** Гильдейская база (вкладка «Базы»). Таблица xf_guild_base. */
class GuildBase extends Entity
{
    protected function verifyBaseName(&$text): bool
    {
        $text = trim((string)$text);
        if ($text === '') {
            $this->error(XF::phrase('please_enter_valid_value'), 'base_name');
            return false;
        }

        return true;
    }

    protected function verifyBaseBbcode(&$text): bool
    {
        $text = (string)$text;

        return true;
    }

    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_guild_base';
        $structure->shortName = 'Guild\Manager:GuildBase';
        $structure->primaryKey = 'guild_base_id';
        $structure->columns = [
            'guild_base_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'guild_id' => ['type' => self::UINT, 'required' => true],
            'base_name' => ['type' => self::STR, 'maxLength' => 200, 'required' => true, 'verify' => 'verifyBaseName'],
            'base_bbcode' => ['type' => self::STR, 'default' => '', 'verify' => 'verifyBaseBbcode'],
            'base_rendered' => ['type' => self::STR, 'default' => ''],
            'display_order' => ['type' => self::UINT, 'default' => 0],
            'created_by_user_id' => ['type' => self::UINT, 'default' => 0],
            'created_date' => ['type' => self::UINT, 'default' => 0],
            'last_update' => ['type' => self::UINT, 'default' => 0],
        ];
        $structure->relations = [];

        return $structure;
    }
}
