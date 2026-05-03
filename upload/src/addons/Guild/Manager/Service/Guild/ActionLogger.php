<?php

namespace Guild\Manager\Service\Guild;

use Guild\Manager\Entity\Guild;
use XF\Entity\User;
use XF\Service\AbstractService;

/**
 * При необходимости пишет сводную строку в xf_guild_action_log (или аналог) для аудита админкой.
 */
class ActionLogger extends AbstractService
{
    public const ACTION_ADD = 'add';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';

    public function log(
        Guild $guild,
        User $actor,
        string $logType,
        string $actionType,
        string $summary
    ): void {
        $logType = trim($logType);
        $actionType = trim($actionType);
        $summary = trim($summary);
        if ($logType === '' || $actionType === '' || $summary === '') {
            return;
        }

        try {
            $this->db()->insert('xf_guild_action_log', [
                'guild_id' => (int)$guild->guild_id,
                'log_type' => $logType,
                'action_type' => $actionType,
                'summary' => mb_substr($summary, 0, 500),
                'actor_user_id' => (int)$actor->user_id,
                'event_date' => \XF::$time,
            ]);
        } catch (\Throwable $e) {
            // Журнал не должен ронять пользовательские действия.
        }
    }
}
