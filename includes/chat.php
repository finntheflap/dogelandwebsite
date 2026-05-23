<?php
/* ============================================================================
   CHAT — admin internal chat + stale ticket alerts
   ========================================================================== */

/* Chat nội bộ admin */
function chat_sys($msg){ try{ db()->prepare("INSERT INTO web_chat(username,message,kind,created) VALUES('SYSTEM',?,?,?)")->execute([$msg, 'system', ms()]); }catch(Exception $e){} }
function chat_send($u,$msg){ try{ db()->prepare("INSERT INTO web_chat(username,message,kind,created) VALUES(?,?, 'msg', ?)")->execute([$u,$msg,ms()]); return (int)db()->lastInsertId(); }catch(Exception $e){ return 0; } }
/* Quét ticket tồn đọng (>=2 ngày chưa đóng) → báo vào chat admin (giới hạn 5 phút/lần) */
function chat_check_stale_tickets(){
  $last=(int)dgl_setting('chat_stale_check',0); $now=ms();
  if($now-$last < 300000) return; dgl_set_setting('chat_stale_check',$now);
  try{
    $cut=$now-2*86400000;
    $rows=db()->query("SELECT id,code,subject,username,updated FROM web_tickets WHERE status<>'closed' AND chat_alert=0 AND updated<$cut ORDER BY updated ASC LIMIT 20")->fetchAll();
    foreach($rows as $t){
      $code=$t['code']?:ticket_code($t['id']);
      $days=floor(($now-(int)$t['updated'])/86400000);
      chat_sys('⏳ Ticket '.$code.' ("'.mb_substr_safe($t['subject'],0,60).'") của '.$t['username'].' đã '.$days.' ngày chưa được xử lý.');
      db()->prepare("UPDATE web_tickets SET chat_alert=1 WHERE id=?")->execute([$t['id']]);
      notify_admins('ticket','Ticket tồn đọng '.$code,$days.' ngày chưa xử lý','?p=ticket&id='.(int)$t['id'],'SYSTEM');
    }
  }catch(Exception $e){}
}
