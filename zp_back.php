<?php
require __DIR__.'/config.php';
require __DIR__.'/helpers.php';
$authority=trim($_GET['Authority']??'');
$pid=trim($_GET['chat_id']??'');
$amount=intval($_GET['amount']??0);
$merchant=(defined('ZP_MERCHANT_ID')?ZP_MERCHANT_ID:(defined('MerchantID')?MerchantID:''));if($merchant===''){$merchant=(isset($MerchantID)?$MerchantID:'');}
if($authority===''||$pid===''||$amount<=0||$merchant===''){http_response_code(400);exit('BAD REQUEST');}
function zp_find_plan_by_payer(string $uid):?int{$files=list_plan_files();$best=null;$bestTime=0;foreach($files as $f){$a=@json_decode(@file_get_contents($f),true);if(!is_array($a))continue;if((string)($a['payer_id']??'')===(string)$uid){$mtime=@filemtime($f);if($mtime>$bestTime){$bestTime=$mtime;$best=(int)str_replace(['plan_','.json'],['',''],basename($f));}}}return $best;}
$gid=zp_find_plan_by_payer($pid);if(!$gid){http_response_code(404);exit('NOT FOUND');}
$st=load_state($gid);if(!$st){http_response_code(404);exit('NOT FOUND');}
$verify=['merchant_id'=>$merchant,'authority'=>$authority,'amount'=>$amount];
$j=json_encode($verify,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$ch=curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
curl_setopt_array($ch,[CURLOPT_USERAGENT=>'ZarinPal Rest Api v4',CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$j,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Content-Length: '.strlen($j)]]);
$res=curl_exec($ch);
$err=curl_error($ch);
curl_close($ch);
$logfile=__DIR__.'/infopay.json';
if($err){$fp=@fopen($logfile,'c+');if($fp){@flock($fp,LOCK_EX);$old=@stream_get_contents($fp);$log=@json_decode($old,true);if(!is_array($log))$log=[];$log[]=['type'=>'verify_error','time'=>date('c'),'payer_id'=>$pid,'group_id'=>$gid,'authority'=>$authority,'amount_rial'=>$amount,'amount_toman'=>intval($amount/10),'error'=>$err];rewind($fp);ftruncate($fp,0);fwrite($fp,json_encode($log,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));fflush($fp);@flock($fp,LOCK_UN);fclose($fp);}http_response_code(502);exit('CURL ERROR: '.$err);}
$a=@json_decode($res,true);
$code=intval($a['data']['code']??0);
$ok=in_array($code,[100,101],true);
$ref=$a['data']['ref_id']??'';
$card=$a['data']['card_pan']??'';
$fee_type=$a['data']['fee_type']??'';
$fee=$a['data']['fee']??'';
$fp=@fopen($logfile,'c+');if($fp){@flock($fp,LOCK_EX);$old=@stream_get_contents($fp);$log=@json_decode($old,true);if(!is_array($log))$log=[];$log[]=['type'=>'verify','time'=>date('c'),'success'=>$ok,'code'=>$code,'payer_id'=>$pid,'group_id'=>$gid,'authority'=>$authority,'ref_id'=>$ref,'card_pan'=>$card,'fee_type'=>$fee_type,'fee'=>$fee,'amount_rial'=>$amount,'amount_toman'=>intval($amount/10)];rewind($fp);ftruncate($fp,0);fwrite($fp,json_encode($log,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));fflush($fp);@flock($fp,LOCK_UN);fclose($fp);}
if($ok){
$st['paid']=true;
$st['phase']='paid';
$st['zp_ref_id']=$ref;
$st['zp_authority']=$authority;
$st['zp_card_mask']=$card;
$st['paid_at']=date('Y-m-d H:i:s').' UTC';
$st['zp']=is_array($st['zp']??null)?$st['zp']:[];
$st['zp']['status']='done';
$st['zp']['expired']=true;
$st['zp']['expired_at']=date('c');
$buyer_token=bin2hex(random_bytes(8));
$seller_token=bin2hex(random_bytes(8));
$st['link_tokens']=['buyer'=>$buyer_token,'seller'=>$seller_token];
$st['link_tokens_used']=['buyer'=>null,'seller'=>null];
save_state($gid,$st);
if(isset($st['pay_msg'])&&is_array($st['pay_msg'])){
$pcid=(int)($st['pay_msg']['cid']??$gid);
$pmid=(int)($st['pay_msg']['mid']??0);
if($pmid>0){
api('editMessageText',['chat_id'=>$pcid,'message_id'=>$pmid,'text'=>'پرداخت موفق ✅','parse_mode'=>'HTML']);
api('editMessageReplyMarkup',['chat_id'=>$pcid,'message_id'=>$pmid,'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>'پرداخت موفق','callback_data'=>'paid_ok_locked']]]],JSON_UNESCAPED_UNICODE)]);
}
}
$botu=defined('BOT_USERNAME')?BOT_USERNAME:'';
$buyer_link=$botu!==''?('https://t.me/'.$botu.'?start=get_'.$buyer_token):'';
$seller_link=$botu!==''?('https://t.me/'.$botu.'?start=get_'.$seller_token):'';
$msg="پرداخت با موفقیت تایید شد.\nشماره پیگیری: <b>".htmlspecialchars((string)$ref)."</b>\nکارت پرداخت: <code>".htmlspecialchars($card?:'-')."</code>\n\nخریدار و فروشنده عزیز برای ادامه مراحل روی لینک‌های زیر بزنید:\n".($seller_link!==''?("لینک فروشنده:\n".$seller_link."\n"):"").($buyer_link!==''?("لینک خریدار:\n".$buyer_link."\n"):"")."\nهر لینک مخصوص همین گروه است.";
api('sendMessage',['chat_id'=>$gid,'text'=>$msg,'parse_mode'=>'HTML','disable_web_page_preview'=>true]);
echo 'OK';
exit;
}
http_response_code(200);
$emsg=isset($a['errors']['message'])?('ZP VERIFY ERROR '.$a['errors']['code'].': '.$a['errors']['message']):'FAILED';
echo $emsg;
