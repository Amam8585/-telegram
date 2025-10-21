<?php
define('BOT_TOKEN','7401933853:AAGIIDiDLN6Pl1M2VqRpqHRKRUstubQNWTU');
define('ADMIN_IDS',[
    '245136195',
    '5469468358',
]);
define('ADMIN_ID',ADMIN_IDS[0]);
define('ZP_MERCHANT_ID','6315601e-880b-4370-a022-f97da548bd87');
define('BOT_USERNAME','Uwhehshshhbot');
define('CARD_NUMBER','6219861860522605');
define('CHANNEL_FORCE_CHAT_ID', -1002293404787);
define('CHANNEL_FORCE_CHAT_LINK','https://t.me/Arian_storeGp');
define('CHANNEL_FORCE_JOIN_BUTTON_TEXT','عضویت در کانال');
if(!defined('BASE_URL')){
$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
$host=$_SERVER['HTTP_HOST']??'';
$script=$_SERVER['SCRIPT_NAME']??'';
$base=rtrim(str_replace(basename($script),'',$script),'/');
define('BASE_URL',$host?($scheme.'://'.$host.$base):'');
}
