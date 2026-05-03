<?php

namespace Guild\Manager\Pub\Controller\Guild;

use Guild\Manager\Service\Guild\PermissionGuard;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/** Поиск пользователей XenForо для AJAX-полей (лидер, участники) — возврат через FindUsersJson. */
class FindUsers extends AbstractGuildAction
{
    public function actionIndex(ParameterBag $params): AbstractReply
    {
        $guild = $this->loadGuild($params);
        $visitor = \XF::visitor();
        if (!$visitor->user_id) {
            return $this->noPermission();
        }

        $guildRole = $this->getGuildRole($guild, $visitor);
        /** @var PermissionGuard $guard */
        $guard = $this->service('Guild\Manager:Guild\PermissionGuard');
        if (!$guard->canSearchLeaderUsers($guild, $visitor, $guildRole)) {
            return $this->noPermission();
        }

        $q = trim($this->filter('q', 'str'));
        if (strlen($q) < 2) {
            $this->setResponseType('json');

            return $this->view('Guild\Manager:FindUsersJson', '', ['results' => []]);
        }

        $like = $this->app->db()->escapeLike($q, '?%');
        $users = $this->finder('XF:User')
            ->where('username', 'like', $like)
            ->where('user_state', 'valid')
            ->order('username')
            ->limit(15)
            ->fetch();

        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'avatar' => $user->getAvatarUrl('s'),
            ];
        }

        $this->setResponseType('json');

        return $this->view('Guild\Manager:FindUsersJson', '', ['results' => $results]);
    }
}
