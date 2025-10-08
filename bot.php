<?php
require __DIR__.'/config.php';
require __DIR__.'/txt.php';
require __DIR__.'/helpers.php';
require __DIR__.'/handlers_admin.php';
require __DIR__.'/handlers_core.php';
$input=file_get_contents('php://input');
if(!$input){echo'OK';exit;}
$u=json_decode($input,true);
handle_update($u);
echo'OK';
