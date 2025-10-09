<?php
function admin_panel_render($flags=null){
    global $TXT;
    if ($flags === null) {
        $flags = admin_flags_all();
    }
    $auto_disabled = (bool)($flags['auto'] ?? false);
    $card_disabled = (bool)($flags['card'] ?? false);

    $text = $TXT['admin_panel_title'] . "\n";
    $text .= '🤖 | <b>خودکار:</b> ' . ($auto_disabled ? '❌ <b>غیرفعال</b>' : '✅ <b>فعال</b>') . "\n";
    $text .= '💳 | <b>کارت به کارت:</b> ' . ($card_disabled ? '❌ <b>غیرفعال</b>' : '✅ <b>فعال</b>');

    $btn_auto = trim(strip_tags($TXT['ap_toggle_auto'] ?? 'روشن/خاموش خودکار')) . ($auto_disabled ? ' ❌' : ' ✅');
    $btn_card = trim(strip_tags($TXT['ap_toggle_card'] ?? 'روشن/خاموش کارت به کارت')) . ($card_disabled ? ' ❌' : ' ✅');
    $btn_close = trim(strip_tags($TXT['ap_close'] ?? 'بستن پنل'));

    $kb = [
        'inline_keyboard' => [
            [
                ['text' => $btn_auto, 'callback_data' => 'ap_toggle_auto'],
            ],
            [
                ['text' => $btn_card, 'callback_data' => 'ap_toggle_card'],
            ],
            [
                ['text' => $btn_close, 'callback_data' => 'ap_close'],
            ],
        ],
    ];

    return [$text, $kb];
}

function admin_on_callback($data, $uid, $qid, $cid, $mid, $st)
{
    global $TXT, $BTN;
    $is_admin = (defined('ADMIN_ID') && ((string)$uid === (string)ADMIN_ID));
    if (strpos($data, 'ap_') === 0) {
        if (!$is_admin) {
            api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['only_admin_btn']]);
            return true;
        }
        if ($data === 'ap_close') {
            api('editMessageText', ['chat_id' => $cid, 'message_id' => $mid, 'text' => $TXT['ap_closed'], 'parse_mode' => 'HTML']);
            api('editMessageReplyMarkup', ['chat_id' => $cid, 'message_id' => $mid, 'reply_markup' => json_encode(['inline_keyboard' => []], JSON_UNESCAPED_UNICODE)]);
            api('answerCallbackQuery', ['callback_query_id' => $qid]);
            return true;
        }
        if (in_array($data, ['ap_toggle_bot', 'ap_toggle_auto', 'ap_toggle_card'], true)) {
            $map = [
                'ap_toggle_bot' => 'bot',
                'ap_toggle_auto' => 'auto',
                'ap_toggle_card' => 'card',
            ];
            $key = $map[$data];
        if ($data === 'ap_toggle_auto' || $data === 'ap_toggle_card') {
            $key = $data === 'ap_toggle_auto' ? 'auto' : 'card';
            $res = admin_flags_toggle($key);
            $flags = $res['flags'];
            [$text, $kb] = admin_panel_render($flags);
            api('editMessageText', ['chat_id' => $cid, 'message_id' => $mid, 'text' => $text, 'parse_mode' => 'HTML']);
            api('editMessageReplyMarkup', ['chat_id' => $cid, 'message_id' => $mid, 'reply_markup' => json_encode($kb, JSON_UNESCAPED_UNICODE)]);
            api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['ap_saved']]);
            return true;
        }
        api('answerCallbackQuery', ['callback_query_id' => $qid]);
        return true;
    }
    if (strpos($data, 'msg_buyer:') === 0 || strpos($data, 'msg_seller:') === 0 || strpos($data, 'seller_bad:') === 0 || strpos($data, 'no_group:') === 0 || strpos($data, 'req_code:') === 0 || strpos($data, 'finish_change:') === 0) {
        if (!$is_admin) {
            api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['only_admin_btn']]);
            return true;
        }
        if (strpos($data, 'msg_buyer:') === 0) {
            $gid = (int)substr($data, 11);
            $gs = load_state($gid);
            if (!$gs) {
                api('answerCallbackQuery', ['callback_query_id' => $qid]);
                return true;
            }
            $gs['bridge'] = ['on' => true, 'admin' => $uid, 'gid' => $gid, 'side' => 'buyer'];
            save_state($gid, $gs);
            api('sendMessage', ['chat_id' => $uid, 'text' => $TXT['bridge_started_buyer'] . "\n" . $TXT['bridge_note_done'], 'parse_mode' => 'HTML']);
            api('answerCallbackQuery', ['callback_query_id' => $qid]);
            return true;
        }
        if (strpos($data, 'msg_seller:') === 0) {
            $gid = (int)substr($data, 12);
            $gs = load_state($gid);
            if (!$gs) {
                api('answerCallbackQuery', ['callback_query_id' => $qid]);
                return true;
            }
            $gs['bridge'] = ['on' => true, 'admin' => $uid, 'gid' => $gid, 'side' => 'seller'];
            save_state($gid, $gs);
            api('sendMessage', ['chat_id' => $uid, 'text' => $TXT['bridge_started_seller'] . "\n" . $TXT['bridge_note_done'], 'parse_mode' => 'HTML']);
            api('answerCallbackQuery', ['callback_query_id' => $qid]);
            return true;
        }
        if (strpos($data, 'seller_bad:') === 0) {
            $gid = (int)substr($data, 11);
            $gs = load_state($gid);
            if (!$gs) {
                api('answerCallbackQuery', ['callback_query_id' => $qid]);
                return true;
            }
            if (($gs['seller_id'] ?? 0) > 0) {
                $sel = (int)$gs['seller_id'];
                $gs['seller_email'] = null;
                $gs['seller_pass'] = null;
                save_state($gid, $gs);
                save_uctx($sel, ['chat_id' => $gid, 'role' => 'seller', 'need' => 'email']);
                api('sendMessage', ['chat_id' => $sel, 'text' => $TXT['seller_reask'], 'parse_mode' => 'HTML']);
            }
            api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['sent']]);
            return true;
        }
        if (strpos($data, 'no_group:') === 0) {
            api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['no_group_link']]);
            return true;
        }
        if (strpos($data, 'req_code:') === 0) {
            $gid = substr($data, 9);
            $gs = load_state($gid);
            if ($gs && ($gs['seller_id'] ?? 0) > 0) {
                api('sendMessage', ['chat_id' => $gs['seller_id'], 'text' => $TXT['ask_seller_code'], 'parse_mode' => 'HTML']);
                $gs['await_code'] = true;
                save_state($gid, $gs);
            }
            api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['code_requested']]);
            return true;
        }
        if (strpos($data, 'finish_change:') === 0) {
            $gid = substr($data, 14);
            $gs = load_state($gid);
            if (!$gs) {
                api('answerCallbackQuery', ['callback_query_id' => $qid]);
                return true;
            }
            if (($gs['buyer_id'] ?? 0) > 0 && ($gs['seller_pass'] ?? '') !== '') {
                api('sendMessage', ['chat_id' => $gs['buyer_id'], 'text' => $TXT['send_pass_to_buyer_prefix'] . $gs['seller_pass'] . $TXT['send_pass_to_buyer_suffix'], 'parse_mode' => 'HTML']);
            }
            if (($gs['seller_id'] ?? 0) > 0) {
                api('sendMessage', ['chat_id' => $gs['seller_id'], 'text' => $TXT['change_done_seller'], 'parse_mode' => 'HTML']);
            }
            api('sendMessage', ['chat_id' => $gid, 'text' => $TXT['change_done_group'], 'parse_mode' => 'HTML']);
            $gs['phase'] = 'post_change';
            save_state($gid, $gs);
            api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['sent']]);
            return true;
        }
    }
    return false;
}

function admin_on_private_text($uid, $txt)
{
    global $TXT;
    $trim = trim($txt);
    $lower = mb_strtolower($trim, 'UTF-8');
    if ($trim !== '' && preg_match('/^\/admin(?:@[\w_]+)?$/', $lower)) {
        [$text, $kb] = admin_panel_render();
        api('sendMessage', ['chat_id' => $uid, 'text' => $text, 'parse_mode' => 'HTML', 'reply_markup' => json_encode($kb, JSON_UNESCAPED_UNICODE)]);
        return true;
    }
    $files = list_plan_files();
    foreach ($files as $f) {
        $a = @json_decode(@file_get_contents($f), true);
        if (!is_array($a)) {
            continue;
        }
        $br = $a['bridge'] ?? null;
        if (is_array($br) && ($br['on'] ?? false) && ($br['admin'] ?? 0) == $uid) {
            if (mb_strtolower($txt, 'UTF-8') === 'done') {
                $a['bridge']['on'] = false;
                save_state((int)$a['bridge']['gid'], $a);
                api('sendMessage', ['chat_id' => $uid, 'text' => $TXT['bridge_finished'], 'parse_mode' => 'HTML']);
                return true;
            }
            $to = 0;
            $side = $br['side'] ?? '';
            if ($side === 'buyer') {
                $to = (int)($a['buyer_id'] ?? 0);
            } elseif ($side === 'seller') {
                $to = (int)($a['seller_id'] ?? 0);
            }
            if ($to > 0) {
                api('sendMessage', ['chat_id' => $to, 'text' => $TXT['msg_from_admin_prefix'] . $txt, 'parse_mode' => 'HTML']);
                api('sendMessage', ['chat_id' => $uid, 'text' => $TXT['bridge_forwarded'], 'parse_mode' => 'HTML']);
                return true;
            }
        }
    }
    return false;
}
