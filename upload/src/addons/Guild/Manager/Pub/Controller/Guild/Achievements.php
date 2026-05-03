<?php

namespace Guild\Manager\Pub\Controller\Guild;

use Guild\Manager\Service\Guild\GuildWorkflow;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/** Достижения гильдии (маршрут отдельной страницы; вкладка в меню может быть скрыта шаблоном). */
class Achievements extends AbstractGuildAction
{
    public function actionAdd(ParameterBag $params): AbstractReply
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
                $workflow->addAchievement(
                    $guild,
                    $visitor,
                    $this->filter('achievement_bbcode', 'str'),
                    $guildRole
                );
            },
            $guild,
            'achievements'
        );
    }

    public function actionDelete(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));

        $guild = $this->loadGuild($params);
        $visitor = \XF::visitor();
        if (!$visitor->user_id) {
            return $this->noPermission();
        }

        $achievementId = (int)$params->get('achievement_id', 0);
        if ($achievementId <= 0) {
            return $this->error('Запись не найдена.');
        }

        $guildRole = $this->getGuildRole($guild, $visitor);
        /** @var GuildWorkflow $workflow */
        $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');

        return $this->handlePrintableOrRedirect(
            function () use ($workflow, $guild, $visitor, $guildRole, $achievementId) {
                $workflow->deleteAchievement($guild, $visitor, $achievementId, $guildRole);
            },
            $guild,
            'achievements'
        );
    }
}
