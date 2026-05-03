<?php

namespace Guild\Manager\Pub\Controller\Guild;

use Guild\Manager\Service\Guild\GuildWorkflow;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/** Управление строками транспорта внутри вкладки «Последователи». */
class Transport extends AbstractGuildAction
{
    public function actionAdd(ParameterBag $params): AbstractReply
    {
        return $this->runMutation($params, 'followers', function ($guild, $visitor, $guildRole) {
            /** @var GuildWorkflow $workflow */
            $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');
            $workflow->addVehicle(
                $guild,
                $visitor,
                $this->filter('transport_name', 'str'),
                $this->filter('transport_status', 'str'),
                $guildRole
            );
        });
    }

    public function actionEdit(ParameterBag $params): AbstractReply
    {
        return $this->runMutation($params, 'followers', function ($guild, $visitor, $guildRole) use ($params) {
            /** @var GuildWorkflow $workflow */
            $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');
            $vehicleId = (int)$params->get('vehicle_id', 0);
            if ($vehicleId <= 0) {
                throw new \XF\PrintableException('Запись не найдена.');
            }
            $workflow->updateVehicle(
                $guild,
                $visitor,
                $vehicleId,
                $this->filter('transport_name', 'str'),
                $this->filter('transport_status', 'str'),
                $guildRole
            );
        });
    }

    public function actionDelete(ParameterBag $params): AbstractReply
    {
        return $this->runMutation($params, 'followers', function ($guild, $visitor, $guildRole) use ($params) {
            /** @var GuildWorkflow $workflow */
            $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');
            $vehicleId = (int)$params->get('vehicle_id', 0);
            if ($vehicleId <= 0) {
                throw new \XF\PrintableException('Запись не найдена.');
            }
            $workflow->deleteVehicle($guild, $visitor, $vehicleId, $guildRole);
        });
    }

    /**
     * Тот же каркас, что у Followers; редирект на вкладку $tab («followers» — транспорт показывается там же).
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
