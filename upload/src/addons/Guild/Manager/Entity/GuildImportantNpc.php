<?php

namespace Guild\Manager\Entity;

use XF;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/** Карточка важного НПС во вкладке «Важные НПС». Таблица xf_guild_important_npc. */
class GuildImportantNpc extends Entity
{
    protected function verifyNpcName(&$text): bool
    {
        $text = trim((string)$text);
        if ($text === '') {
            $this->error(XF::phrase('please_enter_valid_value'), 'npc_name');
            return false;
        }

        return true;
    }

    protected function verifyNpcBbcode(&$text): bool
    {
        $text = (string)$text;
        if (trim($text) === '') {
            $this->error(XF::phrase('please_enter_valid_value'), 'npc_bbcode');
            return false;
        }

        return true;
    }

    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_guild_important_npc';
        $structure->shortName = 'Guild\Manager:GuildImportantNpc';
        $structure->primaryKey = 'important_npc_id';
        $structure->columns = [
            'important_npc_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'guild_id' => ['type' => self::UINT, 'required' => true],
            'npc_name' => ['type' => self::STR, 'maxLength' => 150, 'required' => true, 'verify' => 'verifyNpcName'],
            'npc_bbcode' => ['type' => self::STR, 'default' => '', 'verify' => 'verifyNpcBbcode'],
            'npc_rendered' => ['type' => self::STR, 'default' => ''],
            'display_order' => ['type' => self::UINT, 'default' => 0],
            'created_by_user_id' => ['type' => self::UINT, 'default' => 0],
            'created_date' => ['type' => self::UINT, 'default' => 0],
            'last_update' => ['type' => self::UINT, 'default' => 0],
        ];
        $structure->getters = [];
        $structure->relations = [];

        return $structure;
    }
}
