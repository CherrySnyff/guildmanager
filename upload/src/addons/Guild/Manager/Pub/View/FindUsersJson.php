<?php

namespace Guild\Manager\Pub\View;

use XF\Mvc\View;

/** JSON-ответ автодополнения пользователей для формы смены лидера и блока участников. */
class FindUsersJson extends View
{
    public function renderJson()
    {
        return json_encode($this->params, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
