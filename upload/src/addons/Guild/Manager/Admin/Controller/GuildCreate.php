<?php

namespace Guild\Manager\Admin\Controller;

use Guild\Manager\Service\Guild\GuildWorkflow;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\PrintableException;

/** ACP: форма ручного создания гильдии (обёртка над GuildWorkflow или прямой persist). */
class GuildCreate extends AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('guildManager');
    }

    public function actionIndex(): AbstractReply
    {
        $visitor = \XF::visitor();
        $nodeChoices = [0 => 'Не создавать ярлык в разделе'];
        $nodeRepo = $this->repository('XF:Node');
        $nodeTree = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList());
        foreach ($nodeTree->getFlattened() as $entry) {
            $record = is_array($entry) ? ($entry['record'] ?? null) : ($entry->record ?? null);
            if (!$record) {
                continue;
            }
            $node = is_array($record) ? null : $record;
            $nodeType = $node ? ($node->node_type_id ?? null) : ($record['node_type_id'] ?? null);
            if ($nodeType !== 'Forum') {
                continue;
            }
            $nodeId = $node ? (int)$node->node_id : (int)($record['node_id'] ?? 0);
            $title = $node ? (string)$node->title : (string)($record['title'] ?? '');
            if ($nodeId <= 0 || $title === '') {
                continue;
            }
            $depth = is_array($entry) ? (int)($entry['depth'] ?? 0) : (int)($entry->depth ?? 0);
            $prefix = str_repeat('-- ', max(0, $depth));
            $nodeChoices[$nodeId] = $prefix . $title . ' (#' . $nodeId . ')';
        }

        $viewParams = [
            'defaultActorId' => (int)$visitor->user_id,
            'defaultActorName' => (string)$visitor->username,
            'defaultOwnerId' => (int)$visitor->user_id,
            'defaultOwnerName' => (string)$visitor->username,
            'nodeChoices' => $nodeChoices,
            'userFinderUrl' => $this->buildLink('guild-manager/find-users'),
        ];

        return $this->view('Guild\Manager:GuildCreate', 'guild_manager_admin_create', $viewParams);
    }

    public function actionSave(): AbstractReply
    {
        $this->assertPostOnly();

        $input = $this->filter([
            'actor_user_id' => 'uint',
            'actor_username' => 'str',
            'owner_user_id' => 'uint',
            'owner_username' => 'str',
            'shortcut_parent_node_id' => 'uint',
            'title' => 'str',
            'description' => 'str',
        ]);

        if ($input['title'] === '') {
            return $this->error('Укажите название гильдии.');
        }

        $actor = $this->resolveUser($input['actor_user_id'], $input['actor_username']);
        if (!$actor) {
            return $this->error('Укажите пользователя-автора (выбором из подсказки или точным ником).');
        }

        $owner = $this->resolveUser($input['owner_user_id'], $input['owner_username']);
        if (!$owner) {
            return $this->error('Укажите владельца гильдии (выбором из подсказки или точным ником).');
        }

        try {
            /** @var GuildWorkflow $workflow */
            $workflow = $this->service('Guild\Manager:Guild\GuildWorkflow');
            $guild = $workflow->createGuild(
                $actor,
                $input['title'],
                $input['description'],
                $owner,
                0
            );

            if ($input['shortcut_parent_node_id'] > 0) {
                $shortcutNodeId = $this->createGuildShortcutNode(
                    $input['shortcut_parent_node_id'],
                    $guild->title,
                    (int)$guild->guild_id,
                    $guild->description
                );
                $guild->node_id = $shortcutNodeId;
                $guild->save();
            }
        } catch (PrintableException $e) {
            return $this->error($e->getMessage());
        }

        return $this->redirect(
            $this->app()->router('public')->buildLink('enterum-guilds', ['guild_id' => (int)$guild->guild_id]),
            'Гильдия создана.'
        );
    }

    public function actionFindUsers(): AbstractReply
    {
        $this->assertAdminPermission('guildManager');
        $query = trim($this->filter('q', 'str'));
        if ($query === '' || mb_strlen($query) < 2) {
            $this->setResponseType('json');

            return $this->view('Guild\Manager:FindUsersJson', '', ['results' => []]);
        }

        $finder = $this->finder('XF:User');
        if (ctype_digit($query)) {
            $finder->whereOr([
                ['user_id', '=', (int)$query],
                ['username', 'like', '%' . $query . '%']
            ]);
        } else {
            $finder->where('username', 'like', '%' . $query . '%');
        }

        $users = $finder->order('username')->limit(10)->fetch();

        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'user_id' => (int)$user->user_id,
                'username' => (string)$user->username,
            ];
        }

        $this->setResponseType('json');

        return $this->view('Guild\Manager:FindUsersJson', '', ['results' => $results]);
    }

    protected function createGuildShortcutNode(
        int $parentNodeId,
        string $guildTitle,
        int $guildId,
        string $description = ''
    ): int {
        /** @var \XF\Entity\Forum|null $parentForum */
        $parentForum = $this->em()->find('XF:Forum', $parentNodeId);
        if (!$parentForum) {
            throw new PrintableException('Раздел для ярлыка не найден.');
        }

        /** @var \XF\Entity\Node $node */
        $node = $this->em()->create('XF:Node');
        $node->node_type_id = 'LinkForum';
        $node->title = $guildTitle;
        $node->description = $description;
        $node->parent_node_id = $parentNodeId;
        $node->display_order = 1;
        $node->display_in_list = true;

        /** @var \XF\Entity\LinkForum $linkForum */
        $linkForum = $node->getDataRelationOrDefault();
        $linkForum->link_url = $this->app()->router('public')->buildLink(
            'canonical:enterum-guilds',
            ['guild_id' => $guildId]
        );

        $node->addCascadedSave($linkForum);
        $node->save();

        return (int)$node->node_id;
    }

    protected function resolveUser(int $userId, string $username): ?\XF\Entity\User
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
