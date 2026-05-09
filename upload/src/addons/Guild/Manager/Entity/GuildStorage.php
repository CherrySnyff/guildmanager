<?php

namespace Guild\Manager\Entity;

use XF;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/** Предмет склада вкладки «Склад». Таблица xf_guild_storage. */
class GuildStorage extends Entity
{
    protected function verifyUrlField(string $field, &$url): bool
    {
        $url = trim((string)$url);
        if ($url === '') {
            return true;
        }

        if (strpos($url, 'https://') !== 0 || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error(XF::phrase('please_enter_valid_url'), $field);
            return false;
        }

        return true;
    }

    protected function verifyItemName(&$name): bool
    {
        $name = trim((string)$name);
        if ($name === '') {
            $this->error(XF::phrase('please_enter_valid_value'), 'item_name');
            return false;
        }

        return true;
    }

    protected function verifySourceUrl(&$url): bool
    {
        return $this->verifyUrlField('source_url', $url);
    }

    protected function verifyItemUrl(&$url): bool
    {
        return $this->verifyUrlField('item_url', $url);
    }

    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_guild_storage';
        $structure->shortName = 'Guild\Manager:GuildStorage';
        $structure->primaryKey = 'storage_id';
        $structure->columns = [
            'storage_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'guild_id' => ['type' => self::UINT, 'required' => true],
            'item_name' => ['type' => self::STR, 'maxLength' => 200, 'default' => '', 'verify' => 'verifyItemName'],
            'item_description' => ['type' => self::STR, 'default' => ''],
            'rarity' => ['type' => self::STR, 'allowedValues' => ['common', 'uncommon', 'rare', 'unique'], 'default' => 'common'],
            'item_url' => ['type' => self::STR, 'maxLength' => 500, 'default' => '', 'verify' => 'verifyItemUrl'],
            'source_url' => ['type' => self::STR, 'maxLength' => 500, 'default' => '', 'verify' => 'verifySourceUrl'],
            'created_by_user_id' => ['type' => self::UINT, 'default' => 0],
            'created_date' => ['type' => self::UINT, 'default' => 0],
        ];
        $structure->getters = [];
        $structure->relations = [];

        return $structure;
    }
}
