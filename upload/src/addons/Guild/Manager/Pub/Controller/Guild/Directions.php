<?php

namespace Guild\Manager\Pub\Controller\Guild;

use Guild\Manager\Service\Guild\GuildWorkflow;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/** Сохранение направленностей через FocusManager и пересчёт уровня при необходимости. */
class Directions extends AbstractGuildAction
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
                $slots = [
                    1 => $this->filter('direction_1', 'str'),
                    2 => $this->filter('direction_2', 'str'),
                    3 => $this->filter('direction_3', 'str'),
                    4 => $this->filter('direction_4', 'str'),
                ];
                $workflow->setGuildFocuses($guild, $visitor, $slots, $guildRole);
            },
            $guild,
            'description'
        );
    }
}
