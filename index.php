<?php
/**
 * Advanced Telegram Bot Configuration Manager (Полная интерактивная версия)
 * GitHub: https://github.com/f1devbin/getWebhookInfo
 */

session_start();

// 1. Локальная статистика
$statsFile = 'stats_counter.json';
$stats = file_exists($statsFile) ? json_decode(file_get_contents($statsFile), true) : ['views' => 0, 'requests' => 0, 'last_access' => ''];
$stats['views']++;
$stats['last_access'] = date('d.m.Y H:i:s');
file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$token = $_POST['token'] ?? $_SESSION['token'] ?? '';
if (!empty($_POST['token'])) {
    $_SESSION['token'] = $_POST['token'];
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

function telegramApi($token, $method, $params = []) {
    $url = "https://api.telegram.org/bot{$token}/{$method}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    if (!empty($params)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$botData = null;
$error = '';
$actionsResult = '';

if ($token) {
    $stats['requests']++;
    file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));

    // 2. ОБРАБОТЧИК ВСЕХ ДЕЙСТВИЙ ИЗ ФОРМ (ЗАПИСЬ В API)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'set_webhook':
                $webhookParams = [
                    'url' => $_POST['webhook_url'] ?? '',
                    'max_connections' => (int)($_POST['max_connections'] ?? 40),
                    'allowed_updates' => json_encode($_POST['allowed_updates'] ?? [])
                ];
                if (!empty($_POST['ip_address'])) {
                    $webhookParams['ip_address'] = $_POST['ip_address'];
                }
                if (!empty($_POST['secret_token'])) {
                    $webhookParams['secret_token'] = $_POST['secret_token'];
                }
                if (isset($_POST['drop_pending_setup'])) {
                    $webhookParams['drop_pending_updates'] = true;
                }

                $res = telegramApi($token, 'setWebhook', $webhookParams);
                $actionsResult = ($res['ok'] ?? false) ? '✅ Webhook успешно обновлен!' : '❌ Ошибка Webhook: ' . ($res['description'] ?? 'Неизвестная ошибка');
                break;

            case 'clear_pending':
                $currentUrl = $_POST['current_url'] ?? '';
                if ($currentUrl) {
                    $res = telegramApi($token, 'setWebhook', ['url' => $currentUrl, 'drop_pending_updates' => true]);
                    $actionsResult = ($res['ok'] ?? false) ? '✅ Очередь застрявших сообщений успешно очищена!' : '❌ Ошибка очистки: ' . ($res['description'] ?? 'Неизвестная ошибка');
                }
                break;

            case 'delete_webhook':
                $res = telegramApi($token, 'deleteWebhook', ['drop_pending_updates' => isset($_POST['drop_pending'])]);
                $actionsResult = ($res['ok'] ?? false) ? '✅ Webhook полностью удален! Бот переведен в режим getUpdates.' : '❌ Ошибка удаления: ' . ($res['description'] ?? 'Неизвестная ошибка');
                break;

            case 'set_profile':
                $resName = telegramApi($token, 'setMyName', ['name' => $_POST['bot_name'] ?? '']);
                $resShort = telegramApi($token, 'setMyShortDescription', ['short_description' => $_POST['bot_short_desc'] ?? '']);
                $resFull = telegramApi($token, 'setMyDescription', ['description' => $_POST['bot_full_desc'] ?? '']);

                if (($resName['ok']??false) && ($resShort['ok']??false) && ($resFull['ok']??false)) {
                    $actionsResult = '✅ Профиль бота (Имя, Короткое и Полное описания) успешно обновлен!';
                } else {
                    $actionsResult = '⚠️ Настройки профиля сохранены частично. Проверьте логи.';
                }
                break;

            case 'set_commands':
                $lines = explode("\n", $_POST['commands_text'] ?? '');
                $commands = [];
                foreach ($lines as $line) {
                    $parts = explode('-', $line, 2);
                    if (count($parts) === 2) {
                        $commands[] = [
                            'command' => trim(str_replace('/', '', $parts[0])),
                            'description' => trim($parts[1])
                        ];
                    }
                }
                $res = telegramApi($token, 'setMyCommands', ['commands' => json_encode($commands)]);
                $actionsResult = ($res['ok'] ?? false) ? '✅ Меню команд успешно синхронизировано!' : '❌ Ошибка команд: ' . ($res['description'] ?? 'Неизвестная ошибка');
                break;

            case 'delete_commands':
                $res = telegramApi($token, 'deleteMyCommands');
                $actionsResult = ($res['ok'] ?? false) ? '✅ Все команды меню успешно удалены!' : '❌ Ошибка: ' . ($res['description'] ?? 'Неизвестная ошибка');
                break;

            case 'set_rights':
                $rights = [
                    'is_anonymous' => isset($_POST['right_is_anonymous']),
                    'can_manage_chat' => isset($_POST['right_can_manage_chat']),
                    'can_delete_messages' => isset($_POST['right_can_delete_messages']),
                    'can_manage_video_chats' => isset($_POST['right_can_manage_video_chats']),
                    'can_restrict_members' => isset($_POST['right_can_restrict_members']),
                    'can_promote_members' => isset($_POST['right_can_promote_members']),
                    'can_change_info' => isset($_POST['right_can_change_info']),
                    'can_invite_users' => isset($_POST['right_can_invite_users']),
                    'can_post_messages' => isset($_POST['right_can_post_messages']),
                    'can_edit_messages' => isset($_POST['right_can_edit_messages']),
                    'can_pin_messages' => isset($_POST['right_can_pin_messages']),
                    'can_post_stories' => isset($_POST['right_can_post_stories']),
                    'can_edit_stories' => isset($_POST['right_can_edit_stories']),
                    'can_delete_stories' => isset($_POST['right_can_delete_stories']),
                    'can_manage_topics' => isset($_POST['right_can_manage_topics'])
                ];
                $res = telegramApi($token, 'setMyDefaultAdministratorRights', [
                    'rights' => json_encode($rights)
                ]);
                $actionsResult = ($res['ok'] ?? false) ? '✅ Расширенные дефолтные права администратора успешно обновлены!' : '❌ Ошибка обновления прав: ' . ($res['description'] ?? 'Неизвестная ошибка');
                break;
        }
    }

    // 3. СБОР СВЕЖИХ ДАННЫХ ДЛЯ ОТОБРАЖЕНИЯ И ПРЕДЗАПОЛНЕНИЯ
    $me = telegramApi($token, 'getMe');
    if ($me['ok'] ?? false) {
        $botData = [
            'me' => $me['result'],
            'webhook' => telegramApi($token, 'getWebhookInfo')['result'] ?? [],
            'commands' => telegramApi($token, 'getMyCommands')['result'] ?? [],
            'description' => telegramApi($token, 'getMyDescription')['result'] ?? [],
            'short_description' => telegramApi($token, 'getMyShortDescription')['result'] ?? [],
            'rights' => telegramApi($token, 'getMyDefaultAdministratorRights')['result'] ?? [],
            'avatar' => 'https://via.placeholder.com/150?text=No+Avatar'
        ];

        // Получение аватара
        $photos = telegramApi($token, 'getUserProfilePhotos', ['user_id' => $botData['me']['id'], 'limit' => 1]);
        if (!empty($photos['result']['photos'][0])) {
            $fileId = end($photos['result']['photos'][0])['file_id'];
            $fileInfo = telegramApi($token, 'getFile', ['file_id' => $fileId]);
            if (!empty($fileInfo['result']['file_path'])) {
                $botData['avatar'] = "https://api.telegram.org/file/bot{$token}/{$fileInfo['result']['file_path']}";
            }
        }

        // Форматируем команды для предзаполнения textarea
        $commandsTextarea = '';
        if (!empty($botData['commands'])) {
            foreach ($botData['commands'] as $cmd) {
                $commandsTextarea .= "{$cmd['command']} - {$cmd['description']}\n";
            }
        }
    } else {
        $error = 'Неверный HTTP API Токен.';
        $token = '';
        unset($_SESSION['token']);
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>getWebhookInfo — Live Bot API Manager (Dark Mode)</title>
<style>
:root {
    --tg-blue: #3f9cfb;
    --tg-light-blue: #232e3c;
    --bg: #0e1621;
    --card-bg: #17212b;
    --input-bg: #242f3d;
    --text: #f5f5f5;
    --text-muted: #8b9eb0;
    --border: #2b3947;
    --success: #44b35b;
    --danger: #e65f5c;
}
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 0; }
header { background: var(--card-bg); border-bottom: 1px solid var(--border); padding: 15px 0; }
.container { width: 92%; max-width: 1200px; margin: 0 auto; }
.header-flex { display: flex; justify-content: space-between; align-items: center; }
h1 { margin: 0; font-size: 1.5rem; color: var(--tg-blue); }
.logout-btn { color: var(--danger); text-decoration: none; font-weight: bold; font-size: 0.9rem; }
.login-box { max-width: 500px; margin: 100px auto; background: var(--card-bg); padding: 30px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
.form-group { margin-bottom: 18px; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 0.9rem; color: var(--text); }
input[type="text"], input[type="number"], textarea { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; font-size: 0.95rem; background-color: var(--input-bg); color: var(--text); outline: none; }
input:focus, textarea:focus { border-color: var(--tg-blue); }
textarea { resize: vertical; font-family: inherit; }
.btn { background: var(--tg-blue); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 0.95rem; }
.btn:hover { background: #2b80d9; }
.btn-danger { background: var(--danger); color: white; }
.btn-danger:hover { background: #cf4e4b; }
.btn-secondary { background: #3a4a5a; color: white; }
.btn-secondary:hover { background: #4a5c6e; }
.alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; }
.alert-danger { background: #3d2023; color: #e8868a; border: 1px solid #592a2f; }
.alert-info { background: #1c3851; color: #7cb8ef; border: 1px solid #264a6f; }
.layout { display: grid; grid-template-columns: 300px 1fr; gap: 25px; margin-top: 25px; margin-bottom: 80px; }
@media(max-width: 768px) { .layout { grid-template-columns: 1fr; } }
.sidebar { background: var(--card-bg); border-radius: 12px; padding: 20px; border: 1px solid var(--border); text-align: center; height: fit-content; }
.avatar { width: 110px; height: 110px; border-radius: 50%; object-fit: cover; margin-bottom: 12px; border: 3px solid var(--tg-light-blue); background: var(--input-bg); }
.bot-name { font-size: 1.2rem; font-weight: bold; }
.bot-username { color: var(--tg-blue); text-decoration: none; display: block; margin-bottom: 15px; font-weight: 500; }
.bot-meta-preview { text-align: left; font-size: 0.85rem; border-top: 1px solid var(--border); padding-top: 12px; }
.bot-meta-preview p { margin: 6px 0; }
.tabs { display: flex; gap: 8px; border-bottom: 2px solid var(--border); margin-bottom: 20px; flex-wrap: wrap; }
.tab-link { padding: 10px 16px; cursor: pointer; background: none; border: none; font-size: 0.95rem; font-weight: 500; color: var(--text-muted); border-bottom: 2px solid transparent; margin-bottom: -2px; transition: 0.2s; }
.tab-link:hover { color: var(--text); }
.tab-link.active { color: var(--tg-blue); border-bottom-color: var(--tg-blue); }
.tab-content { display: none; background: var(--card-bg); border-radius: 12px; padding: 25px; border: 1px solid var(--border); }
.tab-content.active { display: block; }
.info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px; }
.info-card { background: var(--input-bg); padding: 12px; border-radius: 8px; border: 1px solid var(--border); }
.info-card small { color: var(--text-muted); display: block; font-size: 0.75rem; margin-bottom: 2px; }
.info-card span { font-weight: bold; font-size: 0.9rem; word-break: break-all; color: var(--text); }
.updates-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px; margin: 15px 0; }
.checkbox-label { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; background: var(--input-bg); padding: 6px 10px; border-radius: 6px; border: 1px solid var(--border); cursor: pointer; color: var(--text); }
.checkbox-label:hover { border-color: var(--tg-blue); }
footer { background: var(--card-bg); border-top: 1px solid var(--border); padding: 15px 0; text-align: center; font-size: 0.85rem; color: var(--text-muted); position: fixed; bottom: 0; width: 100%; z-index: 10; }
</style>
</head>
<body>

<header>
<div class="container header-flex">
<h1>🛠 getWebhookInfo <span style="font-size:0.8rem; color:var(--text-muted)">v2.3.0 Max Params</span></h1>
<?php if ($token): ?> <a href="?logout=1" class="logout-btn">Выйти из панели 🚪</a> <?php endif; ?>
</div>
</header>

<div class="container">
<?php if (!$token): ?>
<div class="login-box">
<h2 style="margin-top:0; text-align:center;">Авторизация бота</h2>
<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<form method="POST">
<div class="form-group">
<label>HTTP API Token бота:</label>
<input type="text" name="token" placeholder="123456789:ABCdefGh..." required>
</div>
<button type="submit" class="btn" style="width:100%;">Инициализировать шлюз</button>
</form>
</div>
<?php else: ?>

<?php if ($actionsResult): ?><div class="alert alert-info" style="margin-top:20px;"><?= $actionsResult ?></div><?php endif; ?>

<div class="layout">
<div class="sidebar">
<img src="<?= $botData['avatar'] ?>" class="avatar">
<div class="bot-name"><?= htmlspecialchars($botData['me']['first_name']) ?></div>
<a href="https://t.me/<?= $botData['me']['username'] ?>" target="_blank" class="bot-username">@<?= $botData['me']['username'] ?></a>

<div class="bot-meta-preview">
<p>🆔 <strong>ID:</strong> <code><?= $botData['me']['id'] ?></code></p>
<p>🌐 <strong>Webhook:</strong> <?= !empty($botData['webhook']['url']) ? '<span style="color:var(--success)">Включен</span>' : '<span style="color:var(--danger)">Выключен</span>' ?></p>
</div>

<div class="bot-meta-preview" style="margin-top:15px; padding-top:15px;">
<div style="font-size:0.9rem; font-weight:bold; margin-bottom:10px; color:var(--tg-blue);">Полные параметры getMe:</div>
<p>🤖 <strong>Это бот:</strong> <?= ($botData['me']['is_bot'] ?? false) ? '<span style="color:var(--success)">Да</span>' : '<span style="color:var(--text-muted)">Нет</span>' ?></p>
<?php if (!empty($botData['me']['last_name'])): ?>
<p>👤 <strong>Фамилия:</strong> <?= htmlspecialchars($botData['me']['last_name']) ?></p>
<?php endif; ?>
<?php if (!empty($botData['me']['language_code'])): ?>
<p>🌍 <strong>Язык (lang_code):</strong> <?= htmlspecialchars($botData['me']['language_code']) ?></p>
<?php endif; ?>
<p>🚪 <strong>Вход в группы:</strong> <?= ($botData['me']['can_join_groups'] ?? false) ? '<span style="color:var(--success)">Да</span>' : '<span style="color:var(--text-muted)">Нет</span>' ?></p>
<p>📖 <strong>Чтение всех сообщений:</strong> <?= ($botData['me']['can_read_all_group_messages'] ?? false) ? '<span style="color:var(--success)">Да</span>' : '<span style="color:var(--text-muted)">Нет</span>' ?></p>
<p>🔎 <strong>Inline режим:</strong> <?= ($botData['me']['supports_inline_queries'] ?? false) ? '<span style="color:var(--success)">Да</span>' : '<span style="color:var(--text-muted)">Нет</span>' ?></p>
<p>💼 <strong>Telegram Business:</strong> <?= ($botData['me']['can_connect_to_business'] ?? false) ? '<span style="color:var(--success)">Да</span>' : '<span style="color:var(--text-muted)">Нет</span>' ?></p>
<p>📱 <strong>Main Web App:</strong> <?= ($botData['me']['has_main_web_app'] ?? false) ? '<span style="color:var(--success)">Да</span>' : '<span style="color:var(--text-muted)">Нет</span>' ?></p>
<p>📎 <strong>Меню вложений:</strong> <?= ($botData['me']['added_to_attachment_menu'] ?? false) ? '<span style="color:var(--success)">Да</span>' : '<span style="color:var(--text-muted)">Нет</span>' ?></p>
</div>
</div>

<div class="main-content">
<div class="tabs">
<button class="tab-link active" onclick="openTab(event, 'tab-webhook')">🌐 Управление Webhook</button>
<button class="tab-link" onclick="openTab(event, 'tab-profile')">📝 Профиль</button>
<button class="tab-link" onclick="openTab(event, 'tab-commands')">📜 Меню и Права</button>
<button class="tab-link" onclick="openTab(event, 'tab-json')">📊 JSON Статистика</button>
</div>

<div id="tab-webhook" class="tab-content active">
<h3>Мониторинг статуса Webhook</h3>
<div class="info-grid">
<div class="info-card"><small>Текущий URL адреса</small><span style="color:<?= !empty($botData['webhook']['url'])?'var(--success)':'var(--danger)'?>;"><?= htmlspecialchars($botData['webhook']['url'] ?? 'Не установлен') ?></span></div>
<div class="info-card"><small>Сообщений в очереди</small><span style="color:<?=($botData['webhook']['pending_update_count']??0)>0?'var(--danger)':'inherit'?>;"><?= $botData['webhook']['pending_update_count'] ?? 0 ?></span></div>
<div class="info-card"><small>Max Connections</small><span><?= $botData['webhook']['max_connections'] ?? 40 ?></span></div>
<div class="info-card"><small>Установленный IP-адрес</small><span><?= htmlspecialchars($botData['webhook']['ip_address'] ?? 'Определяется DNS') ?></span></div>
<div class="info-card"><small>Кастомный SSL Сертификат</small><span><?= !empty($botData['webhook']['has_custom_certificate']) ? 'Да ✅' : 'Нет' ?></span></div>
</div>

<?php if (isset($botData['webhook']['last_error_message'])): ?>
<div class="alert alert-danger" style="font-size:0.85rem;">
<strong>Последняя ошибка доставки:</strong> <?= htmlspecialchars($botData['webhook']['last_error_message']) ?><br>
<small>🕒 Время сбоя: <?= date('d.m.Y H:i:s', $botData['webhook']['last_error_date']) ?></small>
</div>
<?php endif; ?>

<?php if (isset($botData['webhook']['last_synchronization_error_date'])): ?>
<div class="alert alert-danger" style="font-size:0.85rem;">
<strong>Ошибка синхронизации серверов:</strong> Зафиксирована рассинхронизация.<br>
<small>🕒 Время: <?= date('d.m.Y H:i:s', $botData['webhook']['last_synchronization_error_date']) ?></small>
</div>
<?php endif; ?>

<hr style="border:0; border-top:1px solid var(--border); margin:20px 0;">

<h3>Изменение параметров Webhook</h3>
<form method="POST">
<input type="hidden" name="action" value="set_webhook">
<div class="form-group">
<label>URL Webhook:</label>
<input type="text" name="webhook_url" value="<?= htmlspecialchars($botData['webhook']['url'] ?? '') ?>" required placeholder="https://domain.com/bot_handler.php">
</div>
<div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
<div class="form-group">
<label>IP-адрес (опционально, для обхода DNS):</label>
<input type="text" name="ip_address" value="<?= htmlspecialchars($botData['webhook']['ip_address'] ?? '') ?>" placeholder="Например: 192.168.1.1">
</div>
<div class="form-group">
<label>Max Connections (1-100):</label>
<input type="number" name="max_connections" value="<?= $botData['webhook']['max_connections'] ?? 40 ?>" min="1" max="100">
</div>
</div>
<div class="form-group">
<label>Secret Token (Заголовок X-Telegram-Bot-Api-Secret-Token):</label>
<input type="text" name="secret_token" placeholder="Ваш секретный токен (Скрыт API, только для установки)">
</div>

<label><strong>allowed_updates (Типы принимаемых событий):</strong></label>
<div class="updates-grid">
<?php
$known_types = [
    'message' => 'Сообщения',
'edited_message' => 'Ред. сообщения',
'channel_post' => 'Посты канала',
'edited_channel_post' => 'Ред. посты',
'inline_query' => 'Инлайн-запросы',
'chosen_inline_result' => 'Выбор инлайна',
'callback_query' => 'Нажатия кнопок',
'shipping_query' => 'Запросы доставки',
'pre_checkout_query' => 'Пред-оплата',
'poll' => 'Опросы',
'poll_answer' => 'Ответы в опросах',
'my_chat_member' => 'Статус бота в чате',
'chat_member' => 'Статус участников',
'chat_join_request' => 'Запросы вступления',
'message_reaction' => 'Реакции',
'message_reaction_count' => 'Счетчик реакций',
'chat_boost' => 'Бусты',
'removed_chat_boost' => 'Удаление бустов'
];
$allowed = $botData['webhook']['allowed_updates'] ?? ['message', 'callback_query'];
$all_types = array_unique(array_merge(array_keys($known_types), $allowed));

foreach ($all_types as $type):
    $desc = $known_types[$type] ?? 'Неизвестный параметр';
$checked = in_array($type, $allowed) ? 'checked' : '';
?>
<label class="checkbox-label" title="<?= $type ?>">
<input type="checkbox" name="allowed_updates[]" value="<?= $type ?>" <?= $checked ?>>
<span><?= $type ?> <span style="color:var(--text-muted); font-size:0.85em;">(<?= $desc ?>)</span></span>
</label>
<?php endforeach; ?>
</div>

<div class="form-group" style="margin-top:15px;">
<label class="checkbox-label" style="display:inline-flex;">
<input type="checkbox" name="drop_pending_setup">
<span style="color:var(--danger); font-weight:bold;">Удалить застрявшие обновления при регистрации (drop_pending_updates)</span>
</label>
</div>

<button type="submit" class="btn">Сохранить и обновить Webhook</button>
</form>

<?php if (!empty($botData['webhook']['url'])): ?>
<div style="display:flex; gap:10px; margin-top:15px; border-top:1px solid var(--border); padding-top:15px;">
<form method="POST" style="display:inline;">
<input type="hidden" name="action" value="clear_pending">
<input type="hidden" name="current_url" value="<?= htmlspecialchars($botData['webhook']['url']) ?>">
<button type="submit" class="btn btn-secondary">🧹 Очистить очередь (Drop Pending)</button>
</form>

<form method="POST" style="display:inline;" onsubmit="return confirm('Удалить вебхук? Бот перестанет получать события на этот URL и вернется в режим getUpdates.');">
<input type="hidden" name="action" value="delete_webhook">
<button type="submit" class="btn btn-danger">❌ Полностью удалить Webhook</button>
</form>
</div>
<?php endif; ?>
</div>

<div id="tab-profile" class="tab-content">
<h3>Редактирование профиля и описаний бота</h3>
<form method="POST">
<input type="hidden" name="action" value="set_profile">
<div class="form-group">
<label>Имя бота (Отображаемое имя / first_name):</label>
<input type="text" name="bot_name" value="<?= htmlspecialchars($botData['me']['first_name'] ?? '') ?>" required>
</div>
<div class="form-group">
<label>Короткое описание (Short Description — видно в профиле при шеринге):</label>
<input type="text" name="bot_short_desc" value="<?= htmlspecialchars($botData['short_description']['short_description'] ?? '') ?>" placeholder="Коротко о боте...">
</div>
<div class="form-group">
<label>Полное описание (Description — текст на пустом экране чата `Что умеет этот бот?`):</label>
<textarea name="bot_full_desc" rows="4" placeholder="Полное описание возможностей..."><?= htmlspecialchars($botData['description']['description'] ?? '') ?></textarea>
</div>
<button type="submit" class="btn">Сохранить изменения профиля</button>
</form>
</div>

<div id="tab-commands" class="tab-content">
<h3>Настройка команд меню кнопки «Menu»</h3>
<form method="POST" style="margin-bottom:15px;">
<input type="hidden" name="action" value="set_commands">
<div class="form-group">
<label>Текущий список команд (формат: <code>команда - описание</code>):</label>
<textarea name="commands_text" rows="5" placeholder="start - Запустить бота&#10;help - Помощь"><?= trim($commandsTextarea) ?></textarea>
</div>
<button type="submit" class="btn">Синхронизировать меню команд</button>
</form>

<?php if (!empty($botData['commands'])): ?>
<form method="POST" onsubmit="return confirm('Очистить меню команд?');">
<input type="hidden" name="action" value="delete_commands">
<button type="submit" class="btn btn-danger" style="margin-bottom:20px;">🗑 Стереть все команды</button>
</form>
<?php endif; ?>

<hr style="border:0; border-top:1px solid var(--border); margin:25px 0;">

<h3>🛡 Дефолтные права администратора (ChatAdministratorRights)</h3>
<p style="font-size:0.85rem; color:var(--text-muted);">Эти права будут предложены пользователю при добавлении бота в качестве администратора в группы и каналы.</p>
<form method="POST">
<input type="hidden" name="action" value="set_rights">
<div class="updates-grid">
<?php
$admin_rights_map = [
    'is_anonymous' => 'Анонимность',
'can_manage_chat' => 'Управление чатом',
'can_delete_messages' => 'Удаление сообщений',
'can_manage_video_chats' => 'Управление видеочатами',
'can_restrict_members' => 'Блокировка пользователей',
'can_promote_members' => 'Назначение администраторов',
'can_change_info' => 'Изменение инфо о чате',
'can_invite_users' => 'Пригласительные ссылки',
'can_post_messages' => 'Публикация (в каналах)',
'can_edit_messages' => 'Ред. постов (в каналах)',
'can_pin_messages' => 'Закрепление сообщений',
'can_post_stories' => 'Публикация историй',
'can_edit_stories' => 'Редактирование историй',
'can_delete_stories' => 'Удаление историй',
'can_manage_topics' => 'Управление темами'
];

foreach ($admin_rights_map as $key => $label):
    $checked = !empty($botData['rights'][$key]) ? 'checked' : '';
?>
<label class="checkbox-label" title="<?= $key ?>">
<input type="checkbox" name="right_<?= $key ?>" <?= $checked ?>>
<span><?= $label ?> <span style="color:var(--text-muted); font-size:0.85em;">(<?= $key ?>)</span></span>
</label>
<?php endforeach; ?>
</div>
<button type="submit" class="btn">Сохранить дефолтные права</button>
</form>
</div>

<div id="tab-json" class="tab-content">
<h3>Сырые данные getMe (JSON)</h3>
<pre style="background:#1e1e1e; color:#d4d4d4; padding:15px; border-radius:8px; overflow-x:auto; font-family:monospace; font-size:0.85rem; border:1px solid var(--border);"><?= htmlspecialchars(json_encode($botData['me'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

<h3 style="margin-top:20px;">Просмотр локального лога (stats_counter.json)</h3>
<?php
$currentJsonData = file_exists($statsFile) ? file_get_contents($statsFile) : '{}';
$formattedJson = json_encode(json_decode($currentJsonData), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
<pre style="background:#1e1e1e; color:#d4d4d4; padding:15px; border-radius:8px; overflow-x:auto; font-family:monospace; font-size:0.85rem; border:1px solid var(--border);"><?= htmlspecialchars($formattedJson) ?></pre>
</div>
</div>
</div>

<?php endif; ?>
</div>

<script>
function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) { tabcontent[i].classList.remove("active"); }
    tablinks = document.getElementsByClassName("tab-link");
    for (i = 0; i < tablinks.length; i++) { tablinks[i].classList.remove("active"); }
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");
}
</script>

<footer>
<div class="container">
&copy; 2026 Telegram Bot Utility Platform. Разработано в соответствии с актуальными спецификациями Telegram Bot API. |
<a href="https://github.com/f1devbin/getWebhookInfo" target="_blank" style="color:var(--tg-blue); text-decoration:none; font-weight:bold;">⭐ Проект на GitHub</a>
</div>
</footer>

</body>
</html>
