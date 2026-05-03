<?php

namespace Guild\Manager\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Сущность гильдии (таблица xf_guild) — главная запись, к которой привязаны все остальные xf_guild_*.
 *
 * Основное по полям:
 * - organization_level — эффективный уровень (min: уровень по последователям из xf_guild_level_rule и кап по мировой известности).
 * - organization_size_label — EN-ярлык размера (Small/Medium/Large/Legendary), из него считают RU-подпись на витрине.
 * - followers_total, treasury_balance — кэшированные суммы журналов, пересчитываются Aggregator.
 * - influence_cache — кэш агрегатов вкладки «Репутация», обновляется при мутации репутации.
 * - members_bbcode — либо JSON структуры участников gm_members_v2, либо legacy BBCode.
 */
class Guild extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_guild';
        $structure->shortName = 'Guild\Manager:Guild';
        $structure->primaryKey = 'guild_id';
        $structure->columns = [
            'guild_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'node_id' => ['type' => self::UINT, 'default' => 0],
            'thread_id' => ['type' => self::UINT, 'default' => 0],
            'title' => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
            'description' => ['type' => self::STR, 'default' => '', 'maxLength' => 16777215],
            'description_rendered' => ['type' => self::STR, 'default' => '', 'maxLength' => 16777215],
            'description_update_date' => ['type' => self::UINT, 'default' => 0],
            'description_update_user_id' => ['type' => self::UINT, 'default' => 0],
            'leader_user_id' => ['type' => self::UINT, 'default' => 0],
            'leader_username' => ['type' => self::STR, 'maxLength' => 100, 'default' => ''],
            'organization_level' => ['type' => self::UINT, 'max' => 20, 'default' => 1],
            'organization_size_label' => ['type' => self::STR, 'maxLength' => 50, 'default' => 'Small'],
            'member_count' => ['type' => self::UINT, 'default' => 0],
            'followers_total' => ['type' => self::UINT, 'default' => 0],
            'treasury_balance' => ['type' => self::INT, 'default' => 0],
            'influence_cache' => ['type' => self::JSON_ARRAY, 'default' => []],
            'created_date' => ['type' => self::UINT, 'default' => 0],
            'last_update' => ['type' => self::UINT, 'default' => 0],
            'guild_state' => ['type' => self::STR, 'allowedValues' => ['active', 'archived'], 'default' => 'active'],
            'members_bbcode' => ['type' => self::STR, 'default' => '', 'maxLength' => 16777215],
            'members_bbcode_rendered' => ['type' => self::STR, 'default' => '', 'maxLength' => 16777215],
        ];
        $structure->getters = [];
        $structure->relations = [];

        return $structure;
    }
}
