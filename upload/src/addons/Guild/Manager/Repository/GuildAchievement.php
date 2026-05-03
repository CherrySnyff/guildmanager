<?php

namespace Guild\Manager\Repository;

use XF\Mvc\Entity\Repository;

/** Репозиторий достижений гильдии; доп. выборки поверх базового Finder. */
class GuildAchievement extends Repository
{
    public function findAchievementsForGuild(int $guildId)
    {
        return $this->finder('Guild\Manager:GuildAchievement')
            ->where('guild_id', $guildId)
            ->order(['display_order', 'achievement_id']);
    }
}
