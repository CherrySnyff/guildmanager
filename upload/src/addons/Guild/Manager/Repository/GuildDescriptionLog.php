<?php

namespace Guild\Manager\Repository;

use XF\Mvc\Entity\Repository;

/** История текстов описаний (просмотр в админке / отладка при необходимости). */
class GuildDescriptionLog extends Repository
{
    public function findGuildLogs(int $guildId)
    {
        return $this->finder('Guild\Manager:GuildDescriptionLog')
            ->where('guild_id', $guildId)
            ->order('change_date', 'DESC');
    }
}
