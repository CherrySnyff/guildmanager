<?php

namespace Guild\Manager\Helper;

/**
 * BB-код в HTML так же, как в XenForo для пользовательского контента (теги [IMG], [URL], [QUOTE] и т.д.),
 * а не через StringFormatter::renderBbCode(…, 'public'), что даёт сырой текст.
 */
class BbCodeContent
{
    /**
     * @param bool $userContentContext true — сначала рендер как «пользовательский» контент (@[USER] и сопутствующие теги, как в профиле/подписях)
     */
    public static function renderToHtml(\XF\App $app, string $message, bool $userContentContext = false): string
    {
        if (trim($message) === '') {
            return '';
        }

        $bb = $app->bbCode();
        $visitor = \XF::visitor();

        $generic = static function () use ($bb, $message) {
            return (string) $bb->render($message, 'html', '', null);
        };
        $asUser = static function () use ($bb, $message, $visitor) {
            return (string) $bb->render($message, 'html', 'user', $visitor);
        };
        $candidates = $userContentContext
            ? [$asUser, $generic]
            : [$generic, $asUser];

        $last = null;
        foreach ($candidates as $c) {
            try {
                return $c();
            } catch (\Throwable $e) {
                $last = $e;
            }
        }

        if ($app->config('debug') && $last) {
            throw $last;
        }

        return '<div class="bbWrapper bbWrapper--plain">'
            . nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
            . '</div>';
    }
}
