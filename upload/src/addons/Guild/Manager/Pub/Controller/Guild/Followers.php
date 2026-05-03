<?php

namespace Guild\Manager\Pub\Controller\Guild;

use Guild\Manager\Service\Guild\GuildWorkflow;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/** Журнал последователей: добавление строк; последующий пересчёт счётчиков делает OperationManager/GuildWorkflow. */
class Followers extends AbstractGuildAction
{
    public function actionAdd(ParameterBag $params): AbstractReply
    {
        return $this->runMutation($params, 'followers', function ($guild, $visitor, $guildRole) {
            /** @var GuildWorkflow $workflow */
            $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');
            $amount = $this->filter('followers_amount', 'int');
            $character = $this->filter('character_name', 'str');
            $url = $this->filter('source_url', 'str');
            $date = $this->filter('date', 'str');
            if ($amount > 0) {
                $workflow->addFollowers($guild, $visitor, $character, $url, $amount, $date, $guildRole);
            } elseif ($amount < 0) {
                $workflow->removeFollowers($guild, $visitor, $character, $url, $amount, $date, $guildRole);
            } else {
                throw new \XF\PrintableException('Количество должно быть больше 0 или меньше 0.');
            }
        });
    }

    public function actionEdit(ParameterBag $params): AbstractReply
    {
        return $this->runMutation($params, 'followers', function ($guild, $visitor, $guildRole) use ($params) {
            /** @var GuildWorkflow $workflow */
            $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');
            $entryId = (int)$params->get('entry_id', 0);
            if ($entryId <= 0) {
                throw new \XF\PrintableException('Запись не найдена.');
            }
            $amount = $this->filter('followers_amount', 'int');
            $type = $amount > 0 ? 'gain' : ($amount < 0 ? 'loss' : '');
            if ($type === '') {
                throw new \XF\PrintableException('Количество должно быть больше 0 или меньше 0.');
            }
            $workflow->updateFollowerLog(
                $guild,
                $visitor,
                $entryId,
                $this->filter('character_name', 'str'),
                $this->filter('source_url', 'str'),
                $type,
                $amount,
                $this->filter('date', 'str'),
                $guildRole
            );
        });
    }

    public function actionDelete(ParameterBag $params): AbstractReply
    {
        return $this->runMutation($params, 'followers', function ($guild, $visitor, $guildRole) use ($params) {
            /** @var GuildWorkflow $workflow */
            $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');
            $entryId = (int)$params->get('entry_id', 0);
            if ($entryId <= 0) {
                throw new \XF\PrintableException('Запись не найдена.');
            }
            $workflow->deleteFollowerLog($guild, $visitor, $entryId, $guildRole);
        });
    }

    /**
     * Каркас POST для последователей/транспорта по тому же принципу, что Treasury::runMutation ($tab часто «followers»).
     *
     * @param callable(\Guild\Manager\Entity\Guild, \XF\Entity\User, ?string):void $fn ($guild, $visitor, $guildRole|null)
     */
    protected function runMutation(ParameterBag $params, string $tab, callable $fn): AbstractReply
    {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));

        $guild = $this->loadGuild($params);
        $visitor = \XF::visitor();
        if (!$visitor->user_id) {
            return $this->noPermission();
        }

        $guildRole = $this->getGuildRole($guild, $visitor);

        return $this->handlePrintableOrRedirect(
            function () use ($fn, $guild, $visitor, $guildRole) {
                $fn($guild, $visitor, $guildRole);
            },
            $guild,
            $tab
        );
    }
}
