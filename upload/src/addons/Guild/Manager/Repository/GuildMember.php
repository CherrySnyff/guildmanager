<?php

namespace Guild\Manager\Repository;

use XF\Mvc\Entity\Repository;

/** Запросы по участникам и приглашениям xf_guild_member. */
class GuildMember extends Repository
{
    public function findGuildMember(int $guildId, int $userId)
    {
        return $this->finder('Guild\Manager:GuildMember')
            ->where('guild_id', $guildId)
            ->where('user_id', $userId);
    }

    public function findGuildMembers(int $guildId)
    {
        return $this->finder('Guild\Manager:GuildMember')
            ->where('guild_id', $guildId)
            ->order('joined_date');
    }

    /** Активные офицеры (роль officer, не лидер по полю гильдии — лидер отдельно). */
    public function findActiveOfficersForGuild(int $guildId)
    {
        return $this->finder('Guild\Manager:GuildMember')
            ->where('guild_id', $guildId)
            ->where('member_state', 'active')
            ->where('role', 'officer')
            ->order('username');
    }
}
