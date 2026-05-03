<?php

namespace Guild\Manager\Repository;

use XF\Mvc\Entity\Repository;

/** Журнал смен лидера xf_guild_leader_log при наличии. */
class GuildLeaderLog extends Repository
{
    public function findGuildLogs(int $guildId)
    {
        return $this->finder('Guild\Manager:GuildLeaderLog')
            ->where('guild_id', $guildId)
            ->order('change_date', 'DESC');
    }
}
