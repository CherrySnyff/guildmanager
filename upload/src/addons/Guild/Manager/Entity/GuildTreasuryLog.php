<?php

namespace Guild\Manager\Entity;

use XF;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/** Операция казны (внос/снятие). Таблица xf_guild_treasury_log. */
class GuildTreasuryLog extends Entity
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

    protected function verifyReason(&$reason): bool
    {
        $reason = trim((string)$reason);
        if ($reason === '') {
            $this->error(XF::phrase('please_enter_valid_value'), 'reason');
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
        $structure->table = 'xf_guild_treasury_log';
        $structure->shortName = 'Guild\Manager:GuildTreasuryLog';
        $structure->primaryKey = 'treasury_log_id';
        $structure->columns = [
            'treasury_log_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'guild_id' => ['type' => self::UINT, 'required' => true],
            'character_name' => ['type' => self::STR, 'maxLength' => 100, 'default' => '', 'verify' => 'verifyCharacterName'],
            'source_url' => ['type' => self::STR, 'maxLength' => 500, 'default' => '', 'verify' => 'verifySourceUrl'],
            'amount' => ['type' => self::INT, 'default' => 0, 'verify' => 'verifyAmount'],
            'operation_type' => ['type' => self::STR, 'allowedValues' => ['deposit', 'withdraw'], 'default' => 'deposit'],
            'reason' => ['type' => self::STR, 'maxLength' => 255, 'default' => '', 'verify' => 'verifyReason'],
            'created_by_user_id' => ['type' => self::UINT, 'default' => 0],
            'created_date' => ['type' => self::UINT, 'default' => 0],
        ];
        $structure->getters = [];
        $structure->relations = [];

        return $structure;
    }
}
