<?php

namespace Guild\Manager\Repository;

use XF\Mvc\Entity\Repository;

/**
 * Запросы и агрегаты журнала репутации xf_guild_reputation_log для UI и Aggregator.
 *
 * Ключевое: таблица влияния по биомам (getInfluenceTable), мировая «Общая» / getWorldRenownScore —
 * именно они должны быть согласованы с капом уровня в Aggregator::maxOrganizationLevelForWorldRenown.
 */
class GuildReputationLog extends Repository
{
    public function findGuildLogs(int $guildId)
    {
        return $this->finder('Guild\Manager:GuildReputationLog')
            ->where('guild_id', $guildId)
            ->order('created_date', 'DESC');
    }

    public function findGuildLogsForRegion(int $guildId, string $regionKey)
    {
        return $this->finder('Guild\Manager:GuildReputationLog')
            ->where('guild_id', $guildId)
            ->where('region_key', $regionKey)
            ->order('created_date', 'DESC');
    }

    /**
     * То же округление сумм региона (/10 floor), что в таблице влияния на вкладке «Репутация».
     *
     * @return array{neg:int, pos:int}
     */
    public function fetchRegionReputationFlooredSums(int $guildId, string $regionKey): array
    {
        $raw = $this->db()->fetchRow(
            '
                SELECT
                    COALESCE(SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END), 0) AS neg_sum,
                    COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) AS pos_sum
                FROM xf_guild_reputation_log
                WHERE guild_id = ? AND region_key = ?
            ',
            [$guildId, $regionKey]
        );

        $neg = (int)floor(((float)$raw['neg_sum']) / 10);
        $pos = (int)floor(((float)$raw['pos_sum']) / 10);

        return ['neg' => $neg, 'pos' => $pos];
    }

    /**
     * «Мировая» сумма как в блоке биомов: сумма модулей отрицательной и положительной мировой (после свёртки по регионам как в таблице).
     */
    public function getWorldRenownScore(int $guildId): int
    {
        $regions = ['aramidis', 'korzus', 'union'];
        $worldNeg = 0;
        $worldPos = 0;
        foreach ($regions as $regionKey) {
            $s = $this->fetchRegionReputationFlooredSums($guildId, $regionKey);
            $worldNeg += $s['neg'];
            $worldPos += $s['pos'];
        }

        return abs($worldNeg) + $worldPos;
    }

    /**
     * @return array<string, array{label: string, negative: int, positive: int, total: int}>
     */
    public function getInfluenceTable(int $guildId): array
    {
        $labels = [
            'aramidis' => 'Арамидис',
            'korzus' => 'Корзус',
            'union' => 'Юнион',
        ];

        $rows = [];
        $worldNeg = 0;
        $worldPos = 0;

        foreach ($labels as $key => $label) {
            $sums = $this->fetchRegionReputationFlooredSums($guildId, $key);
            $neg = $sums['neg'];
            $pos = $sums['pos'];
            $rows[$key] = [
                'label' => $label,
                'negative' => $neg,
                'positive' => $pos,
                'total' => abs($neg) + $pos,
            ];
            $worldNeg += $neg;
            $worldPos += $pos;
        }

        $rows['world'] = [
            'label' => 'Мировая',
            'negative' => $worldNeg,
            'positive' => $worldPos,
            'total' => abs($worldNeg) + $worldPos,
        ];

        return $rows;
    }

    /**
     * @return list<array{display_name: string, faction_key: string, total: int, relation: string, relation_class: string, relation_tooltip: string}>
     */
    public function getFactionAggregatesForRegion(int $guildId, string $regionKey): array
    {
        $rows = $this->db()->fetchAll(
            '
                SELECT
                    LOWER(TRIM(faction_name)) AS fn,
                    MIN(faction_name) AS display_name,
                    SUM(amount) AS total
                FROM xf_guild_reputation_log
                WHERE guild_id = ? AND region_key = ?
                GROUP BY LOWER(TRIM(faction_name))
                ORDER BY MIN(faction_name) ASC
            ',
            [$guildId, $regionKey]
        );

        $out = [];
        foreach ($rows as $row) {
            $total = (int)$row['total'];
            $relationClass = \Guild\Manager\Service\Guild\ReputationDisplay::relationClass($total);
            $out[] = [
                'display_name' => (string)$row['display_name'],
                'faction_key' => (string)$row['fn'],
                'total' => $total,
                'relation' => mb_strtoupper(\Guild\Manager\Service\Guild\ReputationDisplay::relationLabel($total)),
                'relation_class' => $relationClass,
                'relation_tooltip' => \Guild\Manager\Service\Guild\ReputationDisplay::relationTooltipByClass($relationClass),
            ];
        }

        return $out;
    }
}
