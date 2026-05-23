<?php
/* ============================================================================
   HELPERS — utility/permission/csrf/flash/redirect/rcon/discord/announce
   ========================================================================== */

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
/* Truy cập từ localhost? Dùng để chặn dữ liệu DEMO (mật khẩu mặc định) rò ra prod
   ngay cả khi 'dev_mode' bị bật nhầm. */
function dgl_is_local(){ return in_array(($_SERVER['REMOTE_ADDR']??''), ['127.0.0.1','::1','localhost'], true); }
function csrf_token(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_ok(){ return isset($_POST['csrf'],$_SESSION['csrf']) && hash_equals($_SESSION['csrf'],$_POST['csrf']); }
function flash($m=null){ if($m!==null){ $_SESSION['flash'][]=$m; return; } $f=$_SESSION['flash']??[]; $_SESSION['flash']=[]; return $f; }
function redirect($p){ header('Location: ?p='.$p); exit; }
function ms(){ return (int) round(microtime(true)*1000); }
function is_owner($u){ global $CFG; return $u && strtolower($u)===strtolower($CFG['owner']??''); }
function is_admin($u){
  global $CFG; if(!$u) return false;
  if(is_owner($u)) return true;
  if(in_array(strtolower($u),array_map('strtolower',$CFG['admins']??[]),true)) return true;
  try{ $st=db()->prepare("SELECT 1 FROM web_admins WHERE LOWER(username)=? LIMIT 1"); $st->execute([strtolower($u)]); return (bool)$st->fetch(); }catch(Exception $e){ return false; }
}
function verify_row($u){
  $blank=['username'=>$u,'phone'=>'','phone_verified'=>0,'phone_code'=>'','discord_id'=>'','discord_name'=>'','discord_verified'=>0];
  try{ $st=db()->prepare("SELECT * FROM web_verify WHERE username=?"); $st->execute([$u]); $r=$st->fetch();
    if(!$r){ db()->prepare("INSERT INTO web_verify(username) VALUES(?)")->execute([$u]); return $blank; } return $r;
  }catch(Exception $e){ return $blank; }
}
function open_tickets(){ try{ return (int)db()->query("SELECT COUNT(*) FROM web_tickets WHERE status<>'closed'")->fetchColumn(); }catch(Exception $e){ return 0; } }
function ticket_code($id){ global $CFG; return ($CFG['ticket_prefix']??'DGL').'-'.str_pad((string)$id,6,'0',STR_PAD_LEFT); }
function admin_log($admin,$action,$detail=''){ try{ db()->prepare("INSERT INTO web_admin_log(admin,action,detail,created) VALUES(?,?,?,?)")->execute([$admin,$action,mb_substr_safe($detail,0,250),ms()]); }catch(Exception $e){} }
/* Sanitize 1 token (tên/rank/suffix) trước khi nối vào lệnh RCON. Chỉ cho phép
   ký tự an toàn — bất kỳ thứ gì khác sẽ bị loại để chặn injection (\n, ;, ", `). */
function rcon_arg($s,$max=32){ $s=preg_replace('/[^A-Za-z0-9_]/','', (string)$s); return mb_substr_safe($s,0,$max); }
/* Sanitize đoạn văn bản tự do (reason/kick message): loại CR/LF/null/dấu " để
   không thoát khỏi tham số lệnh. */
function rcon_text($s,$max=120){ $s=preg_replace('/[\r\n\x00"`;]/',' ',(string)$s); return mb_substr_safe(trim($s),0,$max); }
/* Queue 1 lệnh RCON. Defense in depth: ngay cả khi caller quên sanitize, hàm này
   vẫn loại CR/LF/null & cắt 250 ký tự (khớp với cột VARCHAR(255)). */
function rcon_queue($command,$by){
  $command=preg_replace('/[\r\n\x00]+/',' ',(string)$command);
  $command=mb_substr_safe(trim($command),0,250);
  if($command==='') return;
  try{ db()->prepare("INSERT INTO web_rcon_queue(command,requested_by,status,created) VALUES(?,?, 'pending',?)")->execute([$command,$by,ms()]); }catch(Exception $e){}
}
function mb_substr_safe($s,$a,$b){ return function_exists('mb_substr')?mb_substr($s,$a,$b,'UTF-8'):substr($s,$a,$b); }
function discord_notify($content){
  global $CFG; $url=$CFG['discord_webhook']??''; if(!$url) return false;
  $payload=json_encode(['content'=>$content,'username'=>'Dogeland Network'], JSON_UNESCAPED_UNICODE);
  if(function_exists('curl_init')){ $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>8]); curl_exec($ch); $ok=curl_errno($ch)===0; curl_close($ch); return $ok; }
  $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\n",'content'=>$payload,'timeout'=>8,'ignore_errors'=>true]]);
  return @file_get_contents($url,false,$ctx)!==false;
}
function active_announce(){
  try{ $now=ms(); $r=db()->query("SELECT * FROM web_announce WHERE active=1 AND (expires IS NULL OR expires>$now) ORDER BY id DESC LIMIT 1")->fetch(); return $r?:null; }catch(Exception $e){ return null; }
}


function db_err($e){ global $CFG; return !empty($CFG['dev_mode']) ? ('Lỗi CSDL (DEV): '.$e->getMessage()) : 'Lỗi kết nối CSDL. Kiểm tra cấu hình $CFG.'; }
