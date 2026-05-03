<?php

namespace Guild\Manager\Repository;

use XF\Mvc\Entity\Repository;

/** Доступ к правилам уровней последователей xf_guild_level_rule (ACP / seed). */
class GuildLevelRule extends Repository
{
    public function findRuleForFollowers(int $followersTotal)
    {
        return $this->finder('Guild\Manager:GuildLevelRule')
            ->where('followers_min', '<=', $followersTotal)
            ->whereOr(
                [
                    ['followers_max', null],
                    ['followers_max', '>=', $followersTotal]
                ]
            )
            ->order('level', 'DESC');
    }
}
