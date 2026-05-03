<?php

namespace Guild\Manager\Pub\Controller\Guild;

use Guild\Manager\Service\Guild\GuildWorkflow;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/** Репутация по биомам: после сохранения обычно вызывается пересчёт уровня/кэша влияния (OperationManager). */
class Reputation extends AbstractGuildAction
{
    protected function resolveRepRegion(string $region): string
    {
        $allowed = ['aramidis', 'korzus', 'union'];

        return in_array($region, $allowed, true) ? $region : 'aramidis';
    }

    public function actionAdd(ParameterBag $params): AbstractReply
    {
        return $this->runMutation($params, function ($guild, $visitor, $guildRole) {
            /** @var GuildWorkflow $workflow */
            $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');
            $region = $this->resolveRepRegion($this->filter('region_key', 'str'));
            $amount = $this->filter('reputation_amount', 'int');
            $character = $this->filter('character_name', 'str');
            $url = $this->filter('source_url', 'str');
            $faction = $this->filter('faction_name', 'str');
            if ($amount > 0) {
                $workflow->addReputation($guild, $visitor, $region, $character, $url, $amount, $faction, $guildRole);
            } elseif ($amount < 0) {
                $workflow->removeReputation($guild, $visitor, $region, $character, $url, $amount, $faction, $guildRole);
            } else {
                throw new \XF\PrintableException('Репутация должна быть больше 0 или меньше 0.');
            }
        });
    }

    public function actionEdit(ParameterBag $params): AbstractReply
    {
        return $this->runMutation($params, function ($guild, $visitor, $guildRole) use ($params) {
            /** @var GuildWorkflow $workflow */
            $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');
            $entryId = (int)$params->get('entry_id', 0);
            if ($entryId <= 0) {
                throw new \XF\PrintableException('Запись не найдена.');
            }
            $region = $this->resolveRepRegion($this->filter('region_key', 'str'));
            $amount = $this->filter('reputation_amount', 'int');
            $type = $amount > 0 ? 'gain' : ($amount < 0 ? 'loss' : '');
            if ($type === '') {
                throw new \XF\PrintableException('Репутация должна быть больше 0 или меньше 0.');
            }
            $workflow->updateReputationLog(
                $guild,
                $visitor,
                $entryId,
                $region,
                $this->filter('character_name', 'str'),
                $this->filter('source_url', 'str'),
                $type,
                $amount,
                $this->filter('faction_name', 'str'),
                $guildRole
            );
        });
    }

    public function actionDelete(ParameterBag $params): AbstractReply
    {
        return $this->runMutation($params, function ($guild, $visitor, $guildRole) use ($params) {
            /** @var GuildWorkflow $workflow */
            $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');
            $entryId = (int)$params->get('entry_id', 0);
            if ($entryId <= 0) {
                throw new \XF\PrintableException('Запись не найдена.');
            }
            $workflow->deleteReputationLog($guild, $visitor, $entryId, $guildRole);
        });
    }

    /**
     * Как общий runMutation, но вкладка всегда «reputation», а при редиректе сохраняется регион «rep» из запроса
     * (совпадает с resolveRepRegion на карточке гильдии).
     *
     * @param callable(\Guild\Manager\Entity\Guild, \XF\Entity\User, ?string):void $fn ($guild, $visitor, $guildRole|null)
     */
    protected function runMutation(ParameterBag $params, callable $fn): AbstractReply
    {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));

        $guild = $this->loadGuild($params);
        $visitor = \XF::visitor();
        if (!$visitor->user_id) {
            return $this->noPermission();
        }

        $guildRole = $this->getGuildRole($guild, $visitor);
        $repRegion = $this->resolveRepRegion($this->filter('rep', 'str'));

        return $this->handlePrintableOrRedirect(
            function () use ($fn, $guild, $visitor, $guildRole) {
                $fn($guild, $visitor, $guildRole);
            },
            $guild,
            'reputation',
            $repRegion
        );
    }
}
