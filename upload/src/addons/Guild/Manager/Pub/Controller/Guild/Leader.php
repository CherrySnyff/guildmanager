<?php

namespace Guild\Manager\Pub\Controller\Guild;

use Guild\Manager\Service\Guild\GuildWorkflow;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/** Смена лидера гильдии (Workflow + проверки PermissionGuard). */
class Leader extends AbstractGuildAction
{
    public function actionIndex(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));

        $guild = $this->loadGuild($params);
        $visitor = \XF::visitor();
        if (!$visitor->user_id) {
            return $this->noPermission();
        }

        $guildRole = $this->getGuildRole($guild, $visitor);
        /** @var GuildWorkflow $workflow */
        $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');

        return $this->handlePrintableOrRedirect(
            function () use ($workflow, $guild, $visitor, $guildRole) {
                $title = trim((string)$this->filter('guild_title', 'str'));
                if ($title !== '') {
                    if (!$visitor->hasPermission('guild_manager', 'editGuildTitleAny')) {
                        throw new \XF\PrintableException(\XF::phrase('no_permission'));
                    }
                    if (mb_strlen($title) > 100) {
                        throw new \XF\PrintableException('Название гильдии не должно превышать 100 символов.');
                    }
                    $guild->title = $title;
                    $guild->last_update = \XF::$time;
                    $guild->save();
                }

                $workflow->updateLeaderByUserId(
                    $guild,
                    $visitor,
                    $this->filter('leader_user_id', 'uint'),
                    $guildRole
                );
            },
            $guild,
            'description'
        );
    }
}
