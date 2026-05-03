<?php

namespace Guild\Manager\Finder;

use XF\Mvc\Entity\Finder;

/** Finder сущности GuildAchievement. */
class GuildAchievement extends Finder
{
    public function whereGuildId(int $guildId): self
    {
        $this->where('guild_id', $guildId);

        return $this;
    }
}
