<?php
/* ============================================================================
   NOTIFY — bell notifications + admin recipients
   ========================================================================== */

/* --- DB (kết nối lười: chỉ khi cần) --- */
/* ============================================================================
   HELPERS MỞ RỘNG — thông báo, giá nạp động, chat admin, gift code, mention
   ========================================================================== */
/* Tạo 1 thông báo cho 1 người (hiện ở chuông) */
function notify($to,$type,$title,$body='',$link='',$actor=''){
  if(!$to) return;
  try{ db()->prepare("INSERT INTO web_notifications(username,type,title,body,link,actor,created) VALUES(?,?,?,?,?,?,?)")
        ->execute([$to,$type,mb_substr_safe($title,0,190),mb_substr_safe($body,0,255),$link,$actor,ms()]); }catch(Exception $e){}
}
/* Danh sách tên tất cả admin (gồm owner) */
function dgl_admin_names(){
  global $CFG; $names=[]; if(!empty($CFG['owner'])) $names[strtolower($CFG['owner'])]=$CFG['owner'];
  try{ foreach(db()->query("SELECT username FROM web_admins")->fetchAll(PDO::FETCH_COLUMN) as $a) $names[strtolower($a)]=$a; }catch(Exception $e){}
  foreach(($CFG['admins']??[]) as $a) $names[strtolower($a)]=$a;
  return array_values($names);
}
/* Thông báo cho mọi admin (trừ $except) */
function notify_admins($type,$title,$body='',$link='',$actor='',$except=''){
  foreach(dgl_admin_names() as $a){ if($except!=='' && strtolower($a)===strtolower($except)) continue; notify($a,$type,$title,$body,$link,$actor); }
}
