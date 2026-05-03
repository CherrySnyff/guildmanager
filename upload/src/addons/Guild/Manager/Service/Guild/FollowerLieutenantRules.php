<?php

namespace Guild\Manager\Service\Guild;

/**
 * Таблица уровня гильдии → последователи и лейтенанты (ТЗ вкладка «Последователи»).
 *
 * @return array<int, array{
 *   followers_range: string,
 *   max_follower_level: int,
 *   mid_lieutenant_count: int,
 *   mid_lieutenant_level: int,
 *   high_lieutenant_count: int,
 *   high_lieutenant_level: int
 * }>
 */
class FollowerLieutenantRules
{
    /**
     * @return array<int, array<string, int|string>>
     */
    public static function getRowsByGuildLevel(): array
    {
        return [
            1 => ['followers_range' => '1–2', 'max_follower_level' => 0, 'mid_lieutenant_count' => 0, 'mid_lieutenant_level' => 0, 'high_lieutenant_count' => 0, 'high_lieutenant_level' => 0],
            2 => ['followers_range' => '3–4', 'max_follower_level' => 0, 'mid_lieutenant_count' => 0, 'mid_lieutenant_level' => 0, 'high_lieutenant_count' => 0, 'high_lieutenant_level' => 0],
            3 => ['followers_range' => '5–6', 'max_follower_level' => 0, 'mid_lieutenant_count' => 1, 'mid_lieutenant_level' => 1, 'high_lieutenant_count' => 0, 'high_lieutenant_level' => 0],
            4 => ['followers_range' => '7–9', 'max_follower_level' => 0, 'mid_lieutenant_count' => 1, 'mid_lieutenant_level' => 1, 'high_lieutenant_count' => 0, 'high_lieutenant_level' => 0],
            5 => ['followers_range' => '10–13', 'max_follower_level' => 0, 'mid_lieutenant_count' => 1, 'mid_lieutenant_level' => 1, 'high_lieutenant_count' => 0, 'high_lieutenant_level' => 0],
            6 => ['followers_range' => '14–18', 'max_follower_level' => 1, 'mid_lieutenant_count' => 2, 'mid_lieutenant_level' => 2, 'high_lieutenant_count' => 0, 'high_lieutenant_level' => 0],
            7 => ['followers_range' => '19–27', 'max_follower_level' => 1, 'mid_lieutenant_count' => 2, 'mid_lieutenant_level' => 2, 'high_lieutenant_count' => 0, 'high_lieutenant_level' => 0],
            8 => ['followers_range' => '28–36', 'max_follower_level' => 1, 'mid_lieutenant_count' => 3, 'mid_lieutenant_level' => 2, 'high_lieutenant_count' => 0, 'high_lieutenant_level' => 3],
            9 => ['followers_range' => '37–53', 'max_follower_level' => 1, 'mid_lieutenant_count' => 4, 'mid_lieutenant_level' => 2, 'high_lieutenant_count' => 1, 'high_lieutenant_level' => 3],
            10 => ['followers_range' => '54–75', 'max_follower_level' => 2, 'mid_lieutenant_count' => 6, 'mid_lieutenant_level' => 3, 'high_lieutenant_count' => 1, 'high_lieutenant_level' => 4],
            11 => ['followers_range' => '76–99', 'max_follower_level' => 2, 'mid_lieutenant_count' => 8, 'mid_lieutenant_level' => 3, 'high_lieutenant_count' => 2, 'high_lieutenant_level' => 4],
            12 => ['followers_range' => '100–150', 'max_follower_level' => 2, 'mid_lieutenant_count' => 11, 'mid_lieutenant_level' => 3, 'high_lieutenant_count' => 4, 'high_lieutenant_level' => 5],
            13 => ['followers_range' => '151–215', 'max_follower_level' => 2, 'mid_lieutenant_count' => 16, 'mid_lieutenant_level' => 3, 'high_lieutenant_count' => 6, 'high_lieutenant_level' => 5],
            14 => ['followers_range' => '216–300', 'max_follower_level' => 3, 'mid_lieutenant_count' => 23, 'mid_lieutenant_level' => 4, 'high_lieutenant_count' => 7, 'high_lieutenant_level' => 6],
            15 => ['followers_range' => '301–425', 'max_follower_level' => 3, 'mid_lieutenant_count' => 31, 'mid_lieutenant_level' => 4, 'high_lieutenant_count' => 11, 'high_lieutenant_level' => 6],
            16 => ['followers_range' => '426–600', 'max_follower_level' => 3, 'mid_lieutenant_count' => 43, 'mid_lieutenant_level' => 4, 'high_lieutenant_count' => 17, 'high_lieutenant_level' => 7],
            17 => ['followers_range' => '601–850', 'max_follower_level' => 3, 'mid_lieutenant_count' => 61, 'mid_lieutenant_level' => 4, 'high_lieutenant_count' => 24, 'high_lieutenant_level' => 7],
            18 => ['followers_range' => '851–1200', 'max_follower_level' => 4, 'mid_lieutenant_count' => 86, 'mid_lieutenant_level' => 5, 'high_lieutenant_count' => 34, 'high_lieutenant_level' => 8],
            19 => ['followers_range' => '1201–1700', 'max_follower_level' => 4, 'mid_lieutenant_count' => 121, 'mid_lieutenant_level' => 5, 'high_lieutenant_count' => 49, 'high_lieutenant_level' => 8],
            20 => ['followers_range' => '1701–2400', 'max_follower_level' => 4, 'mid_lieutenant_count' => 171, 'mid_lieutenant_level' => 5, 'high_lieutenant_count' => 69, 'high_lieutenant_level' => 9],
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public static function getRowForGuildLevel(int $level): array
    {
        $level = max(1, min(20, $level));
        $rows = self::getRowsByGuildLevel();

        return $rows[$level];
    }

    /**
     * Фрагмент после числа последователей: макс. уровень НПС-последователей и лейтенанты по уровню гильдии.
     */
    public static function buildLieutenantSummaryFragmentHtml(int $guildLevel): string
    {
        $r = self::getRowForGuildLevel($guildLevel);
        $maxF = (int)$r['max_follower_level'];
        $midC = (int)$r['mid_lieutenant_count'];
        $midL = (int)$r['mid_lieutenant_level'];
        $highC = (int)$r['high_lieutenant_count'];
        $highL = (int)$r['high_lieutenant_level'];

        $s = ' <strong>' . $maxF . '</strong> уровня';
        if ($midC > 0 && $midL > 0) {
            $s .= '<br>Из них <span class="gm-lieutenant-mid"><strong>' . $midC . '</strong></span> лейтенантов <span class="gm-lieutenant-mid"><strong>' . $midL . '</strong></span> уровня';
            if ($highC > 0 && $highL > 0) {
                $s .= ' и <span class="gm-lieutenant-high"><strong>' . $highC . '</strong></span> лейтенантов <span class="gm-lieutenant-high"><strong>' . $highL . '</strong></span> уровня';
            }
        }
        return $s . '.';
    }

    public static function buildRulesTableHtml(): string
    {
        $rows = self::getRowsByGuildLevel();
        $html = '<table class="gm-followers-rules-table" cellspacing="0" cellpadding="6">';
        $html .= '<thead><tr>'
            . '<th>Ур.</th>'
            . '<th>Последователи</th>'
            . '<th>Макс. уровень последователей</th>'
            . '<th>Количество лейтенантов</th>'
            . '<th>Средний уровень лейтенантов</th>'
            . '<th>Высокий уровень лейтенантов</th>'
            . '</tr></thead><tbody>';

        foreach ($rows as $lvl => $r) {
            $midC = (int)$r['mid_lieutenant_count'];
            $highC = (int)$r['high_lieutenant_count'];
            $midL = (int)$r['mid_lieutenant_level'];
            $highL = (int)$r['high_lieutenant_level'];

            $col4 = '—';
            if ($midC > 0) {
                $col4 = '<span class="gm-lieutenant-mid">' . $midC . '</span>';
                if ($highC > 0) {
                    $col4 .= ' <span class="gm-lieutenant-high">(+' . $highC . ')</span>';
                }
            }

            $col5 = ($midL > 0) ? (string)$midL : '—';
            $col6 = ($highL > 0) ? (string)$highL : '—';

            $html .= '<tr>'
                . '<td><strong>' . $lvl . '</strong></td>'
                . '<td>' . htmlspecialchars((string)$r['followers_range'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . (int)$r['max_follower_level'] . '</td>'
                . '<td>' . $col4 . '</td>'
                . '<td>' . htmlspecialchars($col5, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars($col6, ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="gm-followers-rules-note">'
            . '<strong><em>Примечание:</em></strong> '
            . '<span class="gm-lieutenant-mid">красная цифра</span> в колонке количества показывает число лейтенантов среднего уровня, '
            . '<span class="gm-lieutenant-high">зелёная цифра в скобках</span> показывает дополнительное число лейтенантов высокого уровня. '
            . 'Например: на 12 уровне гильдии у вас будет '
            . '<span class="gm-lieutenant-mid">11 лейтенантов 3 уровня</span> и '
            . '<span class="gm-lieutenant-high">4 лейтенанта 5 уровня</span>.'
            . '</div>';

        return $html;
    }
}
