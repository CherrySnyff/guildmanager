<?php

namespace Guild\Manager\Repository;

use XF\Mvc\Entity\Repository;

/** Транспорт гильдии: выбор записей xf_guild_vehicle. */
class GuildVehicle extends Repository
{
    public function findGuildVehicles(int $guildId)
    {
        return $this->finder('Guild\Manager:GuildVehicle')
            ->where('guild_id', $guildId)
            ->order('display_order');
    }
}
