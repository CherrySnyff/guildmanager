<?php

namespace Guild\Manager\Pub\Controller\Guild;

use Guild\Manager\Service\Guild\GuildWorkflow;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/** POST сохранение BBCode описания гильдии (DescriptionManager). */
class Description extends AbstractGuildAction
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

        $text = $this->getMessageFromEditor();
        if ($text === '') {
            $text = $this->getBbCodeFromRequestFallbacks();
        }
        if (strlen($text) > 15000000) {
            return $this->error('Описание слишком длинное.');
        }

        return $this->handlePrintableOrRedirect(
            function () use ($workflow, $guild, $visitor, $guildRole, $text) {
                $workflow->updateDescription(
                    $guild,
                    $visitor,
                    $text,
                    '',
                    $guildRole
                );
            },
            $guild,
            'description'
        );
    }
}
