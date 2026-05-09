<?php

namespace Guild\Manager\Pub\Controller\Guild;

use Guild\Manager\Entity\Guild as GuildEntity;
use Guild\Manager\Service\Guild\GuildWorkflow;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/** CRUD вкладки «Базы» и зданий на базе. */
class Bases extends AbstractGuildAction
{
    public function actionAdd(ParameterBag $params): AbstractReply
    {
        $guild = $this->loadGuild($params);
        $visitor = \XF::visitor();
        if (!$visitor->user_id) {
            return $this->noPermission();
        }
        $guildRole = $this->getGuildRole($guild, $visitor);
        /** @var GuildWorkflow $workflow */
        $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');

        $next = strtolower(trim((string)$this->filter('next_step', 'str')));
        $text = $this->collectBaseBbCode();
        $text = $this->normalizeStandaloneAtMentions($text);

        return $this->handlePrintableOrRedirectMut(
            function () use ($workflow, $guild, $visitor, $guildRole, $text, $next) {
                $base = $workflow->addGuildBase(
                    $guild,
                    $visitor,
                    trim((string)$this->filter('base_name', 'str')),
                    $text,
                    $guildRole
                );
                if ($next === 'building') {
                    return ['open_base' => (int)$base->guild_base_id, 'add_building' => 1];
                }
                return [];
            },
            $guild,
            'bases'
        );
    }

    public function actionEdit(ParameterBag $params): AbstractReply
    {
        $guild = $this->loadGuild($params);
        $visitor = \XF::visitor();
        if (!$visitor->user_id) {
            return $this->noPermission();
        }
        $baseId = (int)$params->get('base_id', 0);
        if ($baseId <= 0) {
            return $this->error('Запись не найдена.');
        }
        $guildRole = $this->getGuildRole($guild, $visitor);
        /** @var GuildWorkflow $workflow */
        $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');

        $next = strtolower(trim((string)$this->filter('next_step', 'str')));
        $text = $this->collectBaseBbCode();
        $text = $this->normalizeStandaloneAtMentions($text);

        return $this->handlePrintableOrRedirectMut(
            function () use ($workflow, $guild, $visitor, $guildRole, $baseId, $text, $next) {
                $workflow->updateGuildBase(
                    $guild,
                    $visitor,
                    $baseId,
                    trim((string)$this->filter('base_name', 'str')),
                    $text,
                    $guildRole
                );
                if ($next === 'building') {
                    return ['open_base' => $baseId, 'add_building' => 1];
                }
                return [];
            },
            $guild,
            'bases'
        );
    }

    public function actionDelete(ParameterBag $params): AbstractReply
    {
        $guild = $this->loadGuild($params);
        $visitor = \XF::visitor();
        if (!$visitor->user_id) {
            return $this->noPermission();
        }
        $baseId = (int)$params->get('base_id', 0);
        if ($baseId <= 0) {
            return $this->error('Запись не найдена.');
        }
        $guildRole = $this->getGuildRole($guild, $visitor);
        /** @var GuildWorkflow $workflow */
        $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');

        return $this->handlePrintableOrRedirectMut(
            function () use ($workflow, $guild, $visitor, $guildRole, $baseId) {
                $workflow->deleteGuildBase($guild, $visitor, $baseId, $guildRole);
                return [];
            },
            $guild,
            'bases'
        );
    }

    /** POST .../bases/{id}/building-add (действие после сопоставления маршрута базы). */
    public function actionBuildingAdd(ParameterBag $params): AbstractReply
    {
        $guild = $this->loadGuild($params);
        $visitor = \XF::visitor();
        if (!$visitor->user_id) {
            return $this->noPermission();
        }
        $baseId = (int)$params->get('base_id', 0);
        if ($baseId <= 0) {
            return $this->error('База не найдена.');
        }
        $guildRole = $this->getGuildRole($guild, $visitor);
        /** @var GuildWorkflow $workflow */
        $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');

        $desc = $this->collectBuildingBbCode();
        $desc = $this->normalizeStandaloneAtMentions($desc);

        return $this->handlePrintableOrRedirectMut(
            function () use ($workflow, $guild, $visitor, $guildRole, $baseId, $desc) {
                $workflow->addGuildBaseBuilding(
                    $guild,
                    $visitor,
                    $baseId,
                    trim((string)$this->filter('building_name', 'str')),
                    trim((string)$this->filter('building_level', 'str')),
                    trim((string)$this->filter('direction_text', 'str')),
                    trim((string)$this->filter('lieutenant_name', 'str')),
                    trim((string)$this->filter('bonus_text', 'str')),
                    trim((string)$this->filter('followers_text', 'str')),
                    $desc,
                    $guildRole
                );
                return ['open_base' => $baseId];
            },
            $guild,
            'bases'
        );
    }

    /** POST .../bases/{bid}/building/{id}/building-edit */
    public function actionBuildingEdit(ParameterBag $params): AbstractReply
    {
        $guild = $this->loadGuild($params);
        $visitor = \XF::visitor();
        if (!$visitor->user_id) {
            return $this->noPermission();
        }
        $baseId = (int)$params->get('base_id', 0);
        $buildingId = (int)$params->get('building_id', 0);
        if ($baseId <= 0 || $buildingId <= 0) {
            return $this->error('Запись не найдена.');
        }
        $guildRole = $this->getGuildRole($guild, $visitor);
        /** @var GuildWorkflow $workflow */
        $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');

        $desc = $this->collectBuildingBbCode();
        $desc = $this->normalizeStandaloneAtMentions($desc);

        return $this->handlePrintableOrRedirectMut(
            function () use ($workflow, $guild, $visitor, $guildRole, $baseId, $buildingId, $desc) {
                $workflow->updateGuildBaseBuilding(
                    $guild,
                    $visitor,
                    $baseId,
                    $buildingId,
                    trim((string)$this->filter('building_name', 'str')),
                    trim((string)$this->filter('building_level', 'str')),
                    trim((string)$this->filter('direction_text', 'str')),
                    trim((string)$this->filter('lieutenant_name', 'str')),
                    trim((string)$this->filter('bonus_text', 'str')),
                    trim((string)$this->filter('followers_text', 'str')),
                    $desc,
                    $guildRole
                );
                return ['open_base' => $baseId];
            },
            $guild,
            'bases'
        );
    }

    /** POST .../bases/{bid}/building/{id}/building-delete */
    public function actionBuildingDelete(ParameterBag $params): AbstractReply
    {
        $guild = $this->loadGuild($params);
        $visitor = \XF::visitor();
        if (!$visitor->user_id) {
            return $this->noPermission();
        }
        $baseId = (int)$params->get('base_id', 0);
        $buildingId = (int)$params->get('building_id', 0);
        if ($baseId <= 0 || $buildingId <= 0) {
            return $this->error('Запись не найдена.');
        }
        $guildRole = $this->getGuildRole($guild, $visitor);
        /** @var GuildWorkflow $workflow */
        $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');

        return $this->handlePrintableOrRedirectMut(
            function () use ($workflow, $guild, $visitor, $guildRole, $baseId, $buildingId) {
                $workflow->deleteGuildBaseBuilding($guild, $visitor, $baseId, $buildingId, $guildRole);
                return ['open_base' => $baseId];
            },
            $guild,
            'bases'
        );
    }

    protected function collectBaseBbCode(): string
    {
        $text = $this->getMessageFromEditor();
        if ($text === '') {
            $text = $this->getBbCodeFromRequestFallbacks();
        }

        return $text;
    }

    protected function collectBuildingBbCode(): string
    {
        $editor = $this->plugin('XF:Editor');
        if (is_object($editor) && method_exists($editor, 'fromInput')) {
            $v = $editor->fromInput('building_message');
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }

        $req = $this->request->get('building_message', '');
        if (is_string($req) && $req !== '') {
            return $req;
        }
        if (isset($_POST['building_message']) && is_string($_POST['building_message'])) {
            return $_POST['building_message'];
        }

        return '';
    }

    /**
     * @param callable():array<string,int> $fn возвращает доп. query после успеха (open_base, add_building)
     */
    protected function handlePrintableOrRedirectMut(
        callable $fn,
        GuildEntity $guild,
        string $tab
    ): AbstractReply {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));

        $extras = [];
        try {
            $extras = $fn();
        } catch (\XF\PrintableException $e) {
            return $this->error($e->getMessage());
        } catch (\XF\Mvc\Entity\Exception $e) {
            $details = $this->extractEntityErrorMessage($e);
            return $this->error($details !== '' ? $details : 'Проверьте корректность заполнения полей.');
        } catch (\XF\Mvc\Reply\Exception $e) {
            throw $e;
        } catch (\Throwable $e) {
            error_log('Guild bases mutation error [' . get_class($e) . ']: ' . $e->getMessage());
            if ($this->app->config('debug')) {
                return $this->error($e->getMessage() . ' [' . get_class($e) . ']');
            }
            return $this->error('Не удалось сохранить данные. Попробуйте ещё раз.');
        }

        if (!is_array($extras)) {
            $extras = [];
        }

        return $this->redirectToGuild($guild, $tab, 'aramidis', $extras);
    }
}
