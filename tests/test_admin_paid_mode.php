<?php
require __DIR__.'/../config.php';
require __DIR__.'/../txt.php';
require __DIR__.'/../helpers.php';
require __DIR__.'/../handlers_core.php';
require __DIR__.'/../handlers_admin.php';

function run_admin_ok_scenario(int $gid, string $tradeMode): array {
    $state = [
        'phase' => 'await_admin_confirm',
        'trade_mode' => $tradeMode,
        'buyer_id' => 10001,
        'seller_id' => 10002,
        'receipts' => [
            'abc123' => ['from' => 10001, 'msg_id' => 321]
        ]
    ];
    save_state($gid, $state);

    $calls = [];
    $GLOBALS['__telegram_api_hook'] = function($method, $params) use (&$calls) {
        $calls[] = ['method' => $method, 'params' => $params];
        if ($method === 'sendMessage') {
            return ['ok' => true, 'result' => ['message_id' => 777]];
        }
        return ['ok' => true, 'result' => []];
    };

    $cb = [
        'id' => 'cb-1',
        'from' => ['id' => (int)ADMIN_ID, 'username' => 'admin_demo'],
        'message' => [
            'chat' => ['id' => $gid, 'type' => 'supergroup'],
            'message_id' => 111,
        ],
        'data' => 'admin_ok:abc123',
    ];

    handle_cb($cb);
    unset($GLOBALS['__telegram_api_hook']);

    $after = load_state($gid) ?: [];
    del_state($gid);

    return [$after, $calls];
}

[$economicState, $economicCalls] = run_admin_ok_scenario(910001, 'economic');
[$normalState, $normalCalls] = run_admin_ok_scenario(910002, 'normal');

$errors = [];

$ecoSend = null;
foreach ($economicCalls as $call) {
    if ($call['method'] === 'sendMessage') {
        $ecoSend = $call;
        break;
    }
}
$normSend = null;
foreach ($normalCalls as $call) {
    if ($call['method'] === 'sendMessage') {
        $normSend = $call;
        break;
    }
}

if (($economicState['phase'] ?? '') !== 'done') $errors[] = 'economic phase not done';
if (($normalState['phase'] ?? '') !== 'done') $errors[] = 'normal phase not done';

if (!array_key_exists('link_tokens', $economicState) || $economicState['link_tokens'] !== null) {
    $errors[] = 'economic link_tokens should be null';
}
if (isset($normalState['link_tokens']) && !is_array($normalState['link_tokens'])) {
    $errors[] = 'normal link_tokens should be array';
}
if (is_array($normalState['link_tokens'] ?? null)) {
    if (($normalState['link_tokens']['buyer'] ?? '') === '' || ($normalState['link_tokens']['seller'] ?? '') === '') {
        $errors[] = 'normal link tokens missing';
    }
}

if (($economicState['admin_paid_msg_id'] ?? -1) !== 0) {
    $errors[] = 'economic admin_paid_msg_id should be 0';
}
if (($normalState['admin_paid_msg_id'] ?? 0) <= 0) {
    $errors[] = 'normal admin_paid_msg_id should be set';
}

if (!$ecoSend || strpos((string)($ecoSend['params']['text'] ?? ''), 'لینک ورود خریدار') !== false) {
    $errors[] = 'economic message must not include buyer link';
}
if ($ecoSend && strpos((string)($ecoSend['params']['text'] ?? ''), 'توضیح روش معامله') === false) {
    $errors[] = 'economic message text mismatch';
}
if (!$normSend || strpos((string)($normSend['params']['text'] ?? ''), 'لینک ورود خریدار') === false) {
    $errors[] = 'normal message should include buyer link';
}

if ($errors) {
    echo "FAIL\n";
    foreach ($errors as $error) {
        echo " - {$error}\n";
    }
    exit(1);
}

echo "OK\n";
