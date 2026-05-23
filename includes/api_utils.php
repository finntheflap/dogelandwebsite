<?php
/* ============================================================================
   API UTILITIES — csrf for AJAX + JSON response + mention parsing
   ========================================================================== */

/* Kiểm tra CSRF cho request AJAX (token gửi qua POST hoặc header) */
function ajax_csrf_ok(){
  $t=$_POST['csrf']??($_SERVER['HTTP_X_CSRF']??'');
  return isset($_SESSION['csrf']) && $t!=='' && hash_equals($_SESSION['csrf'],$t);
}
function json_out($a){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
/* Tài khoản tồn tại trong AuthMe? (dùng cho @mention) */
function user_exists($name){
  global $CFG; if(!$name) return false;
  try{ $st=db()->prepare("SELECT realname FROM `".$CFG['authme_table']."` WHERE LOWER(username)=? LIMIT 1"); $st->execute([strtolower($name)]); $r=$st->fetch(); return $r?($r['realname']?:$name):false; }catch(Exception $e){ return false; }
}
/* Trích các @mention hợp lệ từ nội dung (trả về realname chuẩn) */
function parse_mentions($text){
  if(!preg_match_all('/@([A-Za-z0-9_]{3,16})/u',$text,$m)) return [];
  $out=[]; foreach(array_unique($m[1]) as $nm){ $rn=user_exists($nm); if($rn) $out[strtolower($rn)]=$rn; }
  return array_values($out);
}
