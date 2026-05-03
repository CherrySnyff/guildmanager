<?php

namespace Guild\Manager\Repository;

use XF\Mvc\Entity\Repository;

/** Выбор предметов склада по guild_id для вкладки «Склад». */
class GuildStorage extends Repository
{
    public function findStorageForGuild(int $guildId)
    {
        return $this->finder('Guild\Manager:GuildStorage')
            ->where('guild_id', $guildId)
            ->order('storage_id');
    }
}
