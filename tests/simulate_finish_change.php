<?php
require __DIR__.'/../config.php';
require __DIR__.'/../txt.php';
require __DIR__.'/../helpers.php';
require __DIR__.'/../handlers_admin.php';

$gid = 123456;
$state = [
    'buyer_id' => 555001,
    'buyer_username' => 'buyer_demo',
    'seller_id' => 555002,
    'seller_username' => 'seller_demo',
    'seller_pass' => 'Pass12345',
    'admin_paid_msg_id' => 77,
    'topic_id' => 88,
];
save_state($gid, $state);

$calls = [];
$messageCounters = ['sendMessage' => 0];
$GLOBALS['__telegram_api_hook'] = function($method, $params) use (&$calls, &$messageCounters, $gid) {
    $calls[] = ['method' => $method, 'params' => $params];
    if ($method === 'sendMessage') {
        $messageCounters['sendMessage']++;
        if ((int)($params['chat_id'] ?? 0) === $gid && isset($params['allow_sending_without_reply'])) {
            return ['ok' => false, 'description' => 'Bad Request: reply message not found'];
        }
        return ['ok' => true, 'result' => ['message_id' => $messageCounters['sendMessage']]];
    }
    return ['ok' => true];
};

echo "== سناریوی موفق با تلاش مجدد ==" . PHP_EOL;
admin_on_callback('finish_change:' . $gid, (int)ADMIN_ID, 'test-query', $gid, 10, []);

unset($GLOBALS['__telegram_api_hook']);
$groupMessages = array_values(array_filter($calls, function ($call) use ($gid) {
    return $call['method'] === 'sendMessage' && isset($call['params']['chat_id']) && (int)$call['params']['chat_id'] === $gid;
}));
$guideMessages = array_values(array_filter($groupMessages, function ($call) {
    return strpos($call['params']['text'], '🎞|') !== false;
}));

echo 'کل پیام‌های ارسال‌شده به گروه: ' . count($groupMessages) . PHP_EOL;
echo 'تعداد پیام‌های راهنما: ' . count($guideMessages) . PHP_EOL;
foreach ($guideMessages as $msg) {
    echo 'متن پیام راهنما:' . PHP_EOL;
    echo $msg['params']['text'] . PHP_EOL;
}

$noticeMessages = array_values(array_filter($groupMessages, function ($call) {
    return strpos($call['params']['text'], '📣| فروشنده') !== false;
}));
foreach ($noticeMessages as $msg) {
    echo 'متن اعلان کوتاه:' . PHP_EOL;
    echo $msg['params']['text'] . PHP_EOL;
}

del_state($gid);

$gidFail = 654321;
$errorDesc = 'Forbidden: bot was kicked from the supergroup chat';
$stateFail = [
    'phase' => 'await_finish_button',
    'buyer_id' => 700001,
    'seller_id' => 700002,
    'seller_pass' => 'Pass54321',
    'admin_paid_msg_id' => 91,
];
save_state($gidFail, $stateFail);

$calls = [];
$warningText = '';
$GLOBALS['__telegram_api_hook'] = function($method, $params) use (&$calls, &$warningText, $gidFail, $errorDesc) {
    $calls[] = ['method' => $method, 'params' => $params];
    if ($method === 'sendMessage') {
        if ((int)($params['chat_id'] ?? 0) === $gidFail) {
            return ['ok' => false, 'description' => $errorDesc];
        }
        return ['ok' => true, 'result' => ['message_id' => 1]];
    }
    if ($method === 'answerCallbackQuery') {
        $warningText = $params['text'] ?? '';
    }
    return ['ok' => true];
};

echo PHP_EOL . "== سناریوی خطا در ارسال به گروه ==" . PHP_EOL;
admin_on_callback('finish_change:' . $gidFail, (int)ADMIN_ID, 'test-query', $gidFail, 10, []);

unset($GLOBALS['__telegram_api_hook']);
$stateAfterFail = load_state($gidFail);
echo 'فاز پس از تلاش ناموفق: ' . ($stateAfterFail['phase'] ?? '—') . PHP_EOL;
echo 'آیا await_log تعیین شده؟ ' . (isset($stateAfterFail['await_log']) ? 'بله' : 'خیر') . PHP_EOL;
echo 'متن هشدار ادمین: ' . $warningText . PHP_EOL;

del_state($gidFail);
