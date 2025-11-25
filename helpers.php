<?php
function api($m,$p,$multi=false){
    if(isset($GLOBALS['__telegram_api_hook']) && is_callable($GLOBALS['__telegram_api_hook'])){
        return call_user_func($GLOBALS['__telegram_api_hook'],$m,$p,$multi);
    }
    if($m==='sendMessage' && isset($p['parse_mode']) && strtoupper((string)$p['parse_mode'])==='HTML' && isset($p['text'])){
        $p['text']=telegram_normalize_html_text($p['text']);
    }
    $ch=curl_init();
    curl_setopt_array($ch,[CURLOPT_URL=>'https://api.telegram.org/bot'.BOT_TOKEN.'/'.$m,CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_POSTFIELDS=>$p]);
    if($multi){curl_setopt($ch,CURLOPT_HTTPHEADER,[]);} $r=curl_exec($ch);curl_close($ch);return json_decode($r,true);
}

function telegram_normalize_html_text($text){
    if(!is_string($text)||$text===''){
        return $text;
    }
    $search=['<br>','<br/>','<br />'];
    $text=str_ireplace($search,PHP_EOL,$text);
    $text=preg_replace('#<blockquote>\s+#u','<blockquote>',$text);
    $text=preg_replace('#\s+</blockquote>#u','</blockquote>',$text);
    $text=preg_replace("#\n{2,}<blockquote>#u","\n<blockquote>",$text);
    return $text;
}
function admin_all_ids(){static $cache=null;if($cache!==null)return $cache;$ids=[];if(defined('ADMIN_IDS')){$raw=ADMIN_IDS;if(!is_array($raw)){$raw=[$raw];}foreach($raw as $id){$id=trim((string)$id);if($id!==''){$ids[]=$id;}}}elseif(defined('ADMIN_ID')){$ids[]=(string)ADMIN_ID;}$cache=$ids;return $cache;}
function admin_primary_id(){ $ids=admin_all_ids();return $ids?($ids[0]):'';}
function admin_is_user($uid){if($uid===null)return false;$uid=(string)$uid;foreach(admin_all_ids() as $id){if($uid===$id)return true;}return false;}
function admin_broadcast($method,$params){$ids=admin_all_ids();if(!$ids)return false;foreach($ids as $aid){$payload=$params;$payload['chat_id']=$aid;api($method,$payload);}return true;}
function admin_mentions_text($TXT){$ids=admin_all_ids();if(!$ids)return '';$label_tpl=$TXT['admin_label_template']??'ادمین {index}';$link_tpl=$TXT['admin_tag_with_link']??'';$plain_tpl=$TXT['admin_tag_plain']??'';$out=[];$i=1;foreach($ids as $id){$label=strtr($label_tpl,['{index}'=>$i]);if($link_tpl!==''){$out[]=strtr($link_tpl,['{admin_id}'=>$id,'{admin_label}'=>$label]);}elseif($plain_tpl!==''){$out[]=strtr($plain_tpl,['{admin_label}'=>$label]);}else{$out[]=$label;}$i++;}return trim(implode(' ',$out));}

function generate_trade_password($prefix='Arianstore',$length=3){$chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';$chars_len=strlen($chars);if($chars_len===0){return $prefix;} $suffix='';for($i=0;$i<$length;$i++){try{$index=random_int(0,$chars_len-1);}catch(Exception $e){$index=mt_rand(0,$chars_len-1);} $suffix.=$chars[$index];}return $prefix.$suffix;}
function generate_trade_code(){try{return (string)random_int(10000,99999);}catch(Exception $e){return (string)mt_rand(10000,99999);} }
function data_dir(){return __DIR__.'/data';}
function ensure_dir(){if(!is_dir(data_dir()))@mkdir(data_dir(),0775,true);}
function st_path($cid){ensure_dir();return data_dir().'/plan_'.$cid.'.json';}
function ust_path($uid){ensure_dir();return data_dir().'/user_'.$uid.'.json';}
function load_state($cid){$p=st_path($cid);if(file_exists($p)){$j=file_get_contents($p);$a=json_decode($j,true);if(is_array($a))return $a;}return null;}
function save_state($cid,$a){file_put_contents(st_path($cid),json_encode($a,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);}
function del_state($cid){$p=st_path($cid);if(file_exists($p))@unlink($p);}
function load_uctx($uid){$p=ust_path($uid);if(file_exists($p)){$j=file_get_contents($p);$a=json_decode($j,true);if(is_array($a))return $a;}return null;}
function save_uctx($uid,$a){file_put_contents(ust_path($uid),json_encode($a,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);}
function list_plan_files(){ensure_dir();$g=glob(data_dir().'/plan_*.json');return $g?$g:[];}
function label_tick($on,$txt){return ($on?'✅ ':'⬜ ').$txt;}
function rules_kb($st,$uid,$locked=false){global $BTN;$kyc=$st['kyc']??[];$on_act=in_array('act',$kyc);$on_fb=in_array('fb',$kyc);$on_gg=in_array('gg',$kyc);$kyc_on=(bool)($st['kyc_on']??false);$misc_on=(bool)($st['misc_on']??false);$check_on=(bool)($st['acc_check_on']??true);$ack_on=isset(($st['acks']??[])[$uid]);$cb=function($x)use($locked){return $locked?'lock':$x;};$rows=[];if(!$misc_on){$rows[]=[['text'=>label_tick($on_gg,$BTN['google']),'callback_data'=>$cb('tgl_gg')],['text'=>label_tick($on_fb,$BTN['facebook']),'callback_data'=>$cb('tgl_fb')],['text'=>label_tick($on_act,$BTN['activision']),'callback_data'=>$cb('tgl_act')]];}$rows[]=[['text'=>($kyc_on?$BTN['kyc_on']:$BTN['kyc_off']),'callback_data'=>$cb('tgl_kyc')],['text'=>($misc_on?$BTN['misc_on']:$BTN['misc_off']),'callback_data'=>$cb('tgl_misc')]];$rows[]=[['text'=>($check_on?$BTN['acc_check_on']:$BTN['acc_check_off']),'callback_data'=>$cb('tgl_acc_check')]];$rows[]=[['text'=>($ack_on?$BTN['ack_me_on']:$BTN['ack_me_off']),'callback_data'=>$cb('ack_go')]];return ['inline_keyboard'=>$rows];}
function fbq_kb($locked=false){global $BTN;$cb=function($x)use($locked){return $locked?'lock':$x;};return ['inline_keyboard'=>[[['text'=>$BTN['fbchg_on'],'callback_data'=>$cb('fbchg_on')],['text'=>$BTN['fbchg_off'],'callback_data'=>$cb('fbchg_off')]]]];}
function method_kb(){global $BTN;return ['inline_keyboard'=>[[['text'=>$BTN['m_auto'],'callback_data'=>'m_auto'],['text'=>$BTN['m_card'],'callback_data'=>'m_card']]]];}
function card_types_path(){ensure_dir();return data_dir().'/card_types.json';}
function card_type_normalize(array $item){
    $id=trim((string)($item['id']??''));
    if($id===''){$id=bin2hex(random_bytes(3));}
    $title=trim((string)($item['title']??''));
    $card=trim((string)($item['card_number']??''));
    $holder=trim((string)($item['holder']??''));
    $sticker=trim((string)($item['sticker']??''));
    return ['id'=>$id,'title'=>$title,'card_number'=>$card,'holder'=>$holder,'sticker'=>$sticker];
}
function card_types_all(){
    $path=card_types_path();
    $list=[];
    if(file_exists($path)){
        $json=file_get_contents($path);
        $data=json_decode($json,true);
        if(is_array($data)){
            foreach($data as $item){
                if(!is_array($item))continue;
                $list[]=card_type_normalize($item);
            }
        }
    }
    return $list;
}
function card_types_save(array $items){
    if(empty($items)){
        $path=card_types_path();
        if(file_exists($path)){@unlink($path);}return;
    }
    $clean=[];
    foreach($items as $item){
        if(!is_array($item))continue;
        $clean[]=card_type_normalize($item);
    }
    file_put_contents(card_types_path(),json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);
}
function card_types_indexed(){
    $items=card_types_all();
    $indexed=[];
    foreach($items as $item){
        $indexed[$item['id']]=$item;
    }
    return $indexed;
}
function card_type_get($id){
    $id=trim((string)$id);
    if($id==='')return null;
    $all=card_types_all();
    foreach($all as $item){
        if($item['id']===$id)return $item;
    }
    return null;
}
function card_type_save(array $item){
    $item=card_type_normalize($item);
    $all=card_types_all();
    $found=false;
    foreach($all as $idx=>$info){
        if($info['id']===$item['id']){
            $all[$idx]=$item;
            $found=true;
            break;
        }
    }
    if(!$found){$all[]=$item;}
    card_types_save($all);
    return $item;
}
function card_type_delete($id){
    $id=trim((string)$id);
    if($id==='')return false;
    $all=card_types_all();
    $filtered=[];
    $deleted=false;
    foreach($all as $item){
        if($item['id']===$id){$deleted=true;continue;}
        $filtered[]=$item;
    }
    if($deleted){card_types_save($filtered);}
    return $deleted;
}

function build_admin_info_message($gid,&$st){
    global $TXT,$BTN;
    $glink=ensure_admin_group_link($gid,$st);
    $user_link_tpl=$TXT['user_link_template']??'';
    $view_profile=$TXT['admin_profile_view_label']??'';
    $missing_html=$TXT['admin_info_missing_value']??'<b>نامشخص</b>';
    $missing_plain=trim(strip_tags($missing_html));
    $seller_id=(int)($st['seller_id']??0);
    $seller_username=$st['seller_username']??'';
    $seller_link_label=$seller_username!==''?'@'.$seller_username:$view_profile;
    $seller_tag=$seller_id>0
        ? ($user_link_tpl!==''?strtr($user_link_tpl,['{user_id}'=>$seller_id,'{label}'=>$seller_link_label]):$seller_link_label)
        : $missing_html;
    $buyer_id=(int)($st['buyer_id']??0);
    $buyer_username=$st['buyer_username']??'';
    $buyer_link_label=$buyer_username!==''?'@'.$buyer_username:$view_profile;
    $buyer_tag=$buyer_id>0
        ? ($user_link_tpl!==''?strtr($user_link_tpl,['{user_id}'=>$buyer_id,'{label}'=>$buyer_link_label]):$buyer_link_label)
        : $missing_html;
    $seller_email_txt=trim($st['seller_email']??'');
    $seller_email_plain=$seller_email_txt!==''?htmlspecialchars($seller_email_txt):$missing_plain;
    $seller_email_html=$seller_email_txt!==''?'<b>'.htmlspecialchars($seller_email_txt).'</b>':$missing_html;
    $seller_pass_txt=$st['seller_pass']??'';
    $seller_pass_plain=$seller_pass_txt!==''?htmlspecialchars($seller_pass_txt):$missing_plain;
    $seller_pass_html=$seller_pass_txt!==''?'<b>'.htmlspecialchars($seller_pass_txt).'</b>':$missing_html;
    $buyer_email_txt=trim($st['buyer_email']??'');
    $buyer_email_plain=$buyer_email_txt!==''?htmlspecialchars($buyer_email_txt):$missing_plain;
    $buyer_email_html=$buyer_email_txt!==''?'<b>'.htmlspecialchars($buyer_email_txt).'</b>':$missing_html;
    $info_tpl=$TXT['admin_info_template']??'';
    if($info_tpl!==''){
        $adm_text=strtr($info_tpl,[
            '{buyer}'=>$buyer_tag,
            '{buyer_email}'=>$buyer_email_plain,
            '{seller}'=>$seller_tag,
            '{seller_email}'=>$seller_email_plain,
            '{seller_pass}'=>$seller_pass_plain,
        ]);
    }else{
        $adm_text=$TXT['admin_info_title']."\n"
            .$TXT['admin_info_buyer'].$buyer_tag."\n"
            .$TXT['admin_info_buyer_email']."\n".$buyer_email_html."\n"
            .$TXT['admin_info_seller'].$seller_tag."\n"
            .$TXT['admin_info_email']."\n".$seller_email_html."\n"
            .$TXT['admin_info_pass']."\n".$seller_pass_html;
    }
    $kb_rows=[
        [
            ['text'=>$BTN['admin_request_code'],'callback_data'=>'req_code:'.$gid]
        ],
        [
            ['text'=>$BTN['admin_msg_seller'],'callback_data'=>'msg_seller:'.$gid],
            ['text'=>$BTN['admin_msg_buyer'],'callback_data'=>'msg_buyer:'.$gid]
        ],
        [
            ['text'=>$BTN['seller_wrong'],'callback_data'=>'seller_bad:'.$gid]
        ],
        [
            ['text'=>$BTN['seller_regen_pass']??'🈴 | تغییر رمز','callback_data'=>'regen_pass:'.$gid]
        ],
        [
            $glink!==''
                ? ['text'=>$BTN['admin_group'],'url'=>$glink]
                : ['text'=>$BTN['admin_group'],'callback_data'=>'no_group:'.$gid]
        ]
    ];
    $reply_markup=json_encode(['inline_keyboard'=>$kb_rows],JSON_UNESCAPED_UNICODE);
    return ['text'=>$adm_text,'reply_markup'=>$reply_markup];
}
function admin_card_editor_path(){ensure_dir();return data_dir().'/card_editor.json';}
function admin_card_editor_all(){
    $path=admin_card_editor_path();
    if(file_exists($path)){
        $json=file_get_contents($path);
        $data=json_decode($json,true);
        if(is_array($data))return $data;
    }
    return [];
}
function admin_card_editor_get($uid){
    $uid=(string)$uid;
    if($uid==='')return null;
    $all=admin_card_editor_all();
    return $all[$uid]??null;
}
function admin_card_editor_set($uid,$info){
    $uid=(string)$uid;
    if($uid==='')return;
    $all=admin_card_editor_all();
    if($info===null){
        unset($all[$uid]);
    }else{
        $all[$uid]=$info;
    }
    $path=admin_card_editor_path();
    if(empty($all)){
        if(file_exists($path)){@unlink($path);}return;
    }
    file_put_contents($path,json_encode($all,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
}
function card_build_payment_text($card_number,$holder,$total){
    global $TXT;
    $lines=[];
    $card_label=trim((string)($TXT['card_number_label']??''));
    if($card_label!==''){
        $card=trim((string)$card_number);
        if($card===''){
            $card=(defined('ADMIN_CARD')&&ADMIN_CARD)?ADMIN_CARD:((defined('CARD_NUMBER')&&CARD_NUMBER)?CARD_NUMBER:'6219-0000-0000-0000');
        }
        $lines[]=$card_label.'<code>'.htmlspecialchars($card,ENT_QUOTES,'UTF-8').'</code>';
    }
    $holder_tpl=$TXT['card_holder_line_template']??'';
    $holder=trim((string)$holder);
    if($holder_tpl!==''&&$holder!==''){
        $lines[]=strtr($holder_tpl,['{holder}'=>htmlspecialchars($holder,ENT_QUOTES,'UTF-8')]);
    }
    $currency=$TXT['currency_suffix_plain']??'';
    $amount_tpl=$TXT['card_amount_line_template']??'';
    if($amount_tpl!==''){
        $lines[]=strtr($amount_tpl,['{amount}'=>number_format((int)$total),'{currency}'=>$currency]);
    }else{
        $value_tpl=$TXT['card_amount_value_template']??'';
        $amount_line=$value_tpl!==''
            ? strtr($value_tpl,['{amount}'=>number_format((int)$total),'{currency}'=>$currency])
            : number_format((int)$total).($currency!==''?' '.$currency:'');
        $lines[]=($TXT['card_amount_label']??'').$amount_line;
    }
    $after=$TXT['card_after_label']??'';
    if($after!==''){
        $lines[]=$after;
    }
    $lines=array_values(array_filter($lines,function($line){return trim($line)!=='';}));
    return implode("\n",$lines);
}
function need_fb_question($st){$a=$st['kyc']??[];$hasAct=in_array('act',$a);$hasFb=in_array('fb',$a);$hasGg=in_array('gg',$a);return ($hasAct&&$hasFb)||($hasAct&&$hasGg)||($hasGg&&$hasFb&&!$hasAct);}
function compute_fee($amount){if($amount<0)$amount=0;$k=1000;$mm=1000000;$r=[[0,400*$k,15000],[400*$k,900*$k,20000],[900*$k,1.5*$mm,25000],[1.5*$mm,2*$mm,30000],[2*$mm,2.5*$mm,40000],[2.5*$mm,3*$mm,60000],[3*$mm,3.5*$mm,70000],[3.5*$mm,4*$mm,80000],[4*$mm,4.5*$mm,90000],[4.5*$mm,5*$mm,110000],[5*$mm,5.5*$mm,120000],[5.5*$mm,6*$mm,140000],[6*$mm,6.5*$mm,160000],[6.5*$mm,7*$mm,185000],[7*$mm,7.5*$mm,200000],[7.5*$mm,8*$mm,235000],[8*$mm,8.5*$mm,265000],[8.5*$mm,9*$mm,290000],[9*$mm,10*$mm,345000],[10*$mm,11*$mm,400000],[11*$mm,12*$mm,500000],[12*$mm,13*$mm,650000],[13*$mm,14*$mm,750000],[14*$mm,15*$mm,850000]];foreach($r as $x){if($amount>=$x[0]&&$amount<$x[1])return $x[2];}return 850000;}
function calc_gateway_fee_toman($toman){$fee=(int)round($toman*0.005);if($fee>12000)$fee=12000;if($fee<0)$fee=0;return $fee;}
function get_total($st){$amount=(int)($st['amount']??0);$fee_base=(int)($st['fee_base']??($st['fee']??0));$fee_extra=(int)($st['fee_extra_change']??0);$fee_misc=(int)($st['fee_misc']??0);$kyc=(int)($st['kyc_fee']??0);$check_on=(bool)($st['acc_check_on']??true);$check_fee=(int)($st['fee_acc_check']??($check_on?5000:0));if(!$check_on){$check_fee=0;}return $amount+$fee_base+$fee_extra+$fee_misc+$kyc+$check_fee;}
function get_total_with_gateway($st){$base=get_total($st);$gw=0;if(($st['pay_method']??'')==='auto'){$gw=calc_gateway_fee_toman($base);}return $base+$gw;}
function invoice_text($st){
    global $TXT;
    $normalize=function($s){
        $s=trim((string)$s);
        $s=rtrim($s,":：");
        return $s;
    };

    $title=$normalize($TXT['invoice_title_plain']??'');
    $header_tpl=$TXT['invoice_header_template']??'';
    $p_acc=$TXT['invoice_acc_price_plain']??'';
    $p_fee=$TXT['invoice_fee_plain']??'';
    $p_kyc=$TXT['invoice_kyc_plain']??'';
    $p_fb=$TXT['invoice_fbchg_plain']??'';
    $p_misc=$TXT['invoice_misc_plain']??'';
    $p_check_tpl=$TXT['invoice_acc_check_notice']??'';
    $p_check_plain=$TXT['invoice_acc_check_plain']??'';
    $p_gw=$TXT['invoice_gateway_plain']??'';
    $trade_raw=trim((string)($TXT['invoice_trade_code_plain']??''));
    $p_trade=rtrim($trade_raw,":： \t");
    $t_title=$normalize($TXT['invoice_total_title_plain']??'');
    $total_heading_tpl=$TXT['invoice_total_heading_template']??'';
    $total_amount_tpl=$TXT['invoice_total_amount_template']??'';
    $p_saves=$TXT['invoice_saves_plain']??'';
    $currency=$TXT['currency_suffix_plain']??'';
    $gw_notice=$TXT['invoice_gateway_notice']??'';

    $amount=(int)($st['amount']??0);
    $fee_base=(int)($st['fee_base']??($st['fee']??0));
    $kycfee=(int)($st['kyc_fee']??0);
    $fbchg=(int)($st['fee_extra_change']??0);
    $misc=(int)($st['fee_misc']??0);
    $check_on=(bool)($st['acc_check_on']??true);
    $checkfee=$check_on?(int)($st['fee_acc_check']??5000):0;
    $base_total=get_total($st);
    $gw_fee=(($st['pay_method']??'')==='auto')?calc_gateway_fee_toman($base_total):0;
    $trade_code=trim((string)($st['trade_code']??''));

    $out=[];
    if($title!==''){
        $header=$header_tpl!==''?strtr($header_tpl,['{title}'=>$title]):$title;
        $out[]=$header;
    }
    if($trade_code!==''&&$p_trade!==''){
        $trade_label=$p_trade;
        if(substr($trade_label,-1)!==':'){$trade_label.=' :';}
        $out[]='<blockquote><b>'.$trade_label.' '.htmlspecialchars($trade_code,ENT_QUOTES,'UTF-8').'</b></blockquote>';
    }
    if($amount>0&&$p_acc!==''){
        $line='<blockquote><b>'.$p_acc.' '.number_format($amount);
        if($currency!==''){$line.=' '.$currency;}
        $line.='</b></blockquote>';
        $out[]=$line;
    }
    if($fee_base>0&&$p_fee!==''){
        $line='<blockquote><b>'.$p_fee.' '.number_format($fee_base);
        if($currency!==''){$line.=' '.$currency;}
        $line.='</b></blockquote>';
        $out[]=$line;
    }
    if($kycfee>0&&$p_kyc!==''){
        $line='<blockquote><b>'.$p_kyc.' '.number_format($kycfee);
        if($currency!==''){$line.=' '.$currency;}
        $line.='</b></blockquote>';
        $out[]=$line;
    }
    if($fbchg>0&&$p_fb!==''){
        $line='<blockquote><b>'.$p_fb.' '.number_format($fbchg);
        if($currency!==''){$line.=' '.$currency;}
        $line.='</b></blockquote>';
        $out[]=$line;
    }
    if($misc>0&&$p_misc!==''){
        $line='<blockquote><b>'.$p_misc.' '.number_format($misc);
        if($currency!==''){$line.=' '.$currency;}
        $line.='</b></blockquote>';
        $out[]=$line;
    }
    if($checkfee>0){
        if($p_check_tpl!==''){
            $out[]=strtr($p_check_tpl,['{amount}'=>number_format($checkfee)]);
        }elseif($p_check_plain!==''){
            $line='<blockquote><b>'.$p_check_plain.' '.number_format($checkfee);
            if($currency!==''){$line.=' '.$currency;}
            $line.='</b></blockquote>';
            $out[]=$line;
        }
    }
    if($gw_fee>0&&$p_gw!==''){
        $line='<blockquote><b>'.$p_gw.' '.number_format($gw_fee);
        if($currency!==''){$line.=' '.$currency;}
        $line.='</b></blockquote>';
        $out[]=$line;
    }elseif($gw_fee<=0&&$gw_notice!==''){
        $out[]=$gw_notice;
    }
    $final_total=(($st['pay_method']??'')==='auto')?$base_total+$gw_fee:$base_total;
    if($t_title!==''){
        $out[]=($total_heading_tpl!==''?strtr($total_heading_tpl,['{title}'=>$t_title]):$t_title);
    }
    if($total_amount_tpl!==''){
        $out[]=strtr($total_amount_tpl,['{amount}'=>number_format($final_total),'{currency}'=>$currency]);
    }
    $names=[];
    if(($st['misc_on']??false)===true){
        $misc_name=$TXT['save_name_misc']??'';
        if($misc_name!==''){$names[]=$misc_name;}
    }else{
        $kyc=$st['kyc']??[];
        $map=['act'=>'save_name_act','fb'=>'save_name_fb','gg'=>'save_name_gg'];
        foreach($map as $code=>$key){
            if(in_array($code,$kyc)){
                $nm=$TXT[$key]??'';
                if($nm!==''){$names[]=$nm;}
            }
        }
    }
    if($p_saves!==''&&!empty($names)){
        $out[]='<blockquote><b>'.$p_saves.' '.implode(' ',$names).'</b></blockquote>';
    }
    return implode("\n",$out);
}
function invoice_kb($st,$uid,$locked=false){global $BTN;$myack=(($st['amt_acks']??[])&&isset($st['amt_acks'][$uid]));$cb=function($x)use($locked){return $locked?'lock':$x;};return ['inline_keyboard'=>[[['text'=>$myack?$BTN['amt_ok_me_on']:$BTN['amt_ok_me_off'],'callback_data'=>$cb('amt_ok')],['text'=>$BTN['amt_edit'],'callback_data'=>$cb('amt_edit')]]]];}
function parse_start($p){if(preg_match('/^get_([A-Za-z0-9]+)$/',$p,$m))return ['kind'=>'get','token'=>$m[1]];if(preg_match('/^(buyer|seller)_(\-?\d+)_([A-Za-z0-9]+)$/',$p,$m))return ['kind'=>'role','role'=>$m[1],'chat_id'=>$m[2],'token'=>$m[3]];return null;}
function find_chat_by_token($tok){$files=list_plan_files();foreach($files as $f){$a=@json_decode(@file_get_contents($f),true);if(!is_array($a))continue;$lt=$a['link_tokens']??null;if(is_array($lt)){if(($lt['buyer']??'')===$tok)return ['chat_id'=>str_replace(['plan_','.json'],['',''],basename($f)),'role'=>'buyer'];if(($lt['seller']??'')===$tok)return ['chat_id'=>str_replace(['plan_','.json'],['',''],basename($f)),'role'=>'seller'];}}return null;}
function base_url_here(){$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';$host=$_SERVER['HTTP_HOST']??'';$path=rtrim(dirname($_SERVER['SCRIPT_NAME']??''),'/');return $scheme.'://'.$host.$path;}
function build_zp_link($cid,$amount_toman,$payer_id){$st=load_state($cid);$base_total=is_array($st)?(int)($st['total']??get_total($st)):(int)$amount_toman;$gw_fee=calc_gateway_fee_toman($base_total);$pay_toman=$base_total+$gw_fee;if(is_array($st)){$st['pay_method']='auto';$st['gateway_fee']=$gw_fee;$st['total_with_gateway']=$pay_toman;save_state($cid,$st);}ensure_dir();$ordersFile=data_dir().'/zp_orders.json';$orders=@json_decode(@file_get_contents($ordersFile),true);if(!is_array($orders))$orders=[];$code=bin2hex(random_bytes(6));$orders[$code]=['cid'=>$cid,'payer'=>$payer_id,'amount_toman'=>$pay_toman,'gateway_fee'=>$gw_fee,'created'=>time()];@file_put_contents($ordersFile,json_encode($orders,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);$amount_rial=$pay_toman*10;$base=base_url_here();$url=$base.'/zp_req.php?chat_id='.rawurlencode((string)$payer_id).'&amount='.$amount_rial;return ['ok'=>true,'url'=>$url,'code'=>$code,'toman'=>$pay_toman,'gateway_fee'=>$gw_fee];}
function whitelist_user_ids(){static $cache=null;if($cache!==null)return $cache;$cache=[];$file=__DIR__.'/list.php';if(file_exists($file)){$data=require $file;if(is_array($data)){foreach($data as $id){$id=trim((string)$id);if($id==='')continue;$cache[]=$id;}}}return $cache;}
function is_whitelisted_user($uid){if($uid===null)return false;$uid=trim((string)$uid);if($uid==='')return false;foreach(whitelist_user_ids() as $id){if($uid===(string)$id)return true;}return false;}
function plan_candidate_user_ids($st){$out=[];$add=function($id)use(&$out){$id=(int)$id;if($id<=0)return;$out[$id]=$id;};if(isset($st['acks'])&&is_array($st['acks'])){foreach($st['acks'] as $id=>$flag){$add($id);}}if(isset($st['receipts'])&&is_array($st['receipts'])){foreach($st['receipts'] as $info){if(is_array($info)){$add($info['from']??0);}}}if(isset($st['link_tokens_used'])&&is_array($st['link_tokens_used'])){foreach($st['link_tokens_used'] as $info){$add($info);}}if(isset($st['payer_id'])){$add($st['payer_id']);}return array_values($out);}
function auto_assign_trade_roles(&$st){$changed=false;$buyer_id=(int)($st['buyer_id']??0);if($buyer_id>0&&is_whitelisted_user($buyer_id)){$st['buyer_id']=0;if(isset($st['buyer_username'])){$st['buyer_username']='';}$buyer_id=0;$changed=true;}if($buyer_id<=0)return $changed;$seller_id=(int)($st['seller_id']??0);if($seller_id>0&&($seller_id===$buyer_id||is_whitelisted_user($seller_id))){$st['seller_id']=0;if(isset($st['seller_username'])){$st['seller_username']='';}$seller_id=0;$changed=true;}$candidates=plan_candidate_user_ids($st);if($seller_id<=0){foreach($candidates as $candidate){if($candidate===$buyer_id)continue;if(is_whitelisted_user($candidate))continue;$st['seller_id']=$candidate;if(isset($st['seller_username'])&&$st['seller_username']!==''){$st['seller_username']='';}$changed=true;break;}}return $changed;}
function admin_flags_path(){ensure_dir();return data_dir().'/admin_flags.json';}
function admin_flags_all(){
$path=admin_flags_path();
if(file_exists($path)){
$json=file_get_contents($path);
$data=json_decode($json,true);
if(is_array($data))return $data;
}
return [];
}
function admin_flags_save(array $flags){
if(!$flags){
$path=admin_flags_path();
if(file_exists($path))@unlink($path);
return;
}
file_put_contents(admin_flags_path(),json_encode($flags,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
}
function admin_flags_is_disabled($name){$flags=admin_flags_all();return (bool)($flags[$name]??false);}
function admin_flags_toggle($name){$flags=admin_flags_all();$current=(bool)($flags[$name]??false);$disabled=!$current;if($disabled){$flags[$name]=true;}else{unset($flags[$name]);}$path_flags=$flags;admin_flags_save($path_flags);return ['flags'=>$path_flags,'disabled'=>$disabled];}
