<?php

namespace Guild\Manager\Service\Guild;

use Guild\Manager\Entity\Guild;
use XF\Service\AbstractService;

/**
 * Кэши и производные числа для xf_guild, пересчитываемые из журналов и правил.
 *
 * Основное:
 * - recalculateFollowers / recalculateTreasury — суммы по логам;
 * - recalculateOrganizationLevel — уровень = min(по xf_guild_level_rule, потолок по getWorldRenownScore);
 * - organization_size_label — через ReputationDisplay::organizationSizeEnFromLevel от эффективного уровня;
 * - getMaxDirectionSlots — сколько слотов направленностей открыто на данном уровне;
 * - recalculateInfluenceCache — сырой кэш сумм репутации по регионам (без округления вкладочной таблицы).
 */
class Aggregator extends AbstractService
{
    /** Суммирует xf_guild_follower_log → followers_total на сущности. */
    public function recalculateFollowers(Guild $guild): int
    {
        $followersTotal = (int)$this->db()->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) FROM xf_guild_follower_log WHERE guild_id = ?',
            $guild->guild_id
        );

        $guild->followers_total = max(0, $followersTotal);
        return $guild->followers_total;
    }

    /** Суммирует xf_guild_treasury_log → treasury_balance. */
    public function recalculateTreasury(Guild $guild): int
    {
        $treasuryBalance = (int)$this->db()->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) FROM xf_guild_treasury_log WHERE guild_id = ?',
            $guild->guild_id
        );

        $guild->treasury_balance = $treasuryBalance;
        return $guild->treasury_balance;
    }

    /**
     * Выставляет organization_level и organization_size_label из правил последователей и капа известности.
     */
    public function recalculateOrganizationLevel(Guild $guild): int
    {
        $followersTotal = (int)$guild->followers_total;

        /** @var array{level:int|string, size_label:string}|false $followersRule */
        $followersRule = $this->db()->fetchRow(
            '
                SELECT level, size_label
                FROM xf_guild_level_rule
                WHERE followers_min <= ?
                    AND (followers_max IS NULL OR followers_max >= ?)
                ORDER BY level DESC
                LIMIT 1
            ',
            [$followersTotal, $followersTotal]
        );

        if ($followersRule) {
            $levelFromFollowers = (int)$followersRule['level'];
        } else {
            $levelFromFollowers = 1;
        }

        /** @var \Guild\Manager\Repository\GuildReputationLog $repRepo */
        $repRepo = $this->repository('Guild\Manager:GuildReputationLog');
        $worldRenownScore = $repRepo->getWorldRenownScore((int)$guild->guild_id);
        $capByRenown = $this->maxOrganizationLevelForWorldRenown($worldRenownScore);
        $effectiveLevel = min($levelFromFollowers, $capByRenown);

        $guild->organization_level = $effectiveLevel;
        $guild->organization_size_label = ReputationDisplay::organizationSizeEnFromLevel($effectiveLevel);

        return $guild->organization_level;
    }

    /**
     * Уровень только по сумме последователей (без капа по мировой известности).
     */
    public function getOrganizationLevelFromFollowersTotal(int $followersTotal): int
    {
        /** @var array{level:int|string}|false $rule */
        $rule = $this->db()->fetchRow(
            '
                SELECT level
                FROM xf_guild_level_rule
                WHERE followers_min <= ?
                    AND (followers_max IS NULL OR followers_max >= ?)
                ORDER BY level DESC
                LIMIT 1
            ',
            [$followersTotal, $followersTotal]
        );

        return $rule ? (int)$rule['level'] : 1;
    }

    /**
     * Потолок уровня по мировой известности (колонка «Мировая» → total в таблице влияния).
     * Пороги: &lt;5 — до ур. 5; 5–9 — до 15; 10–19 — до 19; ≥20 — до 20.
     */
    public function maxOrganizationLevelForWorldRenown(int $worldRenownScore): int
    {
        if ($worldRenownScore < 5) {
            return 5;
        }
        if ($worldRenownScore < 10) {
            return 15;
        }
        if ($worldRenownScore < 20) {
            return 19;
        }

        return 20;
    }

    /**
     * Минимальная мировая известность для снятия текущего потолка уровня (следующий порог).
     * null — потолок уже максимальный (20).
     */
    public function minWorldRenownForNextCapIncrease(int $currentMaxCap): ?int
    {
        if ($currentMaxCap >= 20) {
            return null;
        }
        if ($currentMaxCap === 5) {
            return 5;
        }
        if ($currentMaxCap === 15) {
            return 10;
        }
        if ($currentMaxCap === 19) {
            return 20;
        }

        return null;
    }

    /** Слотов направленностей доступно при уровне: 1 (1–5), 2 (6+), 3 (11+), 4 (16+). */
    public function getMaxDirectionSlots(int $organizationLevel): int
    {
        if ($organizationLevel >= 16) {
            return 4;
        }
        if ($organizationLevel >= 11) {
            return 3;
        }
        if ($organizationLevel >= 6) {
            return 2;
        }

        return 1;
    }

    /** Простые суммы amount по регионам в influence_cache (+ world как сумма трёх регионов). */
    public function recalculateInfluenceCache(Guild $guild): array
    {
        $rows = $this->db()->fetchAll(
            '
                SELECT region_key, COALESCE(SUM(amount), 0) AS total
                FROM xf_guild_reputation_log
                WHERE guild_id = ?
                GROUP BY region_key
            ',
            $guild->guild_id
        );

        $cache = [
            'aramidis' => 0,
            'union' => 0,
            'korzus' => 0,
            'world' => 0
        ];

        foreach ($rows as $row) {
            $region = $row['region_key'];
            $total = (int)$row['total'];
            if (array_key_exists($region, $cache)) {
                $cache[$region] = $total;
            }
        }

        $cache['world'] = $cache['aramidis'] + $cache['union'] + $cache['korzus'];
        $guild->influence_cache = $cache;

        return $cache;
    }

    /** Последователи → казна → уровень; опционально save(). */
    public function recalculateAll(Guild $guild, bool $save = true): void
    {
        $this->recalculateFollowers($guild);
        $this->recalculateTreasury($guild);
        $this->recalculateOrganizationLevel($guild);
        $guild->last_update = \XF::$time;

        if ($save && $guild->isChanged()) {
            $guild->save();
        }
    }
}
