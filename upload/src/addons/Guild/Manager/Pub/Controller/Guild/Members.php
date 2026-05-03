<?php

namespace Guild\Manager\Pub\Controller\Guild;

use Guild\Manager\Service\Guild\GuildWorkflow;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/** Сохранение структурированного блока участников (JSON gm_members_v2) или смежных действий вкладки. */
class Members extends AbstractGuildAction
{
    public function actionUpdate(ParameterBag $params): AbstractReply
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

        $memberUserIds = $this->request->get('member_user_id', []);
        $memberRoles = $this->request->get('member_role', []);
        $memberUsernames = $this->request->get('member_username', []);
        $hasStructuredPayload = isset($_POST['member_user_id']) || isset($_POST['member_role']) || isset($_POST['member_username']);

        if ($hasStructuredPayload) {
            $textForSave = $this->buildStructuredMembersPayload($memberUserIds, $memberUsernames, $memberRoles);
        } else {
            $text = $this->getMessageFromEditor();
            if ($text === '') {
                $text = $this->getBbCodeFromRequestFallbacks();
            }
            if (strlen($text) > 15000000) {
                return $this->error('Текст слишком длинный.');
            }
            $textForSave = $this->normalizeStandaloneAtMentions($text);
        }

        return $this->handlePrintableOrRedirect(
            function () use ($workflow, $guild, $visitor, $guildRole, $textForSave) {
                $workflow->updateMembersBlock(
                    $guild,
                    $visitor,
                    $textForSave,
                    $guildRole
                );
            },
            $guild,
            'members'
        );
    }

    protected function buildStructuredMembersPayload($rawUserIds, $rawUsernames, $rawRoles): string
    {
        $userIds = is_array($rawUserIds) ? array_values($rawUserIds) : [];
        $usernames = is_array($rawUsernames) ? array_values($rawUsernames) : [];
        $roles = is_array($rawRoles) ? array_values($rawRoles) : [];
        $count = max(count($userIds), count($usernames), count($roles));

        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $userId = (int)($userIds[$i] ?? 0);
            $username = trim((string)($usernames[$i] ?? ''));
            $role = trim((string)($roles[$i] ?? ''));
            if ($role !== '') {
                $role = mb_substr($role, 0, 60);
            }

            if ($userId <= 0 && $username !== '') {
                $user = $this->finder('XF:User')->where('username', $username)->fetchOne();
                if ($user) {
                    $userId = (int)$user->user_id;
                }
            }
            if ($userId <= 0) {
                continue;
            }

            $items[] = [
                'user_id' => $userId,
                'role' => $role,
            ];
        }

        $payload = [
            'format' => 'gm_members_v2',
            'items' => array_values($items),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
