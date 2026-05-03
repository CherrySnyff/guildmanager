<?php

namespace Guild\Manager\Entity;

use XF;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/** Строка репутации по региону и фракции. Источник для таблиц влияния и getWorldRenownScore. xf_guild_reputation_log. */
class GuildReputationLog extends Entity
{
    protected function verifyCharacterName(&$name): bool
    {
        $name = trim((string)$name);
        if ($name === '') {
            $this->error(XF::phrase('please_enter_valid_value'), 'character_name');
            return false;
        }

        return true;
    }

    protected function verifyFactionName(&$name): bool
    {
        $name = trim((string)$name);
        if ($name === '') {
            $this->error(XF::phrase('please_enter_valid_value'), 'faction_name');
            return false;
        }

        return true;
    }

    protected function verifySourceUrl(&$url): bool
    {
        $url = trim((string)$url);
        if ($url === '') {
            $this->error(XF::phrase('please_enter_valid_url'), 'source_url');
            return false;
        }

        if (strpos($url, 'https://') !== 0 || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error(XF::phrase('please_enter_valid_url'), 'source_url');
            return false;
        }

        return true;
    }

    protected function verifyAmount(&$amount): bool
    {
        $amount = (int)$amount;

        if ($amount === 0) {
            $this->error('Amount cannot be zero.', 'amount');
            return false;
        }

        return true;
    }

    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_guild_reputation_log';
        $structure->shortName = 'Guild\Manager:GuildReputationLog';
        $structure->primaryKey = 'reputation_log_id';
        $structure->columns = [
            'reputation_log_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'guild_id' => ['type' => self::UINT, 'required' => true],
            'region_key' => ['type' => self::STR, 'allowedValues' => ['aramidis', 'union', 'korzus'], 'default' => 'aramidis'],
            'character_name' => ['type' => self::STR, 'maxLength' => 100, 'default' => '', 'verify' => 'verifyCharacterName'],
            'source_url' => ['type' => self::STR, 'maxLength' => 500, 'default' => '', 'verify' => 'verifySourceUrl'],
            'amount' => ['type' => self::INT, 'default' => 0, 'verify' => 'verifyAmount'],
            'operation_type' => ['type' => self::STR, 'allowedValues' => ['gain', 'loss'], 'default' => 'gain'],
            'faction_name' => ['type' => self::STR, 'maxLength' => 150, 'default' => '', 'verify' => 'verifyFactionName'],
            'created_by_user_id' => ['type' => self::UINT, 'default' => 0],
            'created_date' => ['type' => self::UINT, 'default' => 0],
        ];
        $structure->getters = [];
        $structure->relations = [];

        return $structure;
    }
}
