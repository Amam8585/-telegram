<?php
// Functions for enforcing channel membership requirements.

if (!defined('CHANNEL_FORCE_CHAT_ID')) {
    define('CHANNEL_FORCE_CHAT_ID', -1002293404787);
}
if (!defined('CHANNEL_FORCE_CHAT_LINK')) {
    define('CHANNEL_FORCE_CHAT_LINK', 'https://t.me/Arian_storeGp');
}
if (!defined('CHANNEL_FORCE_JOIN_BUTTON_TEXT')) {
    define('CHANNEL_FORCE_JOIN_BUTTON_TEXT', 'عضویت در کانال');
}
if (!defined('CHANNEL_FORCE_RECHECK_BUTTON_TEXT')) {
    define('CHANNEL_FORCE_RECHECK_BUTTON_TEXT', 'بررسی مجدد');
}
if (!defined('CHANNEL_FORCE_JOIN_ALERT_TEXT')) {
    define('CHANNEL_FORCE_JOIN_ALERT_TEXT', 'برای استفاده از ربات ابتدا عضو کانال شوید');
}
if (!defined('CHANNEL_FORCE_RECHECK_CALLBACK')) {
    define('CHANNEL_FORCE_RECHECK_CALLBACK', 'channel_recheck');
}

function channel_force_pending_key($chat_id, $user_id)
{
    return (string) $chat_id . ':' . (string) $user_id;
}

function &channel_force_pending_store()
{
    static $store = [];
    return $store;
}

function channel_force_clear_pending($chat_id, $user_id)
{
    $store = &channel_force_pending_store();
    $key = channel_force_pending_key($chat_id, $user_id);
    if (isset($store[$key])) {
        unset($store[$key]);
    }
}

function channel_force_join_message_text()
{
    global $TXT;
    if (isset($TXT['channel_force_join_message'])) {
        return (string) $TXT['channel_force_join_message'];
    }

    $link = htmlspecialchars(CHANNEL_FORCE_CHAT_LINK, ENT_QUOTES, 'UTF-8');

    return "<b>🚫 | دسترسی محدود</b>\nبرای استفاده از ربات باید ابتدا در کانال زیر عضو شوید و سپس روی دکمه <b>بررسی مجدد</b> بزنید.\n🔗 <a href=\"{$link}\">کانال Arian_storeGp</a>";
}

function channel_force_join_button_text()
{
    global $BTN;
    if (isset($BTN['channel_force_join'])) {
        return (string) $BTN['channel_force_join'];
    }

    return CHANNEL_FORCE_JOIN_BUTTON_TEXT;
}

function channel_force_recheck_button_text()
{
    global $BTN;
    if (isset($BTN['channel_force_recheck'])) {
        return (string) $BTN['channel_force_recheck'];
    }

    return CHANNEL_FORCE_RECHECK_BUTTON_TEXT;
}

function channel_force_join_success_text()
{
    global $TXT;
    if (isset($TXT['channel_force_join_success'])) {
        return (string) $TXT['channel_force_join_success'];
    }

    return '✅ عضویت شما تایید شد.';
}

function channel_force_join_retry_text()
{
    global $TXT;
    if (isset($TXT['channel_force_join_retry'])) {
        return (string) $TXT['channel_force_join_retry'];
    }

    return 'عضویت شما تایید نشد. لطفاً پس از عضویت مجدداً تلاش کنید.';
}

function channel_force_join_error_text()
{
    global $TXT;
    if (isset($TXT['channel_force_join_error'])) {
        return (string) $TXT['channel_force_join_error'];
    }

    return 'خطا در بررسی عضویت. لطفاً بعداً دوباره تلاش کنید.';
}

/**
 * Check whether a Telegram user is a member of the required channel.
 *
 * @param int|string $user_id
 * @return bool|null True if member, false if not, null on API error.
 */
function channel_is_user_member($user_id)
{
    static $cache = [];
    $key = (string) $user_id;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    if (!$user_id) {
        $cache[$key] = null;
        return null;
    }
    $response = api('getChatMember', [
        'chat_id' => CHANNEL_FORCE_CHAT_ID,
        'user_id' => $user_id,
    ]);
    if (!is_array($response) || !($response['ok'] ?? false)) {
        $cache[$key] = null;
        return null;
    }
    $member = $response['result'] ?? [];
    $status = $member['status'] ?? '';
    $is_member = false;
    if (in_array($status, ['creator', 'administrator', 'member'], true)) {
        $is_member = true;
    } elseif ($status === 'restricted') {
        $is_member = array_key_exists('is_member', $member) ? (bool) $member['is_member'] : true;
    }
    $cache[$key] = $is_member;
    return $is_member;
}

/**
 * Enforce channel membership for private chats.
 *
 * @param int|string $user_id
 * @param int|string $chat_id
 * @param array $context Additional context such as chat_type, message_id, command, reply_to.
 * @return bool True if the user can continue, false if blocked.
 */
function channel_enforce_join($user_id, $chat_id, array $context = [])
{
    if (!$user_id || !$chat_id) {
        return true;
    }
    if (function_exists('admin_is_user') && admin_is_user($user_id)) {
        return true;
    }
    $membership = channel_is_user_member($user_id);
    if ($membership === null) {
        return true;
    }
    if ($membership === true) {
        return true;
    }

    $chat_type = $context['chat_type'] ?? '';
    $resume_clean = !empty($context['command']) && strtolower((string) $context['command']) === 'clean';
    $store = &channel_force_pending_store();
    $key = channel_force_pending_key($chat_id, $user_id);

    if (isset($store[$key])) {
        if ($chat_type !== '') {
            $store[$key]['context']['chat_type'] = $chat_type;
        }
        if (!empty($context['message_id']) && empty($store[$key]['context']['origin_message_id'])) {
            $store[$key]['context']['origin_message_id'] = $context['message_id'];
        }
        if ($resume_clean) {
            $store[$key]['context']['resume_clean'] = true;
        }
        if (!empty($context['kind'])) {
            $store[$key]['context']['kind'] = $context['kind'];
        }
        return false;
    }

    $keyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => channel_force_join_button_text(),
                    'url' => CHANNEL_FORCE_CHAT_LINK,
                ],
            ],
            [
                [
                    'text' => channel_force_recheck_button_text(),
                    'callback_data' => CHANNEL_FORCE_RECHECK_CALLBACK,
                ],
            ],
        ],
    ];

    $params = [
        'chat_id' => $chat_id,
        'text' => channel_force_join_message_text(),
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
        'disable_web_page_preview' => true,
    ];
    $reply_to = isset($context['reply_to']) ? (int) $context['reply_to'] : 0;
    if ($reply_to > 0) {
        $params['reply_to_message_id'] = $reply_to;
        $params['allow_sending_without_reply'] = true;
    }

    $response = api('sendMessage', $params);
    $message_id = 0;
    if (is_array($response) && ($response['ok'] ?? false)) {
        $message_id = (int) ($response['result']['message_id'] ?? 0);
    }

    $store[$key] = [
        'chat_id' => $chat_id,
        'user_id' => $user_id,
        'message_id' => $message_id,
        'context' => [
            'chat_type' => $chat_type,
            'origin_message_id' => $context['message_id'] ?? 0,
            'resume_clean' => $resume_clean,
            'kind' => $context['kind'] ?? 'message',
        ],
    ];

    return false;
}

function channel_force_auto_clean($chat_id, $user_id, $chat_type = '')
{
    if (!function_exists('handle_msg')) {
        return;
    }
    if ($chat_type === '') {
        $chat_type = 'supergroup';
    }
    if (!in_array($chat_type, ['group', 'supergroup'], true)) {
        return;
    }
    $message = [
        'message_id' => 0,
        'from' => ['id' => $user_id],
        'chat' => ['id' => $chat_id, 'type' => $chat_type],
        'text' => '/clean',
    ];
    handle_msg($message);
}

function channel_handle_callback($callback)
{
    $data = $callback['data'] ?? '';
    if ($data !== CHANNEL_FORCE_RECHECK_CALLBACK) {
        return false;
    }

    $query_id = $callback['id'] ?? '';
    $from = $callback['from'] ?? [];
    $user_id = $from['id'] ?? 0;
    $message = $callback['message'] ?? [];
    $chat = $message['chat'] ?? [];
    $chat_id = $chat['id'] ?? 0;
    $message_id = $message['message_id'] ?? 0;

    if (!$user_id || !$chat_id) {
        if ($query_id !== '') {
            api('answerCallbackQuery', ['callback_query_id' => $query_id]);
        }
        return true;
    }

    $chat_type = $chat['type'] ?? '';
    $membership = channel_is_user_member($user_id);
    $store = &channel_force_pending_store();
    $key = channel_force_pending_key($chat_id, $user_id);
    $pending = $store[$key] ?? null;

    if ($membership === true) {
        $context = $pending['context'] ?? [];
        if ($chat_type === '' && isset($context['chat_type'])) {
            $chat_type = $context['chat_type'];
        }
        $target_message_id = $pending['message_id'] ?? $message_id;
        if (in_array($chat_type, ['group', 'supergroup'], true)) {
            if ($target_message_id) {
                api('deleteMessage', [
                    'chat_id' => $chat_id,
                    'message_id' => $target_message_id,
                ]);
            }
            if (!empty($context['resume_clean'])) {
                channel_force_auto_clean($chat_id, $user_id, $chat_type);
            }
        } else {
            if ($target_message_id) {
                api('editMessageText', [
                    'chat_id' => $chat_id,
                    'message_id' => $target_message_id,
                    'text' => channel_force_join_success_text(),
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['inline_keyboard' => []], JSON_UNESCAPED_UNICODE),
                ]);
            }
        }
        if ($query_id !== '') {
            api('answerCallbackQuery', [
                'callback_query_id' => $query_id,
                'text' => channel_force_join_success_text(),
            ]);
        }
        if (isset($store[$key])) {
            unset($store[$key]);
        }
        return true;
    }

    $alert_text = $membership === null ? channel_force_join_error_text() : channel_force_join_retry_text();
    if ($query_id !== '') {
        api('answerCallbackQuery', [
            'callback_query_id' => $query_id,
            'text' => $alert_text,
            'show_alert' => true,
        ]);
    }

    return true;
}
