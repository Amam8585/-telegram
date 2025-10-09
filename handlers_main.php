<?php
function bot_is_admin_user($user_id)
{
    return admin_is_user($user_id);
}

function bot_update_is_from_admin($update)
{
    if (!is_array($update)) {
        return false;
    }
    if (isset($update['message']['from']['id']) && bot_is_admin_user($update['message']['from']['id'])) {
        return true;
    }
    if (isset($update['callback_query']['from']['id']) && bot_is_admin_user($update['callback_query']['from']['id'])) {
        return true;
    }
    if (isset($update['inline_query']['from']['id']) && bot_is_admin_user($update['inline_query']['from']['id'])) {
        return true;
    }
    if (isset($update['chosen_inline_result']['from']['id']) && bot_is_admin_user($update['chosen_inline_result']['from']['id'])) {
        return true;
    }
    return false;
}

function bot_is_globally_disabled()
{
    return function_exists('admin_flags_is_disabled') && admin_flags_is_disabled('bot');
}

function bot_should_ignore_update($update)
{
    if (!bot_is_globally_disabled()) {
        return false;
    }
    if (bot_update_is_from_admin($update)) {
        return false;
    }
    return true;
}

function bot_handle_disabled_notice($update)
{
    global $TXT;
    $notice = is_array($TXT) ? ($TXT['bot_disabled_notice'] ?? '') : '';
    $plain_notice = trim(strip_tags($notice));
    if ($notice === '') {
        return;
    }
    if (isset($update['callback_query'])) {
        $qid = $update['callback_query']['id'] ?? '';
        if ($qid) {
            api('answerCallbackQuery', [
                'callback_query_id' => $qid,
                'text' => $plain_notice,
                'show_alert' => true,
            ]);
        }
        return;
    }
    if (isset($update['inline_query'])) {
        $iq = $update['inline_query'];
        $qid = $iq['id'] ?? '';
        if ($qid !== '') {
            $params = [
                'inline_query_id' => $qid,
                'results' => json_encode([], JSON_UNESCAPED_UNICODE),
                'cache_time' => 5,
                'is_personal' => true,
            ];
            if ($plain_notice !== '') {
                $params['switch_pm_text'] = $plain_notice;
                $params['switch_pm_parameter'] = 'disabled';
            }
            api('answerInlineQuery', $params);
        }
        return;
    }
    if (isset($update['message'])) {
        $chat = $update['message']['chat'] ?? [];
        $type = $chat['type'] ?? '';
        $chat_id = $chat['id'] ?? 0;
        if (!$chat_id) {
            return;
        }
        if ($type === 'private') {
            api('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $notice,
                'parse_mode' => 'HTML',
            ]);
            return;
        }
        if ($type === 'group' || $type === 'supergroup') {
            $text = $update['message']['text'] ?? '';
            $is_command = is_string($text) && $text !== '' && $text[0] === '/';
            if ($is_command) {
                api('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => $notice,
                    'parse_mode' => 'HTML',
                    'reply_to_message_id' => $update['message']['message_id'] ?? null,
                    'allow_sending_without_reply' => true,
                ]);
            }
        }
    }
}
