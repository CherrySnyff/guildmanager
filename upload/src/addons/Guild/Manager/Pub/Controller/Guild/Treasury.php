<?php

namespace Guild\Manager\Pub\Controller\Guild;

use Guild\Manager\Service\Guild\GuildWorkflow;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/** Обработка форм вкладки «Казна» (добавление/при админ-правах правка и удаление строк журнала). */
class Treasury extends AbstractGuildAction
{
    public function actionAdd(ParameterBag $params): AbstractReply
    {
        return $this->runMutation($params, 'treasury', function ($guild, $visitor, $guildRole) {
            /** @var GuildWorkflow $workflow */
            $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');
            $amount = $this->filter('amount', 'int');
            $character = $this->filter('character_name', 'str');
            $url = $this->filter('source_url', 'str');
            $reason = $this->filter('reason', 'str');
            if ($amount > 0) {
                $workflow->depositTreasury($guild, $visitor, $character, $url, $amount, $reason, $guildRole);
            } elseif ($amount < 0) {
                $workflow->withdrawTreasury($guild, $visitor, $character, $url, $amount, $reason, $guildRole);
            } else {
                throw new \XF\PrintableException('Сумма должна быть больше 0 или меньше 0.');
            }
        });
    }

    public function actionEdit(ParameterBag $params): AbstractReply
    {
        return $this->runMutation($params, 'treasury', function ($guild, $visitor, $guildRole) use ($params) {
            /** @var GuildWorkflow $workflow */
            $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');
            $entryId = (int)$params->get('entry_id', 0);
            if ($entryId <= 0) {
                throw new \XF\PrintableException('Запись не найдена.');
            }
            $amount = $this->filter('amount', 'int');
            $type = $amount > 0 ? 'income' : ($amount < 0 ? 'expense' : '');
            if ($type === '') {
                throw new \XF\PrintableException('Сумма должна быть больше 0 или меньше 0.');
            }
            $workflow->updateTreasuryLog(
                $guild,
                $visitor,
                $entryId,
                $this->filter('character_name', 'str'),
                $this->filter('source_url', 'str'),
                $type,
                $amount,
                $this->filter('reason', 'str'),
                $guildRole
            );
        });
    }

    public function actionDelete(ParameterBag $params): AbstractReply
    {
        return $this->runMutation($params, 'treasury', function ($guild, $visitor, $guildRole) use ($params) {
            /** @var GuildWorkflow $workflow */
            $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');
            $entryId = (int)$params->get('entry_id', 0);
            if ($entryId <= 0) {
                throw new \XF\PrintableException('Запись не найдена.');
            }
            $workflow->deleteTreasuryLog($guild, $visitor, $entryId, $guildRole);
        });
    }

    /**
     * Каркас POST-формы вкладки: CSRF, loadGuild, роль в гильдии; затем вызов $fn (работа через GuildWorkflow и т.д.).
     * Успех — редирект на карточку с вкладкой $tab; ошибки — AbstractGuildAction::handlePrintableOrRedirect.
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
