<?php

namespace Guild\Manager\Admin\Controller;

use Guild\Manager\Entity\Guild as GuildEntity;
use Guild\Manager\Helper\BbCodeContent;
use Guild\Manager\Service\Guild\PermissionPreset;
use XF\Admin\Controller\AbstractController;
use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\PrintableException;

/**
 * ACP: список гильдий, архивирование, блоковые операции (требует права admin guildManager).
 *
 * Публичное отображение пользователям — см. Pub\Controller\Guild.
 */
class Guild extends AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('guildManager');
    }

    public function actionIndex(): AbstractReply
    {
        $op = (string) $this->filter('gmo', 'str');
        $guildId = (int) $this->filter('guild_id', 'uint');
        if ($guildId > 0 && $op !== '') {
            $pb = new ParameterBag(['guild_id' => $guildId]);
            if ($op === 'setState') {
                return $this->actionSetState($pb);
            }
            if ($op === 'edit') {
                return $this->actionEdit($pb);
            }

            return $this->notFound();
        }

        return $this->renderGuildList();
    }

    protected function buildGuildAcpActionUrl(string $op, int $guildId): string
    {
        return $this->buildLink('guild-manager', null, [
            'gmo' => $op,
            'guild_id' => $guildId,
        ]);
    }

    protected function renderGuildList(): AbstractReply
    {
        $guilds = $this->finder('Guild\Manager:Guild')
            ->order('created_date', 'DESC')
            ->fetch();

        $publicRouter = $this->app->router('public');
        $listRows = [];
        foreach ($guilds as $guild) {
            $gid = (int) $guild->guild_id;
            $listRows[] = [
                'guild' => $guild,
                'urlEdit' => $this->buildGuildAcpActionUrl('edit', $gid),
                'urlPublic' => $publicRouter->buildLink('enterum-guilds', $guild),
            ];
        }

        return $this->view('Guild\Manager:Guild\List', 'guild_manager_admin_guild_list', [
            'guilds' => $guilds,
            'listRows' => $listRows,
        ]);
    }

    public function actionEdit(ParameterBag $params): AbstractReply
    {
        $guildId = (int) $this->filter('guild_id', 'uint');
        if ($guildId <= 0) {
            $guildId = (int) $params->get('guild_id', 0);
        }
        /** @var GuildEntity|null $guild */
        $guild = $this->em()->find('Guild\Manager:Guild', $guildId);
        if (!$guild) {
            return $this->notFound();
        }

        if ($this->isPost()) {
            $this->assertPostOnly();
            $input = $this->filter([
                'title' => 'str',
                'description' => 'str',
                'owner_user_id' => 'uint',
                'owner_username' => 'str',
            ]);

            if (trim($input['title']) === '') {
                return $this->error('Укажите название гильдии.');
            }

            $owner = $this->resolveUser($input['owner_user_id'], $input['owner_username']);
            if (!$owner) {
                return $this->error('Укажите владельца гильдии (выбором из подсказки или точным ником).');
            }

            $db = $this->db();
            $db->beginTransaction();
            try {
                if ((int)$owner->user_id !== (int)$guild->leader_user_id) {
                    $this->transferLeadershipInAcp($guild, $owner);
                }

                $guild->title = $input['title'];
                if ((int)$guild->node_id > 0) {
                    $shortcutNode = $this->em()->find('XF:Node', (int)$guild->node_id);
                    if ($shortcutNode) {
                        $shortcutNode->title = $input['title'];
                        $shortcutNode->save();
                    }
                }
                $guild->last_update = \XF::$time;
                $this->applyDescriptionFromAcp($guild, \XF::visitor(), (string)$input['description'], 'Редактирование в ACP');
                $guild->save();
                $db->commit();
            } catch (PrintableException $e) {
                $db->rollback();
                return $this->error($e->getMessage());
            } catch (\Throwable $e) {
                $db->rollback();
                throw $e;
            }

            return $this->redirect(
                $this->buildGuildAcpActionUrl('edit', (int) $guild->guild_id),
                'Изменения сохранены.'
            );
        }

        return $this->view('Guild\Manager:Guild\List', 'guild_manager_admin_guild_edit', [
            'guild' => $guild,
            'formAction' => $this->buildGuildAcpActionUrl('edit', (int) $guild->guild_id),
            'userFinderUrl' => $this->buildLink('guild-manager/find-users'),
        ]);
    }

    public function actionSetState(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();

        $guildId = (int) $this->filter('guild_id', 'uint');
        if ($guildId <= 0) {
            $guildId = (int) $params->get('guild_id', 0);
        }
        $newState = (string) $this->filter('guild_state', 'str');
        if ($guildId < 1) {
            return $this->notFound();
        }
        if (!in_array($newState, ['active', 'archived'], true)) {
            return $this->error('Выберите «Активная» или «Неактивная».');
        }

        /** @var GuildEntity|null $guild */
        $guild = $this->em()->find('Guild\Manager:Guild', $guildId);
        if (!$guild) {
            return $this->notFound();
        }

        if ($guild->guild_state === $newState) {
            return $this->redirect($this->buildLink('guild-manager'));
        }

        $guild->guild_state = $newState;
        $guild->last_update = \XF::$time;
        $guild->save();

        if ((int) $guild->node_id > 0) {
            $shortcutNode = $this->em()->find('XF:Node', (int) $guild->node_id);
            if ($shortcutNode && $shortcutNode->node_type_id === 'LinkForum') {
                $shortcutNode->display_in_list = ($newState === 'active');
                $shortcutNode->save();
            }
        }

        $msg = $newState === 'active'
            ? 'Состояние: активная.'
            : 'Состояние: неактивная.';

        return $this->redirect($this->buildLink('guild-manager'), $msg);
    }

    protected function transferLeadershipInAcp(GuildEntity $guild, User $newLeader): void
    {
        /** @var \Guild\Manager\Service\Guild\MembershipManager $membership */
        $membership = $this->service('Guild\Manager:Guild\MembershipManager');

        $oldLeaderId = (int)$guild->leader_user_id;
        if ((int)$newLeader->user_id === $oldLeaderId) {
            return;
        }

        $membership->setMemberRole($guild, $newLeader, PermissionPreset::ROLE_LEADER);

        if ($oldLeaderId > 0) {
            $old = $this->em()->find('XF:User', $oldLeaderId);
            if ($old) {
                $membership->setMemberRole($guild, $old, PermissionPreset::ROLE_MEMBER);
            }
        }

        $guild->leader_user_id = (int)$newLeader->user_id;
        $guild->leader_username = (string)$newLeader->username;
    }

    protected function applyDescriptionFromAcp(GuildEntity $guild, User $actor, string $newDescription, string $changeNote): void
    {
        $oldDescription = (string)$guild->description;
        $rendered = BbCodeContent::renderToHtml($this->app, $newDescription);

        $log = $this->em()->create('Guild\Manager:GuildDescriptionLog');
        $log->bulkSet([
            'guild_id' => $guild->guild_id,
            'old_description' => $oldDescription,
            'new_description' => $newDescription,
            'changed_by_user_id' => $actor->user_id,
            'change_date' => \XF::$time,
            'change_note' => $changeNote,
        ]);
        $log->save();

        $guild->description = $newDescription;
        $guild->description_rendered = $rendered;
        $guild->description_update_date = \XF::$time;
        $guild->description_update_user_id = (int)$actor->user_id;
    }

    protected function resolveUser(int $userId, string $username): ?User
    {
        if ($userId > 0) {
            $user = $this->em()->find('XF:User', $userId);
            if ($user) {
                return $user;
            }
        }

        $username = trim($username);
        if ($username === '') {
            return null;
        }

        return $this->finder('XF:User')
            ->where('username', $username)
            ->fetchOne();
    }
}
