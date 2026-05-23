<?php
/* ============================================================================
   UUID — offline/online UUID + PlayerPoints key resolver
   ========================================================================== */

/* ============================================================================
   DOGECOIN — lớp tiền tệ thống nhất (gộp Xu + Kim Cương).
   Nguồn: plugin PlayerPoints (nếu pp_enabled) hoặc cột mirror web_wallet.dogecoin.
   ========================================================================== */

/* UUID offline (md5 "OfflinePlayer:<tên>") — chuẩn server offline/cracked & PlayerPoints */
function offline_uuid($name){
  $h = md5('OfflinePlayer:'.$name, true);
  $h[6] = chr((ord($h[6]) & 0x0f) | 0x30);   // version 3
  $h[8] = chr((ord($h[8]) & 0x3f) | 0x80);   // variant
  $x = bin2hex($h);
  return substr($x,0,8).'-'.substr($x,8,4).'-'.substr($x,12,4).'-'.substr($x,16,4).'-'.substr($x,20,12);
}
/* UUID online (Mojang) — tra 1 lần rồi cache vào web_uuid */
function online_uuid($name){
  try{
    $st=db()->prepare("SELECT uuid FROM web_uuid WHERE username=? LIMIT 1"); $st->execute([strtolower($name)]);
    $r=$st->fetch(); if($r && $r['uuid']) return $r['uuid'];
  }catch(Exception $e){}
  $uuid=''; $url='https://api.mojang.com/users/profiles/minecraft/'.rawurlencode($name);
  $raw=@file_get_contents($url);
  if($raw){ $j=json_decode($raw,true);
    if(!empty($j['id']) && strlen($j['id'])===32){ $i=$j['id'];
      $uuid=substr($i,0,8).'-'.substr($i,8,4).'-'.substr($i,12,4).'-'.substr($i,16,4).'-'.substr($i,20,12);
    }
  }
  if($uuid==='') $uuid=offline_uuid($name); // fallback an toàn
  try{ db()->prepare("INSERT INTO web_uuid(username,uuid,created) VALUES(?,?,?) ON DUPLICATE KEY UPDATE uuid=VALUES(uuid)")->execute([strtolower($name),$uuid,ms()]); }catch(Exception $e){}
  return $uuid;
}
/* Khoá định danh người chơi trong PlayerPoints theo cấu hình */
function pp_key($name){
  global $CFG; $m=$CFG['uuid_mode']??'offline';
  if($m==='username') return $name;
  if($m==='online')   return online_uuid($name);
  return offline_uuid($name);
}
