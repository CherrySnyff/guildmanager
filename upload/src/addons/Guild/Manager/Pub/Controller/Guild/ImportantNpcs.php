<?php

namespace Guild\Manager\Pub\Controller\Guild;

use Guild\Manager\Service\Guild\GuildWorkflow;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/** CRUD записей вкладки «Важные НПС». */
class ImportantNpcs extends AbstractGuildAction
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

        $text = $this->getMessageFromEditor();
        if ($text === '') {
            $text = $this->getBbCodeFromRequestFallbacks();
        }
        $text = $this->normalizeStandaloneAtMentions($text);

        return $this->handlePrintableOrRedirect(
            function () use ($workflow, $guild, $visitor, $guildRole, $text) {
                $workflow->addImportantNpc(
                    $guild,
                    $visitor,
                    $this->filter('npc_name', 'str'),
                    $text,
                    $guildRole
                );
            },
            $guild,
            'important-npcs'
        );
    }

    public function actionEdit(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));

        $guild = $this->loadGuild($params);
        $visitor = \XF::visitor();
        if (!$visitor->user_id) {
            return $this->noPermission();
        }

        $npcId = (int)$params->get('npc_id', 0);
        if ($npcId <= 0) {
            return $this->error('Запись не найдена.');
        }

        $guildRole = $this->getGuildRole($guild, $visitor);
        /** @var GuildWorkflow $workflow */
        $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');

        $text = $this->getMessageFromEditor();
        if ($text === '') {
            $text = $this->getBbCodeFromRequestFallbacks();
        }
        $text = $this->normalizeStandaloneAtMentions($text);

        return $this->handlePrintableOrRedirect(
            function () use ($workflow, $guild, $visitor, $guildRole, $npcId, $text) {
                $workflow->updateImportantNpc(
                    $guild,
                    $visitor,
                    $npcId,
                    $this->filter('npc_name', 'str'),
                    $text,
                    $guildRole
                );
            },
            $guild,
            'important-npcs'
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

        $npcId = (int)$params->get('npc_id', 0);
        if ($npcId <= 0) {
            return $this->error('Запись не найдена.');
        }

        $guildRole = $this->getGuildRole($guild, $visitor);
        /** @var GuildWorkflow $workflow */
        $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');

        return $this->handlePrintableOrRedirect(
            function () use ($workflow, $guild, $visitor, $guildRole, $npcId) {
                $workflow->deleteImportantNpc($guild, $visitor, $npcId, $guildRole);
            },
            $guild,
            'important-npcs'
        );
    }
}
