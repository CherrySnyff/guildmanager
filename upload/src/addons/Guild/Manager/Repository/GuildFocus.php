<?php

namespace Guild\Manager\Repository;

use XF\Mvc\Entity\Repository;

/** Направленности xf_guild_focus (обёртка над Finder при необходимости). */
class GuildFocus extends Repository
{
    public function findGuildFocuses(int $guildId)
    {
        return $this->finder('Guild\Manager:GuildFocus')
            ->where('guild_id', $guildId)
            ->order('display_order');
    }
}
