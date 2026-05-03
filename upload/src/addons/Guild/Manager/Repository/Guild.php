<?php

namespace Guild\Manager\Repository;

use XF\Mvc\Entity\Repository;

/** Общие запросы по сущности гильдии xf_guild (списки в админке и т.д.). */
class Guild extends Repository
{
    public function findGuildsForList()
    {
        return $this->finder('Guild\Manager:Guild')
            ->order('organization_level', 'DESC')
            ->order('followers_total', 'DESC');
    }
}
