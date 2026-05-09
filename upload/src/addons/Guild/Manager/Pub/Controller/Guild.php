<?php

/**
 * Единственная точка сборки вкладочной страницы гильдии (enterum-guilds/{guild_id}).
 *
 * Выход: Generic view на шаблон guild_manager_guild_view — блоки для всех вкладок (казна/склад/…)
 * загружаются здесь один раз для простоты; POST-операции вынесены в Pub\Controller\Guild\*.
 *
 * Важный контекст шаблона:
 * - sizeRu / showLevelRenownCapNotice — подпись размера и предупреждение о капе по мировой известности;
 * - showDirectionMilestoneNotice (0|1) — подсказка о незаполненных доступных слотах направленностей.
 */

namespace Guild\Manager\Pub\Controller;

use Guild\Manager\Entity\Guild as GuildEntity;
use Guild\Manager\Helper\BbCodeContent;
use Guild\Manager\Service\Guild\Aggregator;
use Guild\Manager\Service\Guild\FocusManager;
use Guild\Manager\Service\Guild\MembershipManager;
use Guild\Manager\Service\Guild\PermissionGuard;
use Guild\Manager\Service\Guild\FollowerLieutenantRules;
use Guild\Manager\Service\Guild\ReputationDisplay;
use XF\Entity\User as UserEntity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Pub\Controller\AbstractController;

/** @see guild_manager_guild_view — основной публичный контроллер карточки гильдии. */
class Guild extends AbstractController
{
    /** Активные гильдии видимы на публичной стороне; archived — как 404. */
    protected function assertGuildViewable(GuildEntity $guild): void
    {
        if ($guild->guild_state !== 'active') {
            throw $this->exception($this->notFound());
        }
    }

    /** Имя активной вкладки из query-параметра tab; невалидное → описание. */
    protected function resolveTab(string $tab): string
    {
        $allowed = [
            'description',
            'treasury',
            'storage',
            'followers',
            'reputation',
            'important-npcs',
            'bases',
            'achievements',
            'members',
        ];

        return in_array($tab, $allowed, true) ? $tab : 'description';
    }

    /** Подвыбор вкладки «Репутация»: регион биомов (арамидис/корзус/юнион). */
    protected function resolveRepRegion(string $region): string
    {
        $allowed = ['aramidis', 'korzus', 'union'];

        return in_array($region, $allowed, true) ? $region : 'aramidis';
    }

    public function actionIndex(ParameterBag $params): AbstractReply
    {
        /* --- Карточка гильдии: загрузка, роли, направленности, репутация по регионам, флаги UI --- */

        $guildId = (int)$params->get('guild_id', 0);
        if ($guildId <= 0) {
            return $this->errorInvalidGuild();
        }

        /** @var GuildEntity|null $guild */
        $guild = $this->em()->find('Guild\Manager:Guild', $guildId);
        if (!$guild) {
            return $this->errorInvalidGuild();
        }

        $this->assertGuildViewable($guild);

        $tab = $this->resolveTab($this->filter('tab', 'str'));
        $repRegion = $this->resolveRepRegion($this->filter('rep', 'str'));

        $visitor = \XF::visitor();
        /** @var MembershipManager $membershipManager */
        $membershipManager = $this->service('Guild\Manager:Guild\MembershipManager');
        $guildRole = $visitor->user_id ? $membershipManager->getUserGuildRole($guild, $visitor) : null;

        /** @var PermissionGuard $guard */
        $guard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $isGuildOwner = $visitor->user_id > 0 && (int)$guild->leader_user_id === (int)$visitor->user_id;
        $canEditGuildTitleAny = $visitor->hasPermission('guild_manager', 'editGuildTitleAny');
        $canEditGuildDirectionsAny = $visitor->hasPermission('guild_manager', 'editGuildDirectionsAny');
        $canManageDirections = $guard->canManageDirections($guild, $visitor, $guildRole);
        $canAppointGuildOfficer = $guard->canAppointGuildOfficer($guild, $visitor, $guildRole);

        /** @var \Guild\Manager\Repository\GuildMember $guildMemberRepo */
        $guildMemberRepo = $this->repository('Guild\Manager:GuildMember');
        $guildOfficers = [];
        foreach ($guildMemberRepo->findActiveOfficersForGuild($guild->guild_id)->fetch() as $officerRow) {
            $guildOfficers[] = [
                'user_id' => (int)$officerRow->user_id,
                'username' => (string)$officerRow->username,
            ];
        }

        $focusRows = $this->finder('Guild\Manager:GuildFocus')
            ->where('guild_id', $guild->guild_id)
            ->order('display_order')
            ->fetch();

        $focusLabels = FocusManager::FOCUS_KEYS;
        $directionSlots = [1 => '', 2 => '', 3 => '', 4 => ''];
        foreach ($focusRows as $f) {
            $slot = (int)$f->display_order;
            if ($slot >= 1 && $slot <= 4) {
                $directionSlots[$slot] = (string)$f->focus_key;
            }
        }
        $maxDirectionSlots = 1;
        if ((int)$guild->organization_level >= 16) {
            $maxDirectionSlots = 4;
        } elseif ((int)$guild->organization_level >= 11) {
            $maxDirectionSlots = 3;
        } elseif ((int)$guild->organization_level >= 6) {
            $maxDirectionSlots = 2;
        }

        $summaryDirectionItems = [];
        $selectedDirections = [];
        foreach ($focusRows as $f) {
            $slot = (int)$f->display_order;
            $key = (string)$f->focus_key;
            $selectedDirections[] = $key;
            if ($slot >= 1 && $slot <= $maxDirectionSlots) {
                $label = $focusLabels[$key] ?? $key;
                $hint = FocusManager::FOCUS_HINTS[$key] ?? '';
                $summaryDirectionItems[] = [
                    'key' => $key,
                    'label' => $label,
                    'hint' => $hint,
                    'index' => count($summaryDirectionItems),
                ];
            }
        }
        $summaryDirections = implode(
            ', ',
            array_map(static function (array $row): string {
                return (string)($row['label'] ?? '');
            }, $summaryDirectionItems)
        );

        $focusOptions = [];
        foreach ($focusLabels as $k => $label) {
            $focusOptions[] = [
                'key' => $k,
                'label' => $label,
                'selected' => in_array($k, $selectedDirections, true),
            ];
        }

        $directionSlot1 = (string)($directionSlots[1] ?? '');
        $directionSlot2 = (string)($directionSlots[2] ?? '');
        $directionSlot3 = (string)($directionSlots[3] ?? '');
        $directionSlot4 = (string)($directionSlots[4] ?? '');
        $directionSlot1Locked = (!$canEditGuildDirectionsAny && $directionSlot1 !== '');
        $directionSlot2Locked = (!$canEditGuildDirectionsAny && $directionSlot2 !== '');
        $directionSlot3Locked = (!$canEditGuildDirectionsAny && $directionSlot3 !== '');
        $directionSlot4Locked = (!$canEditGuildDirectionsAny && $directionSlot4 !== '');

        /** @var \XF\Entity\User|null $leaderUser */
        $leaderUser = $guild->leader_user_id
            ? $this->em()->find('XF:User', $guild->leader_user_id)
            : null;

        $guildRoot = rtrim($this->buildLink('enterum-guilds', ['guild_id' => $guild->guild_id]), '/');
        $findUsersUrl = $guildRoot . '/find-users';
        $descriptionView = $this->buildDescriptionForGuildPage($guild);
        $membersView = $this->buildMembersBlockForGuildPage($guild);
        $importantNpcRows = $this->finder('Guild\Manager:GuildImportantNpc')
            ->where('guild_id', $guild->guild_id)
            ->order('display_order', 'ASC')
            ->fetch();

        $basesOpenBaseId = (int)$this->filter('open_base', 'uint');
        $basesAddBuilding = (int)$this->filter('add_building', 'uint') ? 1 : 0;
        $guildBasesGrouped = [];
        $guildBaseEntities = $this->finder('Guild\Manager:GuildBase')
            ->where('guild_id', $guild->guild_id)
            ->order('display_order', 'ASC')
            ->fetch();
        foreach ($guildBaseEntities as $baseEnt) {
            $buildingRows = $this->finder('Guild\Manager:GuildBaseBuilding')
                ->where('guild_base_id', $baseEnt->guild_base_id)
                ->order('display_order', 'ASC')
                ->fetch();
            $guildBasesGrouped[] = [
                'base' => $baseEnt,
                'buildings' => $buildingRows,
            ];
        }
        $repRegions = ['aramidis', 'korzus', 'union'];
        $repRowsByRegion = [];
        $factionRowsByRegion = [];
        foreach ($repRegions as $regionKey) {
            $regionRows = $this->repository('Guild\Manager:GuildReputationLog')
                ->findGuildLogsForRegion($guild->guild_id, $regionKey)
                ->fetch();
            $repRowsByFaction = [];
            foreach ($regionRows as $row) {
                $factionKey = mb_strtolower(trim((string)$row->faction_name));
                if ($factionKey === '') {
                    $factionKey = '__empty__';
                }
                if (!isset($repRowsByFaction[$factionKey])) {
                    $repRowsByFaction[$factionKey] = [];
                }
                $repRowsByFaction[$factionKey][] = $row;
            }
            $regionFactionRows = $this->repository('Guild\Manager:GuildReputationLog')
                ->getFactionAggregatesForRegion($guild->guild_id, $regionKey);
            foreach ($regionFactionRows as &$factionRow) {
                $key = (string)($factionRow['faction_key'] ?? '');
                $factionRow['entries'] = $repRowsByFaction[$key] ?? [];
            }
            unset($factionRow);

            $repRowsByRegion[$regionKey] = $regionRows;
            $factionRowsByRegion[$regionKey] = $regionFactionRows;
        }
        $factionRows = $factionRowsByRegion[$repRegion] ?? [];

        /* Уровень «по последователям», кап известности, подсказка на витрине (согласовать с USER_GUIDE_RU.md). */

        /** @var Aggregator $guildAggregator */
        $guildAggregator = $this->service('Guild\Manager:Guild\Aggregator');
        /** @var \Guild\Manager\Repository\GuildReputationLog $repRepo */
        $repRepo = $this->repository('Guild\Manager:GuildReputationLog');
        $levelFromFollowersOnly = $guildAggregator->getOrganizationLevelFromFollowersTotal((int)$guild->followers_total);
        $worldRenownScore = $repRepo->getWorldRenownScore((int)$guild->guild_id);
        $maxLevelByRenown = $guildAggregator->maxOrganizationLevelForWorldRenown($worldRenownScore);
        $nextRenownForCap = $guildAggregator->minWorldRenownForNextCapIncrease($maxLevelByRenown);
        $pinnedAtRenownCeiling = (int)$guild->organization_level === $maxLevelByRenown
            && $nextRenownForCap !== null
            && $worldRenownScore < $nextRenownForCap;
        // Перекос по последователям выше потолка известности, либо уже на потолке и известности не хватает на следующий порог (напр. мир. 4 и ур. 5).
        $showLevelRenownCapNotice = $levelFromFollowersOnly > $maxLevelByRenown || $pinnedAtRenownCeiling;

        /* Пустые слоты направленностей среди уже открытых по уровню (логика дублирует FocusManager::SLOT_UNLOCK_LEVELS через maxDirectionSlots). */
        $slot1 = trim($directionSlot1);
        $slot2 = trim($directionSlot2);
        $slot3 = trim($directionSlot3);
        $slot4 = trim($directionSlot4);
        $showDirectionMilestoneNotice = false;
        if ($maxDirectionSlots >= 1 && $slot1 === '') {
            $showDirectionMilestoneNotice = true;
        } elseif ($maxDirectionSlots >= 2 && $slot2 === '') {
            $showDirectionMilestoneNotice = true;
        } elseif ($maxDirectionSlots >= 3 && $slot3 === '') {
            $showDirectionMilestoneNotice = true;
        } elseif ($maxDirectionSlots >= 4 && $slot4 === '') {
            $showDirectionMilestoneNotice = true;
        }

        $viewParams = [
            'guild' => $guild,
            'descriptionDisplayHtml' => $descriptionView['html'],
            'hasDescriptionToShow' => $descriptionView['has'],
            'membersBlockDisplayHtml' => $membersView['html'],
            'hasMembersBlockToShow' => $membersView['has'],
            'membersTableRows' => $membersView['rows'],
            'membersTableColumns' => $membersView['columns'],
            'membersUsesStructuredMode' => $membersView['mode'] === 'structured',
            'guildRoot' => $guildRoot,
            'findUsersUrl' => $findUsersUrl,
            'tab' => $tab,
            'repRegion' => $repRegion,
            'guildRole' => $guildRole,
            'leaderUser' => $leaderUser,
            'focusRows' => $focusRows,
            'focusLabels' => $focusLabels,
            'summaryDirectionItems' => $summaryDirectionItems,
            'summaryDirections' => $summaryDirections,
            'selectedDirections' => $selectedDirections,
            'directionSlots' => $directionSlots,
            'directionSlot1' => $directionSlot1,
            'directionSlot2' => $directionSlot2,
            'directionSlot3' => $directionSlot3,
            'directionSlot4' => $directionSlot4,
            'directionSlot1Locked' => $directionSlot1Locked,
            'directionSlot2Locked' => $directionSlot2Locked,
            'directionSlot3Locked' => $directionSlot3Locked,
            'directionSlot4Locked' => $directionSlot4Locked,
            'maxDirectionSlots' => $maxDirectionSlots,
            'focusOptions' => $focusOptions,
            'canEditDescription' => $guard->canEditDescription($guild, $visitor, $guildRole),
            'canChangeLeader' => $guard->canChangeLeader($guild, $visitor, $guildRole),
            'canEditGuildPanel' => ($isGuildOwner || $canEditGuildTitleAny || $canManageDirections || $canAppointGuildOfficer),
            'canAppointGuildOfficer' => $canAppointGuildOfficer,
            'guildOfficers' => $guildOfficers,
            'canEditGuildTitleAdmin' => $canEditGuildTitleAny,
            'canEditGuildDirectionsAny' => $canEditGuildDirectionsAny,
            'canManageDirections' => $canManageDirections,
            'canAddTreasury' => $guard->canAddTreasuryOperation($guild, $visitor, $guildRole),
            'canAddFollower' => $guard->canAddFollowerOperation($guild, $visitor, $guildRole),
            'canAddReputation' => $guard->canAddReputationOperation($guild, $visitor, $guildRole),
            'canManageStorage' => $guard->canManageStorage($guild, $visitor, $guildRole),
            'canManageAchievements' => $guard->canManageAchievements($guild, $visitor, $guildRole),
            'canManageMembersBlock' => $guard->canManageMembersBlock($guild, $visitor, $guildRole),
            'canManageImportantNpcs' => $guard->canManageImportantNpcs($guild, $visitor, $guildRole),
            'canManageGuildBases' => $guard->canManageGuildBases($guild, $visitor, $guildRole),
            'guildBasesGrouped' => $guildBasesGrouped,
            'basesOpenBaseId' => $basesOpenBaseId,
            'basesAddBuilding' => $basesAddBuilding,
            'canDeleteImportantNpcs' => $guard->canDeleteImportantNpcs($guild, $visitor, $guildRole),
            'canDeleteAchievements' => $guard->canManageAchievements($guild, $visitor, $guildRole),
            'canEditTreasury' => $guard->canEditTreasuryLogEntry($guild, $visitor, $guildRole),
            'canDeleteTreasury' => $guard->canDeleteTreasuryLogEntry($guild, $visitor, $guildRole),
            'canEditFollower' => $guard->canEditFollowerLogEntry($guild, $visitor, $guildRole),
            'canDeleteFollower' => $guard->canDeleteFollowerLogEntry($guild, $visitor, $guildRole),
            'canEditReputation' => $guard->canEditReputationLogEntry($guild, $visitor, $guildRole),
            'canDeleteReputation' => $guard->canDeleteReputationLogEntry($guild, $visitor, $guildRole),
            'canEditStorage' => $visitor->hasPermission('guild_manager', 'manageGuildAny'),
            'canDeleteStorage' => $guard->canDeleteStorageItem($guild, $visitor, $guildRole),
            'canEditVehicle' => $guard->canEditVehicle($guild, $visitor, $guildRole),
            'canDeleteVehicle' => $guard->canDeleteVehicle($guild, $visitor, $guildRole),
            'canSearchLeaderUsers' => $guard->canSearchLeaderUsers($guild, $visitor, $guildRole),
            'treasuryRows' => $this->finder('Guild\Manager:GuildTreasuryLog')
                ->where('guild_id', $guild->guild_id)
                ->order('created_date', 'DESC')
                ->fetch(),
            'storageRows' => $this->repository('Guild\Manager:GuildStorage')->findStorageForGuild($guild->guild_id)->fetch(),
            'followerRows' => $this->finder('Guild\Manager:GuildFollowerLog')
                ->where('guild_id', $guild->guild_id)
                ->order('created_date', 'DESC')
                ->fetch(),
            'vehicleRows' => $this->finder('Guild\Manager:GuildVehicle')
                ->where('guild_id', $guild->guild_id)
                ->order('vehicle_id')
                ->fetch(),
            'influenceRows' => $this->repository('Guild\Manager:GuildReputationLog')->getInfluenceTable($guild->guild_id),
            'repRowsByRegion' => $repRowsByRegion,
            'factionRowsByRegion' => $factionRowsByRegion,
            'factionRows' => $factionRows,
            'achievementRows' => $this->repository('Guild\Manager:GuildAchievement')
                ->findAchievementsForGuild($guild->guild_id)
                ->fetch(),
            'importantNpcRows' => $importantNpcRows,
            'sizeRu' => ReputationDisplay::organizationSizeRu((string)$guild->organization_size_label),
            'showLevelRenownCapNotice' => $showLevelRenownCapNotice,
            'showDirectionMilestoneNotice' => $showDirectionMilestoneNotice ? 1 : 0,
            'noviceProtectionActive' => (int)$guild->organization_level <= 5,
            'followersLieutenantFragmentHtml' => FollowerLieutenantRules::buildLieutenantSummaryFragmentHtml((int)$guild->organization_level),
            'followersRulesTableHtml' => FollowerLieutenantRules::buildRulesTableHtml(),
            'followersAddDefaultDate' => $this->buildFollowersAddDefaultDate($visitor),
        ];

        /* viewParams см. шаблон _data/templates.xml → guild_manager_guild_view. */

        return $this->view('XF:Pub\View\Generic', 'guild_manager_guild_view', $viewParams);
    }

    protected function errorInvalidGuild(): AbstractReply
    {
        return $this->notFound();
    }

    /**
     * Текущая дата в часовом поясе посетителя (дд.мм.гггг) для формы последователей.
     */
    protected function buildFollowersAddDefaultDate(\XF\Entity\User $visitor): string
    {
        $tzId = trim((string)$visitor->timezone);
        if ($tzId === '') {
            $tzId = 'UTC';
        }
        try {
            $tz = new \DateTimeZone($tzId);
        } catch (\Exception $e) {
            $tz = new \DateTimeZone('UTC');
        }

        return (new \DateTimeImmutable('now', $tz))->format('d.m.Y');
    }

    /** Отображение вкладки «Описание»: готовый HTML описания или пустая заглушка. */
    protected function buildDescriptionForGuildPage(GuildEntity $guild): array
    {
        $rawFull = (string) $guild->description;
        $rendered = (string) $guild->description_rendered;
        if (trim($rawFull) === '') {
            return ['html' => '', 'has' => false];
        }
        if (
            $rendered !== ''
            && !$this->isLikelyUnparsedBbCodeString($rendered)
            && $this->renderedDescriptionLooksNonEmpty($rendered)
        ) {
            return ['html' => $rendered, 'has' => true];
        }
        $html = BbCodeContent::renderToHtml($this->app, $rawFull);
        return ['html' => $html, 'has' => true];
    }

    /**
     * Блок участников: либо структурированный JSON gm_members_v2 (табличный режим), либо legacy BBCode.
     */
    protected function buildMembersBlockForGuildPage(GuildEntity $guild): array
    {
        $rawFull = (string)$guild->members_bbcode;
        $parsedRows = $this->parseStructuredMembersRows($rawFull);
        if ($parsedRows !== null) {
            return [
                'html' => '',
                'has' => count($parsedRows) > 0,
                'rows' => $parsedRows,
                'columns' => $this->buildMembersColumns($parsedRows, 12),
                'mode' => 'structured',
            ];
        }

        $rendered = (string)$guild->members_bbcode_rendered;
        if (trim($rawFull) === '') {
            return ['html' => '', 'has' => false, 'rows' => [], 'columns' => [], 'mode' => 'legacy'];
        }
        if (
            $rendered !== ''
            && !$this->isLikelyUnparsedBbCodeString($rendered)
            && $this->renderedDescriptionLooksNonEmpty($rendered)
        ) {
            return ['html' => $rendered, 'has' => true, 'rows' => [], 'columns' => [], 'mode' => 'legacy'];
        }
        $html = BbCodeContent::renderToHtml($this->app, $rawFull, true);
        return ['html' => $html, 'has' => true, 'rows' => [], 'columns' => [], 'mode' => 'legacy'];
    }

    /** Разбор members_bbcode в формате gm_members_v2; иначе null → legacy режим. */
    protected function parseStructuredMembersRows(string $raw): ?array
    {
        $data = json_decode($raw, true);
        if (!is_array($data) || ($data['format'] ?? '') !== 'gm_members_v2') {
            return null;
        }
        $items = $data['items'] ?? null;
        if (!is_array($items)) {
            return [];
        }

        $userIds = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $uid = (int)($item['user_id'] ?? 0);
            if ($uid > 0) {
                $userIds[] = $uid;
            }
        }
        $userIds = array_values(array_unique($userIds));
        $usersById = [];
        if ($userIds !== []) {
            /** @var \XF\Mvc\Entity\ArrayCollection<int, UserEntity> $users */
            $users = $this->finder('XF:User')->where('user_id', $userIds)->fetch();
            foreach ($users as $user) {
                $usersById[(int)$user->user_id] = $user;
            }
        }

        $rows = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $uid = (int)($item['user_id'] ?? 0);
            if ($uid <= 0 || !isset($usersById[$uid])) {
                continue;
            }
            $role = trim((string)($item['role'] ?? ''));
            if ($role !== '') {
                $role = mb_substr($role, 0, 60);
            }
            $rows[] = [
                'user' => $usersById[$uid],
                'role' => $role,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $aHasRole = trim((string)($a['role'] ?? '')) !== '';
            $bHasRole = trim((string)($b['role'] ?? '')) !== '';
            if ($aHasRole !== $bHasRole) {
                return $aHasRole ? -1 : 1;
            }

            $aName = mb_strtolower((string)($a['user']->username ?? ''));
            $bName = mb_strtolower((string)($b['user']->username ?? ''));
            return $aName <=> $bName;
        });

        return $rows;
    }

    /** Разбивка списка участников на столбцы в шаблоне. */
    protected function buildMembersColumns(array $rows, int $rowsPerColumn): array
    {
        if ($rowsPerColumn <= 0) {
            $rowsPerColumn = 12;
        }

        return array_chunk($rows, $rowsPerColumn);
    }

    /** Эвристика: в БД сохранился сырой BBCode вместо отрендеренного HTML — пересчитываем на лету. */
    private function isLikelyUnparsedBbCodeString(string $s): bool
    {
        if (trim($s) === '') {
            return false;
        }
        if (strpos($s, '[') === false) {
            return false;
        }

        return (bool) preg_match(
            '/\[[\/]?(?:IMG|ATTACH|URL|MEDIA|B|I|U|S|CODE|ISPOILER|QUOTE|SPOILER|LIST|USER|EMAIL)(?:=|[\s\]]|\])/i',
            $s
        );
    }

    /** После strip_tags остаётся ли смысловое содержимое или хотя бы встроенное изображение. */
    private function renderedDescriptionLooksNonEmpty(string $html): bool
    {
        if ($this->isLikelyUnparsedBbCodeString($html)) {
            return false;
        }
        $s = trim(preg_replace('/\s+/', ' ', strip_tags($html, '<img>')));

        return $s !== '' || (bool) preg_match('/<img\s|<iframe/i', $html);
    }
}
