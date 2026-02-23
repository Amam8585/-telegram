<?php
require __DIR__.'/../config.php';
require __DIR__.'/../txt.php';
require __DIR__.'/../helpers.php';
require __DIR__.'/../handlers_core.php';

$errors=[];
$gid=992001;

start_plan_now($gid,['id'=>$gid,'type'=>'supergroup','username'=>'demo_group'],12345);
$state=load_state($gid)?:[];

if (($state['acc_check_on'] ?? null) !== false) {
    $errors[]='default acc_check_on must be false';
}
if ((int)($state['fee_acc_check'] ?? -1) !== 0) {
    $errors[]='default fee_acc_check must be 0';
}

$kb=rules_kb($state,12345,false);
$label=(string)($kb['inline_keyboard'][2][0]['text'] ?? '');
if (mb_strpos($label,'❌')===false) {
    $errors[]='rules keyboard should show acc_check off by default';
}

$state['amount']=1000000;
$state['fee_base']=compute_fee($state['amount'],'normal');
$state['fee_extra_change']=0;
$state['fee_misc']=0;
$state['kyc_fee']=0;
$state['acc_check_on']=false;
$state['fee_acc_check']=0;
$totalOff=get_total($state);

$state['acc_check_on']=true;
$state['fee_acc_check']=10000;
$totalOn=get_total($state);

if (($totalOn-$totalOff)!==10000) {
    $errors[]='acc_check fee delta must be 10000';
}

del_state($gid);

if($errors){
    echo "FAIL\n";
    foreach($errors as $e){
        echo " - {$e}\n";
    }
    exit(1);
}

echo "OK\n";
