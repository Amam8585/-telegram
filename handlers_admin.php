<?php
function admin_panel_render($flags=null){
    global $TXT;
    if ($flags === null) {
        $flags = admin_flags_all();
    }
    $bot_disabled = (bool)($flags['bot'] ?? false);
    $auto_disabled = (bool)($flags['auto'] ?? false);
    $card_disabled = (bool)($flags['card'] ?? false);

    $text = $TXT['admin_panel_title'] . "\n";
    $status_enabled = $TXT['ap_status_enabled'] ?? '✅  <b>فعال</b>';
    $status_disabled = $TXT['ap_status_disabled'] ?? '❌  <b>غیرفعال</b>';
    $bot_label = $TXT['ap_bot_status_label'] ?? '🤖 | <b>ربات :</b> ';
    $auto_label = $TXT['ap_auto_status_label'] ?? '🤖 | <b>خودکار :</b> ';
    $card_label = $TXT['ap_card_status_label'] ?? '💳 | <b>کارت به کارت :</b> ';

    $text .= $bot_label . ($bot_disabled ? $status_disabled : $status_enabled) . "\n";
    $text .= $auto_label . ($auto_disabled ? $status_disabled : $status_enabled) . "\n";
    $text .= $card_label . ($card_disabled ? $status_disabled : $status_enabled);

    $suffix_enabled = trim(strip_tags($TXT['ap_toggle_suffix_enabled'] ?? ' ✅'));
    $suffix_disabled = trim(strip_tags($TXT['ap_toggle_suffix_disabled'] ?? ' ❌'));
    $btn_bot = trim(strip_tags($TXT['ap_toggle_bot'] ?? 'وضعیت ربات')) . ($bot_disabled ? $suffix_disabled : $suffix_enabled);
    $btn_auto = trim(strip_tags($TXT['ap_toggle_auto'] ?? 'روش خودکار')) . ($auto_disabled ? $suffix_disabled : $suffix_enabled);
    $btn_card = trim(strip_tags($TXT['ap_toggle_card'] ?? 'روش کارت به کارت')) . ($card_disabled ? $suffix_disabled : $suffix_enabled);
    $btn_manage_cards = trim(strip_tags($TXT['ap_manage_cards'] ?? 'مدیریت کارت‌ها'));
    $btn_close = trim(strip_tags($TXT['ap_close'] ?? 'بستن پنل'));

    $kb = [
        'inline_keyboard' => [
            [
                ['text' => $btn_bot, 'callback_data' => 'ap_toggle_bot'],
            ],
            [
                ['text' => $btn_auto, 'callback_data' => 'ap_toggle_auto'],
            ],
            [
                ['text' => $btn_card, 'callback_data' => 'ap_toggle_card'],
            ],
            [
                ['text' => $btn_manage_cards, 'callback_data' => 'ap_card_types'],
            ],
            [
                ['text' => $btn_close, 'callback_data' => 'ap_close'],
            ],
        ],
    ];

    return [$text, $kb];
}

function admin_extract_callback_gid($data, $prefix)
{
    if (strpos($data, $prefix) !== 0) {
        return '';
    }
    $gid = trim(substr($data, strlen($prefix)));
    if ($gid === '' || !preg_match('/^-?\d+$/', $gid)) {
        return '';
    }
    return $gid;
}

function admin_on_callback($data, $uid, $qid, $cid, $mid, $st)
{
    global $TXT, $BTN;
    $is_admin = admin_is_user($uid);
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
            $res = admin_flags_toggle($key);
            $flags = $res['flags'];
            [$text, $kb] = admin_panel_render($flags);
            api('editMessageText', ['chat_id' => $cid, 'message_id' => $mid, 'text' => $text, 'parse_mode' => 'HTML']);
            api('editMessageReplyMarkup', ['chat_id' => $cid, 'message_id' => $mid, 'reply_markup' => json_encode($kb, JSON_UNESCAPED_UNICODE)]);

            $disabled = (bool)($res['disabled'] ?? false);
            $messages = [
                'bot' => [
                    'enabled' => $TXT['ap_toggle_bot_enabled'] ?? ($TXT['ap_saved'] ?? ''),
                    'disabled' => $TXT['ap_toggle_bot_disabled'] ?? ($TXT['ap_saved'] ?? ''),
                ],
                'auto' => [
                    'enabled' => $TXT['ap_toggle_auto_enabled'] ?? ($TXT['ap_saved'] ?? ''),
                    'disabled' => $TXT['ap_toggle_auto_disabled'] ?? ($TXT['ap_saved'] ?? ''),
                ],
                'card' => [
                    'enabled' => $TXT['ap_toggle_card_enabled'] ?? ($TXT['ap_saved'] ?? ''),
                    'disabled' => $TXT['ap_toggle_card_disabled'] ?? ($TXT['ap_saved'] ?? ''),
                ],
            ];
            $status_key = $disabled ? 'disabled' : 'enabled';
            $cb_text = $messages[$key][$status_key] ?? ($TXT['ap_saved'] ?? '');
            api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $cb_text]);
            return true;
        }
        if ($data === 'ap_card_types') {
            [$text, $kb] = admin_card_types_overview_components();
            api('editMessageText', ['chat_id' => $cid, 'message_id' => $mid, 'text' => $text, 'parse_mode' => 'HTML']);
            api('editMessageReplyMarkup', ['chat_id' => $cid, 'message_id' => $mid, 'reply_markup' => json_encode($kb, JSON_UNESCAPED_UNICODE)]);
            api('answerCallbackQuery', ['callback_query_id' => $qid]);
            return true;
        }
        if ($data === 'ap_panel_back') {
            [$text, $kb] = admin_panel_render();
            api('editMessageText', ['chat_id' => $cid, 'message_id' => $mid, 'text' => $text, 'parse_mode' => 'HTML']);
            api('editMessageReplyMarkup', ['chat_id' => $cid, 'message_id' => $mid, 'reply_markup' => json_encode($kb, JSON_UNESCAPED_UNICODE)]);
            api('answerCallbackQuery', ['callback_query_id' => $qid]);
            return true;
        }
        if ($data === 'ap_card_add') {
            admin_card_editor_set($uid, [
                'action' => 'card_add',
                'stage' => 'title',
                'draft' => ['title' => '', 'card_number' => '', 'holder' => '', 'sticker' => ''],
            ]);
            $prompt = $TXT['ap_card_add_prompt'] ?? '';
            if ($prompt !== '') {
                api('sendMessage', ['chat_id' => $uid, 'text' => $prompt, 'parse_mode' => 'HTML']);
            }
            api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['ap_card_add_started'] ?? ($TXT['ap_saved'] ?? '')]);
            return true;
        }
        if (strpos($data, 'ap_card_edit:') === 0) {
            $prefix = 'ap_card_edit:';
            $target = trim(substr($data, strlen($prefix)));
            $card = card_type_get($target);
            if (!$card) {
                api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['ap_card_not_found'] ?? '']);
                return true;
            }
            [$text, $kb] = admin_card_type_item_components($card);
            api('editMessageText', ['chat_id' => $cid, 'message_id' => $mid, 'text' => $text, 'parse_mode' => 'HTML']);
            api('editMessageReplyMarkup', ['chat_id' => $cid, 'message_id' => $mid, 'reply_markup' => json_encode($kb, JSON_UNESCAPED_UNICODE)]);
            api('answerCallbackQuery', ['callback_query_id' => $qid]);
            return true;
        }
        if (strpos($data, 'ap_card_edit_label:') === 0) {
            $prefix = 'ap_card_edit_label:';
            $target = trim(substr($data, strlen($prefix)));
            if (!card_type_get($target)) {
                api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['ap_card_not_found'] ?? '']);
                return true;
            }
            admin_card_editor_set($uid, ['action' => 'card_edit_label', 'card_id' => $target]);
            api('sendMessage', ['chat_id' => $uid, 'text' => $TXT['ap_card_edit_label_prompt'] ?? '', 'parse_mode' => 'HTML']);
            api('answerCallbackQuery', ['callback_query_id' => $qid]);
            return true;
        }
        if (strpos($data, 'ap_card_edit_sticker:') === 0) {
            $prefix = 'ap_card_edit_sticker:';
            $target = trim(substr($data, strlen($prefix)));
            if (!card_type_get($target)) {
                api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['ap_card_not_found'] ?? '']);
                return true;
            }
            admin_card_editor_set($uid, ['action' => 'card_edit_sticker', 'card_id' => $target]);
            api('sendMessage', ['chat_id' => $uid, 'text' => $TXT['ap_card_edit_sticker_prompt'] ?? '', 'parse_mode' => 'HTML']);
            api('answerCallbackQuery', ['callback_query_id' => $qid]);
            return true;
        }
        if (strpos($data, 'ap_card_delete:') === 0) {
            $prefix = 'ap_card_delete:';
            $target = trim(substr($data, strlen($prefix)));
            if (!card_type_delete($target)) {
                api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['ap_card_not_found'] ?? '']);
                return true;
            }
            [$text, $kb] = admin_card_types_overview_components();
            api('editMessageText', ['chat_id' => $cid, 'message_id' => $mid, 'text' => $text, 'parse_mode' => 'HTML']);
            api('editMessageReplyMarkup', ['chat_id' => $cid, 'message_id' => $mid, 'reply_markup' => json_encode($kb, JSON_UNESCAPED_UNICODE)]);
            api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['ap_card_deleted'] ?? ($TXT['ap_saved'] ?? '')]);
            return true;
        }
    }
    if (strpos($data, 'msg_buyer:') === 0 || strpos($data, 'msg_seller:') === 0 || strpos($data, 'seller_bad:') === 0 || strpos($data, 'no_group:') === 0 || strpos($data, 'req_code:') === 0 || strpos($data, 'finish_change:') === 0 || strpos($data, 'seller_code_expired:') === 0 || strpos($data, 'buyer_email_wrong:') === 0) {
        if (!$is_admin) {
            api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['only_admin_btn']]);
            return true;
        }
        if (strpos($data, 'msg_buyer:') === 0) {
            $gid = admin_extract_callback_gid($data, 'msg_buyer:');
            if ($gid === '') {
                api('answerCallbackQuery', ['callback_query_id' => $qid]);
                return true;
            }
            $gs = load_state($gid);
            if (!$gs) {
                api('answerCallbackQuery', ['callback_query_id' => $qid]);
                return true;
            }
            $bridge = $gs['bridge'] ?? [];
            if (is_array($bridge) && ($bridge['on'] ?? false)) {
                $current_admin = (int)($bridge['admin'] ?? 0);
                if ($current_admin !== 0 && $current_admin !== $uid) {
                    api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['bridge_busy']]);
                    return true;
                }
                if (($bridge['side'] ?? '') === 'buyer') {
                    api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['bridge_already']]);
                    return true;
                }
            }
            $gs['bridge'] = ['on' => true, 'admin' => $uid, 'gid' => $gid, 'side' => 'buyer'];
            save_state($gid, $gs);
            api('sendMessage', ['chat_id' => $uid, 'text' => $TXT['bridge_started_buyer'] . "\n" . $TXT['bridge_note_done'], 'parse_mode' => 'HTML']);
            if (($gs['buyer_id'] ?? 0) > 0) {
                api('sendMessage', ['chat_id' => (int)$gs['buyer_id'], 'text' => $TXT['bridge_notify_buyer'], 'parse_mode' => 'HTML']);
            }
            api('answerCallbackQuery', ['callback_query_id' => $qid]);
            return true;
        }
        if (strpos($data, 'msg_seller:') === 0) {
            $gid = admin_extract_callback_gid($data, 'msg_seller:');
            if ($gid === '') {
                api('answerCallbackQuery', ['callback_query_id' => $qid]);
                return true;
            }
            $gs = load_state($gid);
            if (!$gs) {
                api('answerCallbackQuery', ['callback_query_id' => $qid]);
                return true;
            }
            $bridge = $gs['bridge'] ?? [];
            if (is_array($bridge) && ($bridge['on'] ?? false)) {
                $current_admin = (int)($bridge['admin'] ?? 0);
                if ($current_admin !== 0 && $current_admin !== $uid) {
                    api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['bridge_busy']]);
                    return true;
                }
                if (($bridge['side'] ?? '') === 'seller') {
                    api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['bridge_already']]);
                    return true;
                }
            }
            $gs['bridge'] = ['on' => true, 'admin' => $uid, 'gid' => $gid, 'side' => 'seller'];
            save_state($gid, $gs);
            api('sendMessage', ['chat_id' => $uid, 'text' => $TXT['bridge_started_seller'] . "\n" . $TXT['bridge_note_done'], 'parse_mode' => 'HTML']);
            if (($gs['seller_id'] ?? 0) > 0) {
                api('sendMessage', ['chat_id' => (int)$gs['seller_id'], 'text' => $TXT['bridge_notify_seller'], 'parse_mode' => 'HTML']);
            }
            api('answerCallbackQuery', ['callback_query_id' => $qid]);
            return true;
        }
        if (strpos($data, 'seller_bad:') === 0) {
            $gid = admin_extract_callback_gid($data, 'seller_bad:');
            if ($gid === '') {
                api('answerCallbackQuery', ['callback_query_id' => $qid]);
                return true;
            }
            $gs = load_state($gid);
            if (!$gs) {
                api('answerCallbackQuery', ['callback_query_id' => $qid]);
                return true;
            }
            if (($gs['seller_id'] ?? 0) > 0) {
                $sel = (int)$gs['seller_id'];
                $gs['seller_email'] = null;
                $gs['seller_pass'] = null;
                $gs['await_code'] = false;
                unset($gs['notified_admin']);
                save_state($gid, $gs);
                save_uctx($sel, ['chat_id' => $gid, 'role' => 'seller', 'need' => 'email', 'token' => $gs['token'] ?? '']);
                api('sendMessage', ['chat_id' => $sel, 'text' => $TXT['seller_reask'], 'parse_mode' => 'HTML']);
            }
            api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['sent']]);
            return true;
        }
        if (strpos($data, 'seller_code_expired:') === 0) {
            $gid = admin_extract_callback_gid($data, 'seller_code_expired:');
            if ($gid === '') {
                api('answerCallbackQuery', ['callback_query_id' => $qid]);
                return true;
            }
            $gs = load_state($gid);
            if ($gs && ($gs['seller_id'] ?? 0) > 0) {
                $sel = (int)$gs['seller_id'];
                api('sendMessage', ['chat_id' => $sel, 'text' => $TXT['seller_code_expired_notice'], 'parse_mode' => 'HTML']);
                $gs['await_code'] = ['admin' => $uid, 'ts' => time()];
                save_state($gid, $gs);
            }
            api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['code_requested']]);
            return true;
        }
        if (strpos($data, 'buyer_email_wrong:') === 0) {
            $gid = admin_extract_callback_gid($data, 'buyer_email_wrong:');
            if ($gid === '') {
                api('answerCallbackQuery', ['callback_query_id' => $qid]);
                return true;
            }
            $gs = load_state($gid);
            if ($gs && ($gs['buyer_id'] ?? 0) > 0) {
                $bid = (int)$gs['buyer_id'];
                $gs['buyer_email'] = null;
                save_state($gid, $gs);
                save_uctx($bid, ['chat_id' => $gid, 'role' => 'buyer', 'token' => $gs['token'] ?? '']);
                api('sendMessage', ['chat_id' => $bid, 'text' => $TXT['buyer_email_wrong_notice'], 'parse_mode' => 'HTML']);
            }
            api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['sent']]);
            return true;
        }
        if (strpos($data, 'no_group:') === 0) {
            api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['no_group_link']]);
            return true;
        }
        if (strpos($data, 'req_code:') === 0) {
            $gid = admin_extract_callback_gid($data, 'req_code:');
            $gs = load_state($gid);
            if ($gs && ($gs['seller_id'] ?? 0) > 0) {
                api('sendMessage', ['chat_id' => $gs['seller_id'], 'text' => $TXT['ask_seller_code'], 'parse_mode' => 'HTML']);
                $gs['await_code'] = ['admin' => $uid, 'ts' => time()];
                save_state($gid, $gs);
            }
            api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $TXT['code_requested']]);
            return true;
        }
        if (strpos($data, 'finish_change:') === 0) {
            $gid = admin_extract_callback_gid($data, 'finish_change:');
            $gs = load_state($gid);
            if (!$gs) {
                api('answerCallbackQuery', ['callback_query_id' => $qid]);
                return true;
            }
            $buyer_id = (int)($gs['buyer_id'] ?? 0);
            $seller_id = (int)($gs['seller_id'] ?? 0);
            $seller_pass = (string)($gs['seller_pass'] ?? '');
            if ($buyer_id > 0 && $seller_pass !== '') {
                $buyer_tpl = $TXT['finish_change_buyer_pm'] ?? '';
                if ($buyer_tpl !== '') {
                    $buyer_text = strtr($buyer_tpl, ['{password}' => $seller_pass]);
                    api('sendMessage', ['chat_id' => $buyer_id, 'text' => $buyer_text, 'parse_mode' => 'HTML']);
                } else {
                    api('sendMessage', ['chat_id' => $buyer_id, 'text' => $TXT['send_pass_to_buyer_prefix'] . $seller_pass . $TXT['send_pass_to_buyer_suffix'], 'parse_mode' => 'HTML']);
                }
            }
            $log_command = trim($TXT['log_command_text'] ?? '');
            $log_message_id = 0;
            if ($log_command !== '') {
                $log_res = api('sendMessage', ['chat_id' => $gid, 'text' => $log_command]);
                if (isset($log_res['ok']) && $log_res['ok']) {
                    $log_message_id = (int)($log_res['result']['message_id'] ?? 0);
                }
                if ($log_message_id > 0) {
                    api('deleteMessage', ['chat_id' => $gid, 'message_id' => $log_message_id]);
                }
            }
            $user_link_tpl = $TXT['user_link_template'] ?? '';
            $missing_html = $TXT['admin_info_missing_value'] ?? '<b>نامشخص</b>';
            $seller_tag = $missing_html;
            if ($seller_id > 0) {
                $seller_username = $gs['seller_username'] ?? '';
                $seller_label = $seller_username !== '' ? '@' . $seller_username : ($TXT['seller_label'] ?? '');
                $seller_tag = $user_link_tpl !== '' ? strtr($user_link_tpl, ['{user_id}' => $seller_id, '{label}' => $seller_label]) : $seller_label;
            }
            $instruction_tpl = $TXT['log_instruction_text'] ?? '';
            $instruction_text = $instruction_tpl !== '' ? strtr($instruction_tpl, ['{seller}' => $seller_tag]) : ($seller_tag . ' «به روش بالا لاگ را ارسال کنید.»');
            $support_mid = (int)($gs['admin_paid_msg_id'] ?? 0);
            $group_params = [
                'chat_id' => $gid,
                'text' => $instruction_text,
                'parse_mode' => 'HTML'
            ];
            if ($support_mid > 0) {
                $group_params['reply_to_message_id'] = $support_mid;
                $group_params['allow_sending_without_reply'] = true;
            }
            $instruction_mid = 0;
            $group_res = api('sendMessage', $group_params);
            if (isset($group_res['ok']) && $group_res['ok']) {
                $instruction_mid = (int)($group_res['result']['message_id'] ?? 0);
            }
            if ($seller_id > 0) {
                $seller_notice = $TXT['change_done_seller'] ?? '';
                if ($seller_notice !== '') {
                    api('sendMessage', ['chat_id' => $seller_id, 'text' => $seller_notice, 'parse_mode' => 'HTML']);
                }
            }
            $gs['phase'] = 'await_seller_log';
            $gs['await_log'] = [
                'seller_id' => $seller_id,
                'buyer_id' => $buyer_id,
                'instruction_message_id' => $instruction_mid
            ];
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
            $side = $br['side'] ?? '';
            $to = 0;
            if ($side === 'buyer') {
                $to = (int)($a['buyer_id'] ?? 0);
            } elseif ($side === 'seller') {
                $to = (int)($a['seller_id'] ?? 0);
            }
            if (mb_strtolower($txt, 'UTF-8') === 'done') {
                $a['bridge']['on'] = false;
                save_state((int)$a['bridge']['gid'], $a);
                if ($to > 0) {
                    api('sendMessage', ['chat_id' => $to, 'text' => $TXT['bridge_finished_user'], 'parse_mode' => 'HTML']);
                }
                api('sendMessage', ['chat_id' => $uid, 'text' => $TXT['bridge_finished'], 'parse_mode' => 'HTML']);
                return true;
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

function admin_card_types_overview_components()
{
    global $TXT;
    $types = card_types_all();
    $fallback = $TXT['card_type_fallback_label'] ?? 'کارت';
    $text = $TXT['ap_card_types_title'] ?? '<b>مدیریت کارت‌ها</b>';
    $lines = [];
    foreach ($types as $type) {
        $label = $type['title'] !== '' ? $type['title'] : $fallback;
        $lines[] = '• ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    }
    if (empty($types)) {
        $empty = $TXT['ap_card_types_empty'] ?? '';
        if ($empty !== '') {
            $text .= "\n" . $empty;
        }
    } else {
        $text .= "\n" . implode("\n", $lines);
    }
    $buttons = [];
    foreach ($types as $type) {
        $label = $type['title'] !== '' ? $type['title'] : $fallback;
        $buttons[] = ['text' => '⚙️ ' . $label, 'callback_data' => 'ap_card_edit:' . $type['id']];
    }
    $rows = [];
    $chunk = [];
    foreach ($buttons as $btn) {
        $chunk[] = $btn;
        if (count($chunk) >= 3) {
            $rows[] = $chunk;
            $chunk = [];
        }
    }
    if (!empty($chunk)) {
        $rows[] = $chunk;
    }
    $rows[] = [
        ['text' => $TXT['ap_card_add_btn'] ?? '➕ افزودن کارت', 'callback_data' => 'ap_card_add']
    ];
    $rows[] = [
        ['text' => $TXT['ap_card_back_btn'] ?? 'بازگشت', 'callback_data' => 'ap_panel_back']
    ];
    return [$text, ['inline_keyboard' => $rows]];
}

function admin_card_type_item_components(array $card)
{
    global $TXT;
    $fallback = $TXT['card_type_fallback_label'] ?? 'کارت';
    $title = $card['title'] !== '' ? $card['title'] : $fallback;
    $sticker = $card['sticker'] !== '' ? $card['sticker'] : ($TXT['ap_card_empty_value'] ?? '—');
    $tpl = $TXT['ap_card_item_template'] ?? '';
    if ($tpl === '') {
        $text = '<b>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</b>'
            . "\n" . '<b>استیکر:</b> ' . htmlspecialchars($sticker, ENT_QUOTES, 'UTF-8');
    } else {
        $text = strtr($tpl, [
            '{title}' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            '{sticker}' => htmlspecialchars($sticker, ENT_QUOTES, 'UTF-8'),
        ]);
    }
    $rows = [
        [
            ['text' => $TXT['ap_card_edit_label_btn'] ?? '📝 ویرایش عنوان', 'callback_data' => 'ap_card_edit_label:' . $card['id']]
        ],
        [
            ['text' => $TXT['ap_card_edit_sticker_btn'] ?? '🌁 استیکر', 'callback_data' => 'ap_card_edit_sticker:' . $card['id']]
        ],
        [
            ['text' => $TXT['ap_card_delete_btn'] ?? '🗑 حذف', 'callback_data' => 'ap_card_delete:' . $card['id']]
        ],
        [
            ['text' => $TXT['ap_card_back_btn'] ?? 'بازگشت', 'callback_data' => 'ap_card_types']
        ],
    ];
    return [$text, ['inline_keyboard' => $rows]];
}

function admin_card_editor_handle_message($uid, $message)
{
    global $TXT;
    $pending = admin_card_editor_get($uid);
    if (!$pending) {
        return false;
    }
    $action = $pending['action'] ?? '';
    $card_id = $pending['card_id'] ?? '';
    $text = trim((string)($message['text'] ?? ''));
    $lower = mb_strtolower($text, 'UTF-8');
    if ($text !== '' && in_array($lower, ['/cancel', 'cancel', 'لغو'], true)) {
        admin_card_editor_set($uid, null);
        api('sendMessage', ['chat_id' => $uid, 'text' => $TXT['ap_card_edit_cancelled'] ?? '', 'parse_mode' => 'HTML']);
        return true;
    }
    if ($action === 'card_add') {
        $draft = $pending['draft'] ?? ['title' => '', 'card_number' => '', 'holder' => '', 'sticker' => ''];
        $stage = $pending['stage'] ?? 'title';
        if ($stage === 'title') {
            if ($text === '') {
                $reply = $TXT['ap_card_add_format_error'] ?? ($TXT['ap_card_add_prompt'] ?? '');
                if ($reply !== '') {
                    api('sendMessage', ['chat_id' => $uid, 'text' => $reply, 'parse_mode' => 'HTML']);
                }
                return true;
            }
            if (isset($message['sticker'])) {
                $reply = $TXT['ap_card_add_prompt'] ?? '';
                if ($reply !== '') {
                    api('sendMessage', ['chat_id' => $uid, 'text' => $reply, 'parse_mode' => 'HTML']);
                }
                return true;
            }
            $draft['title'] = $text;
            admin_card_editor_set($uid, ['action' => 'card_add', 'stage' => 'sticker', 'draft' => $draft]);
            $reply = $TXT['ap_card_add_wait_sticker'] ?? '';
            if ($reply !== '') {
                api('sendMessage', ['chat_id' => $uid, 'text' => $reply, 'parse_mode' => 'HTML']);
            }
            return true;
        }
        if ($stage === 'sticker') {
            if (!isset($message['sticker'])) {
                $reply = $TXT['ap_card_add_sticker_only'] ?? ($TXT['ap_card_add_wait_sticker'] ?? '');
                if ($reply !== '') {
                    api('sendMessage', ['chat_id' => $uid, 'text' => $reply, 'parse_mode' => 'HTML']);
                }
                return true;
            }
            $draft['sticker'] = trim((string)($message['sticker']['file_id'] ?? ''));
            admin_card_editor_finish_card_add($uid, $draft);
            return true;
        }
        admin_card_editor_set($uid, [
            'action' => 'card_add',
            'stage' => 'title',
            'draft' => ['title' => '', 'card_number' => '', 'holder' => '', 'sticker' => ''],
        ]);
        $reply = $TXT['ap_card_add_prompt'] ?? '';
        if ($reply !== '') {
            api('sendMessage', ['chat_id' => $uid, 'text' => $reply, 'parse_mode' => 'HTML']);
        }
        return true;
    }
    if ($card_id === '') {
        admin_card_editor_set($uid, null);
        api('sendMessage', ['chat_id' => $uid, 'text' => $TXT['ap_card_not_found'] ?? '', 'parse_mode' => 'HTML']);
        return true;
    }
    $card = card_type_get($card_id);
    if (!$card) {
        admin_card_editor_set($uid, null);
        api('sendMessage', ['chat_id' => $uid, 'text' => $TXT['ap_card_not_found'] ?? '', 'parse_mode' => 'HTML']);
        return true;
    }
    if ($action === 'card_edit_label') {
        if ($text === '') {
            api('sendMessage', ['chat_id' => $uid, 'text' => $TXT['ap_card_edit_label_prompt'] ?? '', 'parse_mode' => 'HTML']);
            return true;
        }
        $card['title'] = $text;
        card_type_save($card);
        admin_card_editor_set($uid, null);
        api('sendMessage', ['chat_id' => $uid, 'text' => $TXT['ap_card_saved'] ?? ($TXT['ap_saved'] ?? ''), 'parse_mode' => 'HTML']);
        return true;
    }
    if ($action === 'card_edit_sticker') {
        if (isset($message['sticker'])) {
            $card['sticker'] = $message['sticker']['file_id'] ?? '';
            card_type_save($card);
            admin_card_editor_set($uid, null);
            api('sendMessage', ['chat_id' => $uid, 'text' => $TXT['ap_card_saved'] ?? ($TXT['ap_saved'] ?? ''), 'parse_mode' => 'HTML']);
            return true;
        }
        if ($text !== '' && in_array($lower, ['remove', 'delete', 'حذف'], true)) {
            $card['sticker'] = '';
            card_type_save($card);
            admin_card_editor_set($uid, null);
            api('sendMessage', ['chat_id' => $uid, 'text' => $TXT['ap_card_sticker_removed'] ?? ($TXT['ap_saved'] ?? ''), 'parse_mode' => 'HTML']);
            return true;
        }
        api('sendMessage', ['chat_id' => $uid, 'text' => $TXT['ap_card_edit_sticker_prompt'] ?? '', 'parse_mode' => 'HTML']);
        return true;
    }
    return false;
}

function admin_card_editor_finish_card_add($uid, array $draft)
{
    global $TXT;
    $item = [
        'title' => trim((string)($draft['title'] ?? '')),
        'card_number' => trim((string)($draft['card_number'] ?? '')),
        'holder' => trim((string)($draft['holder'] ?? '')),
        'sticker' => trim((string)($draft['sticker'] ?? '')),
    ];
    card_type_save($item);
    admin_card_editor_set($uid, null);
    $text = $TXT['ap_card_added'] ?? ($TXT['ap_saved'] ?? '');
    if ($text !== '') {
        api('sendMessage', ['chat_id' => $uid, 'text' => $text, 'parse_mode' => 'HTML']);
    }
}
