<?php

/**
 * Общая база для POST-контроллеров подмаршрутов enterum-guilds/{guild_id}/… .
 *
 * Наследники: Treasury, Followers, Reputation и др. Вызывают loadGuild → handlePrintableOrRedirect или redirectToGuild.
 *
 * По зонам в файле ниже:
 * - загрузка гильдии и роли посетителя;
 * - редирект на вкладку карточки после успешной мутации;
 * - обработка исключений (PrintableException, Entity\Exception) и ошибка пользователю;
 * - сбор BBCode из редактора / fallback POST;
 * - нормализация @упоминаний для профильных ссылок.
 */

namespace Guild\Manager\Pub\Controller\Guild;

use Guild\Manager\Entity\Guild as GuildEntity;
use Guild\Manager\Service\Guild\MembershipManager;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Redirect;
use XF\Pub\Controller\AbstractController;

abstract class AbstractGuildAction extends AbstractController
{
    /* ---------- Загрузка гильдии и контекст посетителя ---------- */

    /** Только активные карточки; archived считаются отсутствующими для публичных действий. */
    protected function assertGuildViewable(GuildEntity $guild): void
    {
        if ($guild->guild_state !== 'active') {
            throw $this->exception($this->notFound());
        }
    }

    /**
     * Загружает сущность по guild_id из маршрута; 404 если неверный id или гильдия не активна.
     */
    protected function loadGuild(ParameterBag $params): GuildEntity
    {
        $guildId = (int)$params->get('guild_id', 0);
        if ($guildId <= 0) {
            throw $this->exception($this->notFound());
        }

        /** @var GuildEntity|null $guild */
        $guild = $this->em()->find('Guild\Manager:Guild', $guildId);
        if (!$guild) {
            throw $this->exception($this->notFound());
        }

        $this->assertGuildViewable($guild);

        return $guild;
    }

    /**
     * Роль пользователя в этой гильдии для PermissionGuard (null для гостя).
     */
    protected function getGuildRole(GuildEntity $guild, \XF\Entity\User $visitor): ?string
    {
        if (!$visitor->user_id) {
            return null;
        }

        /** @var MembershipManager $membershipManager */
        $membershipManager = $this->service('Guild\Manager:Guild\MembershipManager');

        return $membershipManager->getUserGuildRole($guild, $visitor);
    }

    /* ---------- Навигация после сохранения ---------- */

    /**
     * Общий успешный исход: возврат на карточку с нужной вкладкой и (для репутации) подвыбранным регионом rep.
     */
    protected function redirectToGuild(GuildEntity $guild, string $tab, string $repRegion = 'aramidis', array $extraParams = []): Redirect
    {
        return $this->redirect(
            $this->buildLink('enterum-guilds', ['guild_id' => $guild->guild_id], array_merge([
                'tab' => $tab,
                'rep' => $repRegion,
            ], $extraParams))
        );
    }

    /* ---------- Ошибки мутаций и успешный redirect ---------- */

    /**
     * Выполняет callable с бизнес-логикой; при успехе — redirect на вкладку, при ошибке — XenForo error() с текстом.
     * PrintableException — показ сообщения пользователю; Mvc\Entity\Exception — попытка вытащить полевые ошибки.
     */
    protected function handlePrintableOrRedirect(
        callable $fn,
        GuildEntity $guild,
        string $tab,
        string $repRegion = 'aramidis',
        array $extraParams = []
    ): AbstractReply {
        try {
            $fn();
        } catch (\XF\PrintableException $e) {
            return $this->error($e->getMessage());
        } catch (\XF\Mvc\Entity\Exception $e) {
            $details = $this->extractEntityErrorMessage($e);
            return $this->error($details !== '' ? $details : 'Проверьте корректность заполнения полей.');
        } catch (\XF\Mvc\Reply\Exception $e) {
            throw $e;
        } catch (\Throwable $e) {
            error_log(
                'Guild Manager mutation error [' . get_class($e) . ']: '
                . $e->getMessage()
            );
            if ($this->app->config('debug')) {
                return $this->error($e->getMessage() . ' [' . get_class($e) . ']');
            }
            return $this->error('Не удалось сохранить данные. Попробуйте ещё раз.');
        }

        return $this->redirectToGuild($guild, $tab, $repRegion, $extraParams);
    }

    /**
     * Строка для пользователя из XF исключений сущности (вложенные массивы getErrors()/getMessages()).
     */
    protected function extractEntityErrorMessage(\Throwable $e): string
    {
        if (method_exists($e, 'getErrors')) {
            $errs = $e->getErrors();
            if (is_array($errs) && $errs !== []) {
                $flat = [];
                foreach ($errs as $err) {
                    if (is_array($err)) {
                        foreach ($err as $nested) {
                            if (is_scalar($nested)) {
                                $flat[] = (string)$nested;
                            }
                        }
                    } elseif (is_scalar($err)) {
                        $flat[] = (string)$err;
                    }
                }
                if ($flat !== []) {
                    return implode("\n", array_values(array_unique($flat)));
                }
            }
        }
        if (method_exists($e, 'getMessages')) {
            $messages = $e->getMessages();
            if (is_array($messages) && $messages !== []) {
                $flat = [];
                foreach ($messages as $msg) {
                    if (is_scalar($msg)) {
                        $flat[] = (string)$msg;
                    }
                }
                if ($flat !== []) {
                    return implode("\n", array_values(array_unique($flat)));
                }
            }
        }

        return trim((string)$e->getMessage());
    }

    /* ---------- Тексты из Froala/XF Editor ---------- */

    /**
     * Основной путь: контент поля message через стандартный плагин Editor (BBCode строкой).
     */
    protected function getMessageFromEditor(): string
    {
        $editor = $this->plugin('XF:Editor');
        if (!is_object($editor) || !method_exists($editor, 'fromInput')) {
            return '';
        }
        $v = $editor->fromInput('message');
        if (is_string($v)) {
            return $v;
        }
        if (is_object($v) && method_exists($v, '__toString')) {
            return (string) $v;
        }

        return '';
    }

    /**
     * Резерв если Froala/JS не отдал строку: сырой $_POST или повтор через Request (members_bbcode / description_bbcode).
     */
    protected function getBbCodeFromRequestFallbacks(): string
    {
        if (isset($_POST['message']) && is_string($_POST['message'])) {
            return $_POST['message'];
        }
        if (isset($_POST['members_bbcode']) && is_string($_POST['members_bbcode'])) {
            return $_POST['members_bbcode'];
        }
        if (isset($_POST['description_bbcode'])) {
            $v = $_POST['description_bbcode'];
            if (is_string($v)) {
                return $v;
            }
            if (is_array($v)) {
                foreach (['text', 'bbcode', 0, 'value'] as $k) {
                    if (isset($v[$k]) && is_string($v[$k])) {
                        return $v[$k];
                    }
                }
            }
        }
        if (is_object($this->request) && method_exists($this->request, 'get')) {
            $f = $this->request->get('message', '');
            if (is_string($f) && $f !== '') {
                return $f;
            }
            $f = $this->request->get('members_bbcode', '');
            if (is_string($f) && $f !== '') {
                return $f;
            }
            $f = $this->request->get('description_bbcode', '');
            if (is_string($f) && $f !== '') {
                return $f;
            }
        }

        return '';
    }

    /* ---------- Упоминания @ник в тексте блоков гильдии ---------- */

    /**
     * Конвертирует строки вида "@Имя Пользователя" в XF-упоминание [USER=id]@Имя[/USER].
     * Это позволяет получать профильные ссылки и оформление ника плагинами ролей.
     */
    protected function normalizeStandaloneAtMentions(string $text): string
    {
        if (strpos($text, '@') === false) {
            return $text;
        }

        // Базовый XF-парсер упоминаний (учитывает расширения MentionFormatter от add-ons).
        try {
            $formatter = $this->app->stringFormatter()->getMentionFormatter();
            $text = (string) $formatter->getMentionsBbCode($text);
        } catch (\Throwable $e) {
            // Fallback ниже.
        }

        $parts = preg_split('/(\R)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts) || $parts === []) {
            return $text;
        }

        $resolved = [];
        foreach ($parts as &$part) {
            if ($part === '' || preg_match('/^\R$/u', $part)) {
                continue;
            }

            if (!preg_match('/^\s*@(.+?)\s*$/u', $part, $m)) {
                continue;
            }

            $name = trim($m[1]);
            if ($name === '') {
                continue;
            }

            if (!array_key_exists($name, $resolved)) {
                $resolved[$name] = $this->resolveMentionUserByNameOrPrefix($name);
            }

            $user = $resolved[$name];
            if ($user) {
                $safeName = str_replace([']', '['], '', (string) $user->username);
                $part = '[USER=' . (int) $user->user_id . ']@' . $safeName . '[/USER]';
            }
        }
        unset($part);

        return implode('', $parts);
    }

    /**
     * Поиск пользователя по точному username; если не найден — по префиксу только при единственном кандидате.
     */
    protected function resolveMentionUserByNameOrPrefix(string $name): ?\XF\Entity\User
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $exact = $this->finder('XF:User')
            ->where('username', $name)
            ->fetchOne();
        if ($exact) {
            return $exact;
        }

        // Пользователь мог набрать лишь часть после @. Линкуем только при ОДНОЗНАЧНОМ совпадении.
        $escaped = addcslashes($name, '\\%_');
        $candidates = $this->finder('XF:User')
            ->whereSql('username LIKE ?', $escaped . '%')
            ->limit(2)
            ->fetch();
        if ($candidates->count() === 1) {
            foreach ($candidates as $u) {
                return $u;
            }
        }

        return null;
    }
}
