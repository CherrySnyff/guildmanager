<?php

namespace Guild\Manager\Repository;

use XF\Mvc\Entity\Repository;

/** Журнал последователей (чтение; запись через OperationManager). */
class GuildFollowerLog extends Repository
{
    public function findGuildLogs(int $guildId)
    {
        return $this->finder('Guild\Manager:GuildFollowerLog')
            ->where('guild_id', $guildId)
            ->order('created_date', 'DESC');
    }
}
