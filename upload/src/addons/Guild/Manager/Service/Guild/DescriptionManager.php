<?php

namespace Guild\Manager\Service\Guild;

use Guild\Manager\Entity\Guild;
use Guild\Manager\Helper\BbCodeContent;
use XF\Entity\User;
use XF\Service\AbstractService;

/**
 * Сохранение текста описания гильдии, рендер в description_rendered и запись журнала изменений (GuildDescriptionLog).
 */
class DescriptionManager extends AbstractService
{
    private const LOG_MAX_CHARS = 100000;

    public function updateDescription(
        Guild $guild,
        User $actor,
        string $newDescription,
        string $changeNote = '',
        ?string $guildRole = null
    ): \Guild\Manager\Entity\GuildDescriptionLog {
        /** @var PermissionGuard $permissionGuard */
        $permissionGuard = $this->service('Guild\Manager:Guild\PermissionGuard');
        $permissionGuard->assertCanEditDescription($guild, $actor, $guildRole);

        $oldDescription = (string) $guild->description;
        $newDescription = $this->assertUtf8ForStorage($newDescription);
        $rendered = $this->buildRenderedDescription($newDescription);

        $guild->description = $newDescription;
        $guild->description_rendered = $rendered;
        $guild->description_update_date = \XF::$time;
        $guild->description_update_user_id = $actor->user_id;
        $guild->last_update = \XF::$time;
        $guild->save();

        /** @var \Guild\Manager\Entity\GuildDescriptionLog $log */
        $log = $this->em()->create('Guild\Manager:GuildDescriptionLog');
        $log->bulkSet([
            'guild_id' => $guild->guild_id,
            'old_description' => $this->truncateForLog($oldDescription),
            'new_description' => $this->truncateForLog($newDescription),
            'changed_by_user_id' => $actor->user_id,
            'change_date' => \XF::$time,
            'change_note' => $changeNote
        ]);
        try {
            $log->save();
        } catch (\Throwable $e) {
            if ($this->app->config('debug')) {
                throw $e;
            }
            error_log('Guild Manager: xf_guild_description_log save failed after guild update: ' . $e->getMessage());
        }

        /** @var ActionLogger $actionLogger */
        $actionLogger = $this->service('Guild\Manager:Guild\ActionLogger');
        $actionLogger->log(
            $guild,
            $actor,
            'description',
            ActionLogger::ACTION_UPDATE,
            'Описание гильдии изменено'
        );

        return $log;
    }

    private function buildRenderedDescription(string $bbCode): string
    {
        $out = BbCodeContent::renderToHtml($this->app, $bbCode);
        return $this->assertUtf8ForStorage($out);
    }

    private function assertUtf8ForStorage(string $s): string
    {
        if ($s === '' || preg_match('//u', $s)) {
            return $s;
        }

        if (function_exists('iconv')) {
            $c = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
            if (is_string($c) && $c !== '') {
                return $c;
            }
        }

        return (string) preg_replace('/[\x{FFFE}\x{FFFF}\xD800-\xDFFF|\xFDD0-\xFDEF]/u', '', $s);
    }

    private function truncateForLog(string $s): string
    {
        if (self::LOG_MAX_CHARS <= 0) {
            return $s;
        }
        if (mb_strlen($s, 'UTF-8') <= self::LOG_MAX_CHARS) {
            return $s;
        }

        return mb_substr($s, 0, self::LOG_MAX_CHARS, 'UTF-8') . "\n\n[…усечено для записи в журнал…]";
    }
}
