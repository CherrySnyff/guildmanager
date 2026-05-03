<?php

namespace Guild\Manager\Pub\View;

use XF\Mvc\View;

/** JSON-ответ автодополнения пользователей для формы смены лидера и блока участников. */
class FindUsersJson extends View
{
    public function renderJson(): array
    {
        return $this->params;
    }
}
