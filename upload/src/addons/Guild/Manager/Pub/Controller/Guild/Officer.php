<?php

namespace Guild\Manager\Pub\Controller\Guild;

use Guild\Manager\Service\Guild\GuildWorkflow;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/** Назначение офицера по числовому user_id (лидер гильдии или manageGuildAny). */
class Officer extends AbstractGuildAction
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
                $workflow->appointOfficerByUserId(
                    $guild,
                    $visitor,
                    $this->filter('officer_user_id', 'uint'),
                    $guildRole
                );
            },
            $guild,
            'description'
        );
    }
}
