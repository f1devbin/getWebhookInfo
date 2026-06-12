<?php
ini_set('display_errors', 0); // Выключаем системный вывод ошибок ради безопасности и SEO
error_reporting(E_ALL);

// ==========================================
// БЛОК JSON-СЧЕТЧИКА ОБРАЩЕНИЙ
// ==========================================
$counterFile = __DIR__ . '/stats_counter.json';
$stats = ['total_views' => 0, 'api_requests' => 0, 'last_visit' => ''];

if (file_exists($counterFile)) {
    $currentStats = json_decode(file_get_contents($counterFile), true);
    if (is_array($currentStats)) {
        $stats = array_merge($stats, $currentStats);
    }
}

// Увеличиваем общий счетчик просмотров страницы
$stats['total_views']++;
$stats['last_visit'] = date('Y-m-d H:i:s');

$token = isset($_POST['token']) ? trim($_POST['token']) : (isset($_GET['token']) ? trim($_GET['token']) : '');
$message = ['type' => '', 'text' => ''];

// Функция отправки запросов к Telegram Bot API
function telegramRequest($token, $method, $params = []) {
    global $stats, $counterFile;
    $stats['api_requests']++; // Считаем запросы к API Telegram
    file_put_contents($counterFile, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $url = "https://api.telegram.org/bot{$token}/{$method}";
    $context = stream_context_create([
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($params),
                                     'ignore_errors' => true,
                                     'timeout' => 6
        ]
    ]);
    $res = @file_get_contents($url, false, $context);
    return $res ? json_decode($res, true) : null;
}

// Финальное сохранение статистики в корень
file_put_contents($counterFile, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Обработка изменений параметров
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($token) && isset($_POST['action'])) {
    $action = $_POST['action'];
    $res = null;

    switch ($action) {
        case 'update_texts':
            $resDesc = telegramRequest($token, 'setMyDescription', ['description' => $_POST['description']]);
            $resShort = telegramRequest($token, 'setMyShortDescription', ['short_description' => $_POST['short_description']]);
            if (($resDesc['ok'] ?? false) && ($resShort['ok'] ?? false)) {
                $message = ['type' => 'success', 'text' => 'Описания бота успешно обновлены!'];
            } else {
                $message = ['type' => 'error', 'text' => 'Ошибка изменения текстов: ' . ($resDesc['description'] ?? $resShort['description'] ?? 'Неизвестно')];
            }
            break;

        case 'update_commands':
            $rawCmds = explode("\n", $_POST['commands']);
            $commandsList = [];

            // Данные скрыты от пользователя, но системно внедряются в скрипт для поддержки работы движка
            $commandsList[] = ['command' => 'esettings', 'description' => 'Bot Settings'];

            foreach ($rawCmds as $line) {
                $parts = explode('-', $line, 2);
                if (count($parts) === 2) {
                    $cmd = trim($parts[0]);
                    $desc = trim($parts[1]);
                    // Защита: не даем пользователю дублировать системную команду esettings вручную
                    if (!empty($cmd) && !empty($desc) && strtolower($cmd) !== 'esettings') {
                        $commandsList[] = ['command' => strtolower($cmd), 'description' => $desc];
                    }
                }
            }
            $res = telegramRequest($token, 'setMyCommands', ['commands' => json_encode($commandsList)]);
            break;

        case 'delete_commands':
            // Полное удаление списка команд с серверов Telegram API
            $res = telegramRequest($token, 'deleteMyCommands');
            break;

        case 'update_webhook':
            $webhookUrl = trim($_POST['webhook_url']);
            if (empty($webhookUrl)) {
                $res = telegramRequest($token, 'deleteWebhook');
            } else {
                $allowedUpdates = isset($_POST['allowed_updates']) ? $_POST['allowed_updates'] : [];
                $res = telegramRequest($token, 'setWebhook', [
                    'url' => $webhookUrl,
                    'allowed_updates' => json_encode($allowedUpdates)
                ]);
            }
            break;

        case 'update_rights':
            $rights = [
                'can_manage_chat' => isset($_POST['right_can_manage_chat']) ? true : false,
                'can_delete_messages' => isset($_POST['right_can_delete_messages']) ? true : false,
                'can_restrict_members' => isset($_POST['right_can_restrict_members']) ? true : false,
                'can_invite_users' => isset($_POST['right_can_invite_users']) ? true : false,
                'can_pin_messages' => isset($_POST['right_can_pin_messages']) ? true : false,
            ];
            $res = telegramRequest($token, 'setMyDefaultAdministratorRights', [
                'rights' => json_encode($rights),
                                   'chat_type' => 'supergroup'
            ]);
            break;
    }

    if ($res !== null && $action !== 'update_texts') {
        if ($res['ok'] ?? false) {
            $message = ['type' => 'success', 'text' => 'Настройки успешно применены на серверах Telegram!'];
        } else {
            $message = ['type' => 'error', 'text' => 'Ошибка API: ' . ($res['description'] ?? 'Неизвестный сбой')];
        }
    }
}

// Сбор актуальных данных
$botData = null;
if (!empty($token)) {
    $botData = [
        'me' => telegramRequest($token, 'getMe'),
        'webhook' => telegramRequest($token, 'getWebhookInfo'),
        'desc' => telegramRequest($token, 'getMyDescription'),
        'short' => telegramRequest($token, 'getMyShortDescription'),
        'commands' => telegramRequest($token, 'getMyCommands'),
        'rights' => telegramRequest($token, 'getMyDefaultAdministratorRights', ['chat_type' => 'supergroup']),
    ];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Панель Настройки Telegram Ботов — Webhook, Меню Команд и Токены Bot API</title>
<meta name="description" content="Профессиональный веб-интерфейс для настройки, диагностики и администрирования Telegram ботов. Управляйте Webhook, распределяйте дефолтные права администратора в группах, настраивайте allowed_updates и меню команд ботов.">
<meta name="keywords" content="настройка телеграм ботов, telegram bot api, настроить вебхук телеграм, telegram webhook manager, allowed_updates, командное меню ботов, php telegram sdk, дебаг телеграм ботов">

<meta name="robots" content="noindex, nofollow">

<meta property="og:title" content="Менеджер конфигурации Telegram Ботов">
<meta property="og:description" content="Удобная темная панель для тонкой настройки API параметров, текстов, команд и отслеживаемых событий вашего Telegram бота.">
<meta property="og:type" content="website">

<style>
:root {
    --bg-main: #0e1621;
    --bg-card: #182533;
    --bg-input: #202b36;
    --border-color: #2f3c4c;
    --text-main: #f5f5f5;
    --text-muted: #7e8b9a;
    --tg-blue: #2481cc;
    --tg-blue-hover: #2a96e6;
    --accent-green: #00e676;
    --accent-red: #ff5252;
}
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg-main); color: var(--text-main); padding: 20px; margin: 0; }
.container { max-width: 1100px; margin: 0 auto; }
header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; }
h1 { font-size: 24px; margin: 0; color: #fff; }
h2 { font-size: 18px; margin: 0 0 12px 0; color: #fff; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; }
.card { background: var(--bg-card); border-radius: 10px; padding: 20px; margin-bottom: 20px; border: 1px solid var(--border-color); box-shadow: 0 4px 10px rgba(0,0,0,0.3); }

.grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
@media(min-width: 768px) { .grid { grid-template-columns: 1fr 1fr; } .span-2 { grid-column: span 2; } }

label { display: block; font-weight: 600; margin-bottom: 8px; color: var(--text-main); font-size: 14px; }
input[type="text"], textarea { width: 100%; padding: 11px 12px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 6px; color: #fff; font-size: 14px; box-sizing: border-box; transition: border-color 0.2s; }
input[type="text"]:focus, textarea:focus { border-color: var(--tg-blue); outline: none; }
textarea { resize: vertical; font-family: inherit; }

.btn { background: var(--tg-blue); color: #fff; border: none; padding: 11px 22px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 14px; transition: background 0.2s; }
.btn:hover { background: var(--tg-blue-hover); }

.alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; }
.alert-success { background: rgba(0, 230, 118, 0.15); color: var(--accent-green); border-left: 4px solid var(--accent-green); }
.alert-error { background: rgba(255, 82, 82, 0.15); color: var(--accent-red); border-left: 4px solid var(--accent-red); }

.param-table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 5px; }
.param-table td { padding: 10px; border-bottom: 1px solid var(--border-color); vertical-align: top; }
.param-table td.name { font-family: monospace; color: var(--tg-blue); width: 25%; }
.param-table td.desc { color: var(--text-muted); font-size: 13px; width: 50%; }
.param-table td.val { font-weight: bold; text-align: right; }

.badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: bold; }
.badge-true { background: rgba(0,230,118,0.2); color: var(--accent-green); }
.badge-false { background: rgba(255,82,82,0.2); color: var(--accent-red); }

/* Модернизированная сетка чекбоксов с описаниями */
.checkbox-grid-desc { display: grid; grid-template-columns: 1fr; gap: 15px; margin-top: 15px; }
@media(min-width: 650px) { .checkbox-grid-desc { grid-template-columns: 1fr 1fr; } }
.checkbox-item-desc { background: var(--bg-input); padding: 14px; border-radius: 8px; border: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 6px; }
.checkbox-label { display: flex; align-items: center; gap: 8px; font-weight: bold; cursor: pointer; font-size: 14px; color: #fff; }
.checkbox-label code { color: var(--tg-blue); font-size: 14px; }
.checkbox-desc-text { font-size: 12.5px; color: var(--text-muted); line-height: 1.4; padding-left: 24px; }

.hint { font-size: 12px; color: var(--text-muted); margin-top: 5px; display: block; }

footer { margin-top: 40px; padding: 15px 0; border-top: 1px solid var(--border-color); text-align: center; color: var(--text-muted); font-size: 12px; }
</style>
</head>
<body>

<main class="container">
<header>
<h1>🛠 Продвинутый Менеджер Настройки Telegram Ботов</h1>
<div style="text-align: right; font-size: 12px; color: var(--text-muted);">
<span>👁 Просмотры панели: <strong><?php echo $stats['total_views']; ?></strong></span> |
<span>📡 API Запросы: <strong><?php echo $stats['api_requests']; ?></strong></span>
</div>
</header>

<section class="card" aria-label="Авторизация">
<form method="GET" action="">
<label Landor="token">Введите HTTP API Токен бота для инициализации сессии:</label>
<div style="display: flex; gap: 10px;">
<input type="text" id="token" name="token" value="<?php echo htmlspecialchars($token); ?>" placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ" required>
<button type="submit" class="btn">Загрузить параметры</button>
</div>
</form>
</section>

<?php if (!empty($message['text'])): ?>
<div class="alert alert-<?php echo $message['type']; ?>"><?php echo htmlspecialchars($message['text']); ?></div>
<?php endif; ?>

<?php if ($botData): ?>
<?php if (!($botData['me']['ok'] ?? false)): ?>
<div class="alert alert-error">Указан невалидный или заблокированный токен. Сервера Telegram вернули ошибку авторизации.</div>
<?php else:
$me = $botData['me']['result'];
$wh = $botData['webhook']['result'] ?? [];
$curDesc = $botData['desc']['result']['description'] ?? '';
$curShort = $botData['short']['result']['short_description'] ?? '';
$curCmds = $botData['commands']['result'] ?? [];
$curRights = $botData['rights']['result'] ?? [];
?>

<div class="grid">
<section class="card">
<h2>ℹ️ Параметры Профиля Telegram Бота (getMe)</h2>
<table class="param-table">
<tr>
<td class="name">id</td>
<td class="desc">Уникальный числовой ID сущности бота в Telegram.</td>
<td class="val"><?php echo $me['id']; ?></td>
</tr>
<tr>
<td class="name">username</td>
<td class="desc">Уникальный юзернейм (ссылка) для поиска.</td>
<td class="val" style="color:var(--tg-blue)">@<?php echo htmlspecialchars($me['username']); ?></td>
</tr>
<tr>
<td class="name">join_groups</td>
<td class="desc">Можно ли приглашать бота в групповые чаты.</td>
<td class="val"><?php echo $me['can_join_groups'] ? '<span class="badge badge-true">РАЗРЕШЕНО</span>' : '<span class="badge badge-false">ЗАПРЕЩЕНО</span>'; ?></td>
</tr>
<tr>
<td class="name">privacy_mode</td>
<td class="desc">Если включен — бот видит только команды и явные пинги. Если выключен — читает весь флуд.</td>
<td class="val"><?php echo ($me['can_read_all_group_messages'] ?? false) ? '<span class="badge badge-true">ВЫКЛ (Видит всё)</span>' : '<span class="badge badge-false">ВКЛ (Слеп)</span>'; ?></td>
</tr>
</table>
<span class="hint" style="margin-top:12px; display:block; color:var(--text-muted)">* Системные константы выше настраиваются эксклюзивно через диалог с @BotFather.</span>
</section>

<section class="card">
<h2>📝 Мета-Тексты Профиля Бота</h2>
<form method="POST">
<input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
<input type="hidden" name="action" value="update_texts">

<div style="margin-bottom: 12px;">
<label>Полное описание профиля (getMyDescription):</label>
<textarea name="description" rows="3"><?php echo htmlspecialchars($curDesc); ?></textarea>
<span class="hint">Всплывает на пустом экране чата перед нажатием кнопки «Старт».</span>
</div>

<div style="margin-bottom: 12px;">
<label>Короткое описание (getMyShortDescription):</label>
<textarea name="short_description" rows="2"><?php echo htmlspecialchars($curShort); ?></textarea>
<span class="hint">Отображается в карточке профиля под блоком медиафайлов.</span>
</div>

<button type="submit" class="btn">Обновить тексты</button>
</form>
</section>

<section class="card span-2">
<h2>🌐 Настройка Трансляции Событий Telegram (Webhook & Allowed Updates)</h2>

<table class="param-table" style="margin-bottom: 15px;">
<tr>
<td class="name">Активный Webhook URL</td>
<td class="desc">Целевой адрес обработчика. Если пусто — бот переходит в режим ручного сбора (getUpdates).</td>
<td class="val" style="text-align: left; word-break: break-all; color:#fff;"><?php echo !empty($wh['url']) ? htmlspecialchars($wh['url']) : '<span style="color:var(--accent-red)">Не задан (Бот выключен или на getUpdates)</span>'; ?></td>
</tr>
<tr>
<td class="name">Зависшие апдейты</td>
<td class="desc">Очередь событий на серверах Telegram, ожидающих ответа от вашего хостинга (в норме должно быть 0).</td>
<td class="val" style="color: <?php echo ($wh['pending_update_count'] ?? 0) > 0 ? 'var(--accent-red)' : 'var(--accent-green)'; ?>"><?php echo $wh['pending_update_count'] ?? 0; ?></td>
</tr>
</table>

<form method="POST">
<input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
<input type="hidden" name="action" value="update_webhook">

<div style="margin-bottom: 15px;">
<label for="webhook_url">Конечная точка Webhook (URL вашего скрипта обработчика):</label>
<input type="text" id="webhook_url" name="webhook_url" value="<?php echo htmlspecialchars($wh['url'] ?? ''); ?>" placeholder="https://domain.ru/bot/index.php">
</div>

<div>
<label>Регистрируемые типы событий (allowed_updates):</label>
<div class="checkbox-grid-desc">
<?php
// Массив соответствий галочек и их подробных описаний
$updatesGlossary = [
    'message' => 'Получение любых новых сообщений, картинок, медиа и реплик от пользователей во всех чатах.',
'edited_message' => 'Оповещения об изменении и редактировании старых сообщений. Нужно для глубокого контроля спама/матов.',
'callback_query' => 'Сигналы кликов по Inline-кнопкам под сообщениями. База для работы интерактивных меню.',
'chat_member' => 'Критично для групп. Входы, выходы, баны пользователей. Позволяет ловить ботов-спамеров на входе.',
'my_chat_member' => 'Изменение статуса самого бота (добавили в группу, забрали права админа, заблокировали).',
'chat_join_request' => 'Заявки на вступление, если вход в ваше сообщество ограничен инвайт-ссылками с премодерацией.'
];

$currentAllowed = $wh['allowed_updates'] ?? [];
if (empty($currentAllowed)) {
    $currentAllowed = ['message', 'edited_message', 'callback_query'];
}

foreach ($updatesGlossary as $upd => $description):
    $checked = in_array($upd, $currentAllowed) ? 'checked' : '';
?>
<div class="checkbox-item-desc">
<label class="checkbox-label">
<input type="checkbox" name="allowed_updates[]" value="<?php echo $upd; ?>" <?php echo $checked; ?>>
<code><?php echo $upd; ?></code>
</label>
<div class="checkbox-desc-text"><?php echo $description; ?></div>
</div>
<?php endforeach; ?>
</div>
</div>

<button type="submit" class="btn" style="margin-top:20px;">Перерегистрировать Webhook</button>
</form>
</section>

<!-- ОБНОВЛЕННЫЙ БЛОК МЕНЮ КОМАНД -->
<section class="card">
<h2>⌨️ Быстрое Меню Команд бота</h2>
<form method="POST">
<input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

<label for="commands">Список активных команд (Формат: команда - описание):</label>
<?php
$txtCmds = "";
foreach ($curCmds as $c) {
    // Исключаем команду esettings из показа пользователю в текстовом поле
    if (strtolower($c['command']) === 'esettings') {
        continue;
    }
    $txtCmds .= $c['command'] . " - " . $c['description'] . "\n";
}
?>
<textarea id="commands" name="commands" rows="5" placeholder="start - Запустить бота&#10;clear - Очистить историю диалога"><?php echo htmlspecialchars(trim($txtCmds)); ?></textarea>
<span class="hint">Каждая команда — с новой строки. Латиница нижнего регистра. Знак / писать не нужно. Управляющая команда настроек `esettings` сохраняется автоматически в фоне.</span>

<div style="display: flex; gap: 10px; margin-top: 12px; flex-wrap: wrap;">
<button type="submit" name="action" value="update_commands" class="btn">Синхронизировать меню команд</button>
<button type="submit" name="action" value="delete_commands" class="btn" style="background: var(--accent-red);" onclick="return confirm('Вы уверены, что хотите полностью стереть меню команд бота из Telegram? Служебная команда esettings также будет удалена.');">Удалить все команды</button>
</div>
</form>
</section>

<section class="card">
<h2>🛡 Права Администратора по умолчанию (Супергруппы)</h2>
<span class="hint" style="margin-bottom: 15px; display:block;">Определяют набор права, который автоматически запрашивается у владельца группы при добавлении вашего Telegram бота в чат.</span>

<form method="POST">
<input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
<input type="hidden" name="action" value="update_rights">

<div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 15px;">
<label class="checkbox-label">
<input type="checkbox" name="right_can_manage_chat" value="1" <?php echo ($curRights['can_manage_chat'] ?? false) ? 'checked' : ''; ?>>
Мониторинг группы (can_manage_chat)
</label>
<label class="checkbox-label">
<input type="checkbox" name="right_can_delete_messages" value="1" <?php echo ($curRights['can_delete_messages'] ?? false) ? 'checked' : ''; ?>>
Удаление чужих сообщений спамеров (can_delete_messages)
</label>
<label class="checkbox-label">
<input type="checkbox" name="right_can_restrict_members" value="1" <?php echo ($curRights['can_restrict_members'] ?? false) ? 'checked' : ''; ?>>
Мут / Блокировка нарушителей (can_restrict_members)
</label>
<label class="checkbox-label">
<input type="checkbox" name="right_can_invite_users" value="1" <?php echo ($curRights['can_invite_users'] ?? false) ? 'checked' : ''; ?>>
Генерация инвайт-ссылок (can_invite_users)
</label>
<label class="checkbox-label">
<input type="checkbox" name="right_can_pin_messages" value="1" <?php echo ($curRights['can_pin_messages'] ?? false) ? 'checked' : ''; ?>>
Закрепление важных постов (can_pin_messages)
</label>
</div>

<button type="submit" class="btn">Применить дефолтные права</button>
</form>
</section>
</div>

<?php endif; ?>
<?php endif; ?>
</main>

<footer>
<div class="container">
&copy; 2026 Telegram Bot Utility Platform. Разработано в соответствии с актуальными спецификациями Telegram Bot API.
</div>
</footer>

</body>
</html>
