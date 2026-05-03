<?php

namespace Guild\Manager\Pub\Controller\Guild;

use Guild\Manager\Service\Guild\GuildWorkflow;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/** CRUD блока склада предметов. */
class Storage extends AbstractGuildAction
{
    public function actionAdd(ParameterBag $params): AbstractReply
    {
        return $this->runMutation($params, 'storage', function ($guild, $visitor, $guildRole) {
            /** @var GuildWorkflow $workflow */
            $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');
            $workflow->addStorageItem(
                $guild,
                $visitor,
                $this->filter('item_name', 'str'),
                $this->filter('item_description', 'str'),
                $this->filter('rarity', 'str'),
                $this->filter('source_url', 'str'),
                $guildRole
            );
        });
    }

    public function actionEdit(ParameterBag $params): AbstractReply
    {
        return $this->runMutation($params, 'storage', function ($guild, $visitor, $guildRole) use ($params) {
            /** @var GuildWorkflow $workflow */
            $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');
            $itemId = (int)$params->get('item_id', 0);
            if ($itemId <= 0) {
                throw new \XF\PrintableException('Запись не найдена.');
            }
            $workflow->updateStorageItem(
                $guild,
                $visitor,
                $itemId,
                $this->filter('item_name', 'str'),
                $this->filter('item_description', 'str'),
                $this->filter('rarity', 'str'),
                $this->filter('source_url', 'str'),
                $guildRole
            );
        });
    }

    public function actionDelete(ParameterBag $params): AbstractReply
    {
        return $this->runMutation($params, 'storage', function ($guild, $visitor, $guildRole) use ($params) {
            /** @var GuildWorkflow $workflow */
            $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');
            $itemId = (int)$params->get('item_id', 0);
            if ($itemId <= 0) {
                throw new \XF\PrintableException('Запись не найдена.');
            }
            $workflow->deleteStorageItem($guild, $visitor, $itemId, $guildRole);
        });
    }

    /**
     * Каркас POST склада; $tab должен быть «storage», чтобы после сохранения открывалась нужная вкладка.
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
