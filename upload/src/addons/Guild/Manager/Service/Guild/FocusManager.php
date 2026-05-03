<?php

namespace Guild\Manager\Service\Guild;

use Guild\Manager\Entity\Guild;
use XF\Entity\User;
use XF\PrintableException;
use XF\Service\AbstractService;

/**
 * Направленности гильдии (xf_guild_focus): справочник FOCUS_KEYS, слоты 1–4, правила сохранения и замков под PermissionGuard.
 */
class FocusManager extends AbstractService
{
    public const SLOT_UNLOCK_LEVELS = [
        1 => 1,
        2 => 6,
        3 => 11,
        4 => 16,
    ];

    public const FOCUS_KEYS = [
        'ideology' => 'Идеология',
        'art' => 'Искусство',
        'production' => 'Производство',
        'economy' => 'Экономика',
        'magic' => 'Магия',
        'science' => 'Наука',
        'politics' => 'Политика',
        'statehood' => 'Государственность',
        'crime' => 'Преступность',
        'war' => 'Война',
        'defense' => 'Оборона',
        'public' => 'Общественность',
        'status' => 'Статус',
        'traditions' => 'Традиции',
        'progress' => 'Прогрессивность',
        'mercenary' => 'Наёмничество',
    ];

    public function assertValidFocusKeys(array $keys): void
    {
        foreach ($keys as $key) {
            if (!isset(self::FOCUS_KEYS[$key])) {
                throw new PrintableException('Неизвестная направленность.');
            }
        }
    }

    public function setGuildFocuses(
        Guild $guild,
        User $actor,
        array $focusKeys,
        ?string $guildRole = null
    ): void {
        /** @var PermissionGuard $guard */
        $guard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $guard->assertCanChangeLeader($guild, $actor, $guildRole);
        $canEditAnyDirections = $actor->hasPermission('guild_manager', 'editGuildDirectionsAny');

        /** @var Aggregator $aggregator */
        $aggregator = $this->service('Guild\Manager:Guild\Aggregator');
        $aggregator->recalculateFollowers($guild);
        $aggregator->recalculateOrganizationLevel($guild);
        $level = (int)$guild->organization_level;
        $availableSlots = $aggregator->getMaxDirectionSlots($level);

        $incomingSlots = $this->normalizeIncomingSlots($focusKeys);
        $incomingValues = array_values(array_filter($incomingSlots, static function ($value): bool
        {
            return $value !== '';
        }));
        $this->assertValidFocusKeys($incomingValues);
        if (count(array_unique($incomingValues)) !== count($incomingValues)) {
            throw new PrintableException('Направленности не должны повторяться.');
        }

        $existingSlots = $this->fetchExistingSlots($guild);
        $finalSlots = $existingSlots;

        for ($slot = 1; $slot <= 4; $slot++) {
            if ($slot > $availableSlots) {
                continue;
            }

            $existingValue = $existingSlots[$slot] ?? '';
            $incomingValue = $incomingSlots[$slot] ?? '';
            if ($incomingValue === null) {
                $incomingValue = '';
            }

            if ($canEditAnyDirections) {
                $finalSlots[$slot] = $incomingValue;
                continue;
            }

            if ($existingValue !== '' && $incomingValue !== $existingValue) {
                throw new PrintableException(
                    'Направленность для уровня ' . self::SLOT_UNLOCK_LEVELS[$slot]
                    . ' уже зафиксирована. Изменить её может только администратор сайта.'
                );
            }

            if ($existingValue === '' && $incomingValue !== '') {
                $finalSlots[$slot] = $incomingValue;
            }
        }

        $db = $this->db();
        $db->beginTransaction();
        try {
            $db->delete('xf_guild_focus', 'guild_id = ?', [(int) $guild->guild_id]);

            for ($slot = 1; $slot <= 4; $slot++) {
                $key = $finalSlots[$slot] ?? '';
                if ($key === '') {
                    continue;
                }
                /** @var \Guild\Manager\Entity\GuildFocus $row */
                $row = $this->em()->create('Guild\Manager:GuildFocus');
                $row->bulkSet([
                    'guild_id' => $guild->guild_id,
                    'focus_key' => $key,
                    'display_order' => $slot,
                    'created_date' => \XF::$time,
                ]);
                $row->save();
            }

            $guild->last_update = \XF::$time;
            $guild->save();

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    protected function fetchExistingSlots(Guild $guild): array
    {
        $slots = [1 => '', 2 => '', 3 => '', 4 => ''];
        $rows = $this->finder('Guild\Manager:GuildFocus')
            ->where('guild_id', $guild->guild_id)
            ->fetch();

        foreach ($rows as $row) {
            $slot = (int)$row->display_order;
            if ($slot >= 1 && $slot <= 4) {
                $slots[$slot] = (string)$row->focus_key;
            }
        }

        return $slots;
    }

    protected function normalizeIncomingSlots(array $raw): array
    {
        $slots = [1 => '', 2 => '', 3 => '', 4 => ''];

        // New format: keyed by explicit slot indexes.
        foreach ([1, 2, 3, 4] as $slot) {
            if (array_key_exists($slot, $raw) || array_key_exists((string)$slot, $raw)) {
                $value = $raw[$slot] ?? $raw[(string)$slot] ?? '';
                $slots[$slot] = trim((string)$value);
            }
        }

        // Backward compatibility: old sequential array "directions[]".
        if ($slots[1] === '' && $slots[2] === '' && $slots[3] === '' && $slots[4] === '') {
            $i = 1;
            foreach ($raw as $value) {
                if ($i > 4) {
                    break;
                }
                $slots[$i] = trim((string)$value);
                $i++;
            }
        }

        return $slots;
    }
}
