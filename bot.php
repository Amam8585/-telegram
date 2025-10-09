<?php
require __DIR__.'/config.php';
require __DIR__.'/txt.php';
require __DIR__.'/helpers.php';
require __DIR__.'/channel.php';
require __DIR__.'/handlers_main.php';
require __DIR__.'/handlers_admin.php';
require __DIR__.'/handlers_core.php';
$input=file_get_contents('php://input');
if(!$input){echo'OK';exit;}
$u=json_decode($input,true);
if(!is_array($u)){echo'OK';exit;}
if(function_exists('bot_should_ignore_update')&&bot_should_ignore_update($u)){
if(function_exists('bot_handle_disabled_notice')){bot_handle_disabled_notice($u);}echo'OK';exit;
}
handle_update($u);
echo'OK';
