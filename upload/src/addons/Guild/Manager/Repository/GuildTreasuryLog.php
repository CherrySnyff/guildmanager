<?php

namespace Guild\Manager\Repository;

use XF\Mvc\Entity\Repository;

/** Журнал операций казны. */
class GuildTreasuryLog extends Repository
{
    public function findGuildLogs(int $guildId)
    {
        return $this->finder('Guild\Manager:GuildTreasuryLog')
            ->where('guild_id', $guildId)
            ->order('created_date', 'DESC');
    }
}
