# Guild Manager (XenForo 2.2) - CLI Guide

## Документация

- Руководство для новичка по страницам/полям/правам: `USER_GUIDE_RU.md`

Этот файл содержит быстрые команды для запуска backend-сценариев без UI.

## Где запускать команды

Запускайте команды из корня XenForo (там, где лежит `cmd.php`):

```bash
php cmd.php <command>
```

## Полный список CLI-команд

- `guild-manager:create-guild`
- `guild-manager:transfer-leader`
- `guild-manager:add-treasury`
- `guild-manager:recalc`
- `guild-manager:invite-member`
- `guild-manager:accept-invite`
- `guild-manager:add-followers`
- `guild-manager:add-reputation`
- `guild-manager:update-description`

---

## 1) Создать гильдию

Команда:

```bash
php cmd.php guild-manager:create-guild <actor-user-id> "<title>" "<description>"
```

Ready-to-copy:

```bash
php cmd.php guild-manager:create-guild 1 "Night Watch" "Main RP guild"
```

---

## 2) Передать лидерство

Команда:

```bash
php cmd.php guild-manager:transfer-leader <guild-id> <actor-user-id> <new-leader-user-id>
```

Ready-to-copy:

```bash
php cmd.php guild-manager:transfer-leader 10 1 25
```

---

## 3) Казна: пополнение / списание

Команда:

```bash
php cmd.php guild-manager:add-treasury <guild-id> <actor-user-id> "<character-name>" "<source-url>" <amount> [deposit|withdraw] "<reason>"
```

Примечания:
- `amount` передавать положительным числом.
- Для `withdraw` сервис сам запишет отрицательную сумму в лог.
- `source-url` должен начинаться с `https://`.

Ready-to-copy:

```bash
php cmd.php guild-manager:add-treasury 10 1 "Arthas" "https://example.com/proof" 500 deposit "Raid reward"
php cmd.php guild-manager:add-treasury 10 1 "Arthas" "https://example.com/proof" 150 withdraw "Craft expenses"
```

---

## 4) Пересчет агрегатов

Команда (одна гильдия):

```bash
php cmd.php guild-manager:recalc <guild-id>
```

Команда (все гильдии):

```bash
php cmd.php guild-manager:recalc
```

Ready-to-copy:

```bash
php cmd.php guild-manager:recalc 10
php cmd.php guild-manager:recalc
```

---

## 5) Пригласить участника

Команда:

```bash
php cmd.php guild-manager:invite-member <guild-id> <actor-user-id> <target-user-id>
```

Ready-to-copy:

```bash
php cmd.php guild-manager:invite-member 10 1 25
```

---

## 6) Принять приглашение

Команда:

```bash
php cmd.php guild-manager:accept-invite <guild-id> <actor-user-id>
```

Ready-to-copy:

```bash
php cmd.php guild-manager:accept-invite 10 25
```

---

## 7) Последователи: добавление / потеря

Команда:

```bash
php cmd.php guild-manager:add-followers <guild-id> <actor-user-id> "<character-name>" "<source-url>" <amount> [gain|loss] "<event-date-text>"
```

Примечания:
- `amount` передавать положительным числом.
- Для `loss` сервис сам запишет отрицательную сумму в лог.
- `source-url` должен начинаться с `https://`.

Ready-to-copy:

```bash
php cmd.php guild-manager:add-followers 10 1 "Arthas" "https://example.com/proof" 30 gain "2026-04-25"
php cmd.php guild-manager:add-followers 10 1 "Arthas" "https://example.com/proof" 12 loss "Penalty event"
```

---

## 8) Репутация: добавление / потеря

Команда:

```bash
php cmd.php guild-manager:add-reputation <guild-id> <actor-user-id> <region-key> "<character-name>" "<source-url>" <amount> [gain|loss] "<faction-name>"
```

`region-key`:
- `aramidis`
- `union`
- `korzus`

Примечания:
- `amount` передавать положительным числом.
- Для `loss` сервис сам запишет отрицательную сумму в лог.
- `source-url` должен начинаться с `https://`.

Ready-to-copy:

```bash
php cmd.php guild-manager:add-reputation 10 1 aramidis "Arthas" "https://example.com/proof" 50 gain "Iron League"
php cmd.php guild-manager:add-reputation 10 1 union "Arthas" "https://example.com/proof" 20 loss "Silver Court"
```

---

## 9) Обновить описание гильдии (BBCode + лог истории)

Команда:

```bash
php cmd.php guild-manager:update-description <guild-id> <actor-user-id> "<description-bbcode>" "<change-note>"
```

Примечания:
- описание сохраняется как исходный BBCode (`description`);
- HTML-кэш сохраняется в `description_rendered`;
- запись истории добавляется в `xf_guild_description_log`.

Ready-to-copy:

```bash
php cmd.php guild-manager:update-description 10 1 "[B]Устав[/B]\n1) Уважение\n2) Актив" "добавлен устав"
```

---

## Быстрый smoke-тест (минимальный сценарий)

```bash
php cmd.php guild-manager:create-guild 1 "Night Watch" "Main RP guild"
php cmd.php guild-manager:invite-member 10 1 25
php cmd.php guild-manager:accept-invite 10 25
php cmd.php guild-manager:add-treasury 10 1 "Arthas" "https://example.com/proof" 500 deposit "Raid reward"
php cmd.php guild-manager:add-followers 10 1 "Arthas" "https://example.com/proof" 30 gain "2026-04-25"
php cmd.php guild-manager:add-reputation 10 1 aramidis "Arthas" "https://example.com/proof" 50 gain "Iron League"
php cmd.php guild-manager:update-description 10 1 "[B]Устав[/B]\n1) Уважение\n2) Актив" "добавлен устав"
php cmd.php guild-manager:recalc 10
```

Замените `guild-id` и `user-id` на реальные ID вашего форума.

---

## Типовые ошибки и как исправить

### 1) `no_permission`

Почему возникает:
- у пользователя (`actor-user-id`) нет нужного XF-права;
- пользователь не лидер гильдии;
- роль участника не дает нужного действия (через матрицу `PermissionPreset`).

Как проверить:
- убедитесь, что у пользователя есть нужные права группы:
  - `manageGuildAny`, `manageTreasuryAny`, `manageMembersAny`, `manageReputationAny`;
- проверьте, что пользователь состоит в `xf_guild_member` и имеет корректную роль;
- проверьте `leader_user_id` в `xf_guild`.

Что сделать:
- выдать нужное право группе пользователя в ACP;
- назначить пользователя лидером или офицером (в зависимости от операции);
- повторить команду.

### 2) `Guild not found.`

Почему возникает:
- передан несуществующий `guild-id`.

Как проверить:
- взять актуальный ID из вывода `guild-manager:create-guild`;
- проверить наличие записи в таблице `xf_guild`.

Что сделать:
- использовать корректный `guild-id`;
- если гильдия удалена, создать новую.

### 3) `Actor user not found.` / `Target user not found.` / `New leader user not found.`

Почему возникает:
- передан несуществующий `user-id`.

Как проверить:
- убедиться, что пользователь существует в `xf_user`;
- проверить, что ID не перепутан.

Что сделать:
- подставить правильный `user-id`.

### 4) Ошибка по `source_url` (неверный URL)

Почему возникает:
- `source_url` не начинается с `https://`;
- URL не проходит базовую валидацию формата.

Как проверить:
- строка должна выглядеть как `https://example.com/proof`.

Что сделать:
- передавать только полный HTTPS URL;
- убрать пробелы и лишние символы в конце.

### 5) Ошибка по `amount` / знаку суммы

Почему возникает:
- передан ноль;
- знак суммы не соответствует `operation_type`.

Как работает в фасаде:
- для CLI в фасаде ожидается **положительный** `amount`;
- `withdraw/loss` преобразуются в отрицательное значение автоматически.

Что сделать:
- передавать `amount > 0`;
- для списаний использовать `withdraw` или `loss`, а не отрицательное число вручную.

### 6) `Cannot remove or ban the last active guild leader.`

Почему возникает:
- попытка удалить/забанить/понизить последнего активного лидера гильдии.

Что сделать:
- сначала назначить другого активного лидера (`transfer-leader`);
- затем повторить удаление/бан/понижение.

### 7) Команда не найдена (`Command ... is not defined`)

Почему возникает:
- аддон не установлен/не обновлен в XenForo;
- кэш классов не актуален.

Что сделать:
- в ACP выполнить установку/апгрейд аддона;
- пересобрать кэши XenForo и повторить запуск через `php cmd.php`.

### 8) Операция прошла, но итоговые числа не совпадают

Почему возникает:
- старые данные в логах/кэше после ручных правок БД или миграций.

Что сделать:
- выполнить пересчет:

```bash
php cmd.php guild-manager:recalc <guild-id>
```

или для всех гильдий:

```bash
php cmd.php guild-manager:recalc
```
