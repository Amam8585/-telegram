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

function channel_force_join_message_text(array $context = [])
{
    global $TXT;

    $message = null;
    if (isset($TXT['channel_force_join_message'])) {
        $message = (string) $TXT['channel_force_join_message'];
    }

    if ($message === null || $message === '') {
        $message = "برای استفاده از ربات باید ابتدا در کانال زیر عضو شوید:\n\nکانال: {channel_link}";
    }

    $link = CHANNEL_FORCE_CHAT_LINK;
    $flags = ENT_QUOTES;
    if (defined('ENT_SUBSTITUTE')) {
        $flags |= ENT_SUBSTITUTE;
    }
    $replacements = [
        '{channel_link}' => htmlspecialchars($link, $flags, 'UTF-8'),
        '{join_button}' => channel_force_join_button_text(),
    ];

    return strtr($message, $replacements);
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
 * Enforce channel membership where required (currently private chats only).
 *
 * @param int|string $user_id
 * @param int|string $chat_id
 * @return bool True if the user can continue, false if blocked.
 */
function channel_enforce_join($user_id, $chat_id, array $context = [])
{
    if (!$user_id || !$chat_id) {
        return true;
    }
    $chat_type = $context['chat_type'] ?? '';
    if ($chat_type !== '' && $chat_type !== 'private') {
        return true;
    }
    if (function_exists('admin_is_user') && admin_is_user($user_id)) {
        return true;
    }
    $membership = channel_is_user_member($user_id);
    $force_on_error = !empty($context['force_on_error']);
    if ($membership === null && !$force_on_error) {
        return true;
    }
    if ($membership === true) {
        return true;
    }
    static $notified = [];
    $key = $chat_id . ':' . $user_id;
    if (!isset($notified[$key])) {
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
        $payload = [
            'chat_id' => $chat_id,
            'text' => channel_force_join_message_text($context),
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
            'disable_web_page_preview' => true,
        ];
        $chat_type = $context['chat_type'] ?? '';
        $message_id = $context['message_id'] ?? 0;
        if ($chat_type !== 'private' && $message_id) {
            $payload['reply_to_message_id'] = $message_id;
            $payload['allow_sending_without_reply'] = true;
        }
        api('sendMessage', $payload);
        $notified[$key] = true;
    }
    return false;
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

    $membership = channel_is_user_member($user_id);
    if ($membership === true) {
        if ($message_id) {
            api('editMessageText', [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => channel_force_join_success_text(),
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => []], JSON_UNESCAPED_UNICODE),
            ]);
        }
        if ($query_id !== '') {
            api('answerCallbackQuery', [
                'callback_query_id' => $query_id,
                'text' => channel_force_join_success_text(),
            ]);
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
