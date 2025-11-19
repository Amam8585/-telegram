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
$GLOBALS['__telegram_api_hook'] = function($method, $params) use (&$calls, &$messageCounters) {
    $calls[] = ['method' => $method, 'params' => $params];
    if ($method === 'sendMessage') {
        $messageCounters['sendMessage']++;
        return ['ok' => true, 'result' => ['message_id' => $messageCounters['sendMessage']]];
    }
    return ['ok' => true];
};

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
