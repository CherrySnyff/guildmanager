<?php

namespace Guild\Manager\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/** ACP: просмотр журналов казна/последователи/репутация/прочее с пагинацией. */
class GuildLogs extends AbstractController
{
    protected const PER_PAGE = 50;

    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('guildManager');
    }

    public function actionIndex(): AbstractReply
    {
        return $this->redirect($this->buildLink('guild-manager/logs/treasury'));
    }

    public function actionTreasury(): AbstractReply
    {
        return $this->renderLogList('treasury');
    }

    public function actionFollowers(): AbstractReply
    {
        return $this->renderLogList('followers');
    }

    public function actionReputation(): AbstractReply
    {
        return $this->renderLogList('reputation');
    }

    public function actionStorage(): AbstractReply
    {
        return $this->renderLogList('storage');
    }

    public function actionTransport(): AbstractReply
    {
        return $this->renderLogList('transport');
    }

    public function actionDescription(): AbstractReply
    {
        return $this->renderLogList('description');
    }

    protected function renderLogList(string $type): AbstractReply
    {
        $title = $this->getLogTitle($type);
        if ($title === null) {
            return $this->notFound();
        }

        $this->ensureActionLogTableExists();
        $hasActionLogTable = $this->hasActionLogTable();

        $page = max(1, (int)$this->filter('page', 'uint'));
        $sort = $this->resolveSortKey((string)$this->filter('sort', 'str'));
        $direction = $this->resolveSortDirection((string)$this->filter('direction', 'str'));
        $guildFilter = trim((string)$this->filter('guild', 'str'));
        $userFilter = trim((string)$this->filter('user', 'str'));
        $dateFromFilter = trim((string)$this->filter('date_from', 'str'));
        $dateToFilter = trim((string)$this->filter('date_to', 'str'));
        $offset = ($page - 1) * self::PER_PAGE;

        $orderBy = $this->buildOrderBy($sort, $direction);
        $db = $this->app->db();
        $rows = [];
        $total = 0;
        [$whereSql, $whereParams] = $this->buildFiltersSql(
            $type,
            $guildFilter,
            $userFilter,
            $dateFromFilter,
            $dateToFilter
        );

        $userJoinCondition = '(u.user_id = l.actor_user_id)';

        if ($hasActionLogTable) {
            $countParams = $whereParams;
            $total = (int)$db->fetchOne(
                'SELECT COUNT(*) 
                 FROM xf_guild_action_log AS l
                 LEFT JOIN xf_guild AS g ON (g.guild_id = l.guild_id)
                 LEFT JOIN xf_user AS u ON ' . $userJoinCondition
                 . $whereSql,
                $countParams
            );

            $selectParams = $whereParams;
            $selectParams[] = $offset;
            $selectParams[] = self::PER_PAGE;
            $rows = $db->fetchAll(
                'SELECT l.action_log_id AS row_id, l.*, g.title AS guild_title, u.username AS actor_username
                 FROM xf_guild_action_log AS l
                 LEFT JOIN xf_guild AS g ON (g.guild_id = l.guild_id)
                 LEFT JOIN xf_user AS u ON ' . $userJoinCondition . '
                 ' . $whereSql . '
                 ORDER BY ' . $orderBy . '
                 LIMIT ?, ?',
                $selectParams
            );
        }

        $filterParams = [
            'sort' => $sort,
            'direction' => $direction,
            'guild' => $guildFilter,
            'user' => $userFilter,
            'date_from' => $dateFromFilter,
            'date_to' => $dateToFilter,
        ];

        $viewParams = [
            'logType' => $type,
            'logTitle' => $title,
            'rows' => $rows,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => $total,
            'sort' => $sort,
            'direction' => $direction,
            'baseLink' => 'guild-manager/logs/' . $type,
            'tabs' => $this->getTabs(),
            'activeTab' => $type,
            'filterGuild' => $guildFilter,
            'filterUser' => $userFilter,
            'filterDateFrom' => $dateFromFilter,
            'filterDateTo' => $dateToFilter,
            'filterParams' => $filterParams,
            'hasActionLogTable' => $hasActionLogTable,
        ];

        return $this->view('Guild\Manager:GuildLogs\List', 'guild_manager_admin_logs_list', $viewParams);
    }

    protected function ensureActionLogTableExists(): void
    {
        try {
            if ($this->app->schemaManager()->tableExists('xf_guild_action_log')) {
                return;
            }
            $this->app->db()->query(
                "CREATE TABLE IF NOT EXISTS `xf_guild_action_log` (
                    `action_log_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `guild_id` INT UNSIGNED NOT NULL DEFAULT 0,
                    `log_type` VARCHAR(50) NOT NULL DEFAULT '',
                    `action_type` ENUM('add','update','delete') NOT NULL DEFAULT 'add',
                    `summary` VARCHAR(500) NOT NULL DEFAULT '',
                    `actor_user_id` INT UNSIGNED NOT NULL DEFAULT 0,
                    `event_date` INT UNSIGNED NOT NULL DEFAULT 0,
                    PRIMARY KEY (`action_log_id`),
                    KEY `log_type_event_date` (`log_type`,`event_date`),
                    KEY `guild_id` (`guild_id`),
                    KEY `actor_user_id` (`actor_user_id`),
                    KEY `event_date` (`event_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\Throwable $e) {
            // Если нет прав на DDL, ошибка будет показана ниже на обычном запросе к логу.
        }
    }

    protected function hasActionLogTable(): bool
    {
        try {
            return (bool)$this->app->db()->fetchOne("SHOW TABLES LIKE 'xf_guild_action_log'");
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function resolveSortKey(string $sort): string
    {
        return in_array($sort, ['date', 'guild', 'user'], true) ? $sort : 'date';
    }

    protected function resolveSortDirection(string $direction): string
    {
        return strtolower($direction) === 'asc' ? 'asc' : 'desc';
    }

    protected function buildOrderBy(string $sort, string $direction): string
    {
        $dir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        switch ($sort) {
            case 'guild':
                return 'g.title ' . $dir . ', l.event_date DESC';
            case 'user':
                return 'u.username ' . $dir . ', l.event_date DESC';
            case 'date':
            default:
                return 'l.event_date ' . $dir;
        }
    }

    protected function buildFiltersSql(
        string $type,
        string $guildFilter,
        string $userFilter,
        string $dateFromFilter,
        string $dateToFilter
    ): array {
        $db = $this->app->db();
        $where = [];
        $params = [];

        $where[] = 'l.log_type = ?';
        $params[] = $type;

        if ($guildFilter !== '') {
            $where[] = 'g.title LIKE ?';
            $params[] = $db->escapeLike($guildFilter, '?%');
        }

        if ($userFilter !== '') {
            $where[] = 'u.username LIKE ?';
            $params[] = $db->escapeLike($userFilter, '?%');
        }

        if ($dateFromFilter !== '') {
            $fromTs = strtotime($dateFromFilter . ' 00:00:00');
            if ($fromTs !== false) {
                $where[] = 'l.event_date >= ?';
                $params[] = (int)$fromTs;
            }
        }

        if ($dateToFilter !== '') {
            $toTs = strtotime($dateToFilter . ' 23:59:59');
            if ($toTs !== false) {
                $where[] = 'l.event_date <= ?';
                $params[] = (int)$toTs;
            }
        }

        if (!$where) {
            return ['', []];
        }

        return [' WHERE ' . implode(' AND ', $where), $params];
    }

    protected function getTabs(): array
    {
        return [
            'treasury' => ['label' => 'Лог казны', 'link' => 'guild-manager/logs/treasury'],
            'followers' => ['label' => 'Лог последователей', 'link' => 'guild-manager/logs/followers'],
            'reputation' => ['label' => 'Лог репутации', 'link' => 'guild-manager/logs/reputation'],
            'storage' => ['label' => 'Лог склада', 'link' => 'guild-manager/logs/storage'],
            'transport' => ['label' => 'Лог транспорта', 'link' => 'guild-manager/logs/transport'],
            'description' => ['label' => 'Лог описания', 'link' => 'guild-manager/logs/description'],
        ];
    }

    protected function getLogTitle(string $type): ?string
    {
        $map = [
            'treasury' => 'Лог казны',
            'followers' => 'Лог последователей',
            'reputation' => 'Лог репутации',
            'storage' => 'Лог склада',
            'transport' => 'Лог транспорта',
            'description' => 'Лог описания',
        ];

        return $map[$type] ?? null;
    }
}
