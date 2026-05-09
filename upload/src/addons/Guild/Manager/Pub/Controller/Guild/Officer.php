<?php

namespace Guild\Manager\Pub\Controller\Guild;

use Guild\Manager\Service\Guild\GuildWorkflow;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/**
 * Офицеры: POST на один URL …/officer с полем gm_officer_op — избегает ошибок совпадения маршрутов add/remove/replace при ЧПУ.
 * Операции: add | remove | replace (лидер гильдии или manageGuildAny).
 */
class Officer extends AbstractGuildAction
{
    public function actionIndex(ParameterBag $params): AbstractReply
    {
        if (!$this->request->isPost()) {
            return $this->error('Это действие доступно только через POST.');
        }

        /** @var GuildWorkflow $workflow */
        $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');

        return $this->runMutation($params, function ($guild, $visitor, $guildRole) use ($workflow) {
            $op = strtolower(trim((string)$this->filter('gm_officer_op', 'str')));
            switch ($op) {
                case 'remove':
                    $raw = $this->request->get('officer_remove_ids', []);
                    if (!is_array($raw)) {
                        $raw = [];
                    }
                    $ids = array_values(array_unique(array_filter(array_map('\intval', $raw), static function (int $v): bool {
                        return $v > 0;
                    })));
                    $workflow->removeGuildOfficersByUserIds($guild, $visitor, $ids, $guildRole);
                    return;
                case 'replace':
                    $workflow->replaceGuildOfficer(
                        $guild,
                        $visitor,
                        $this->filter('from_user_id', 'uint'),
                        $this->filter('to_user_id', 'uint'),
                        $guildRole
                    );
                    return;
                case '':
                case 'add':
                    $workflow->appointOfficerByUserId(
                        $guild,
                        $visitor,
                        $this->filter('officer_user_id', 'uint'),
                        $guildRole
                    );
                    return;
                default:
                    throw new \XF\PrintableException('Неизвестная операция.');
            }
        });
    }

    /**
     * @param callable(\Guild\Manager\Entity\Guild, \XF\Entity\User, ?string):void $fn
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

        return $this->handlePrintableOrRedirect(
            function () use ($fn, $guild, $visitor, $guildRole) {
                $fn($guild, $visitor, $guildRole);
            },
            $guild,
            'description'
        );
    }
}
