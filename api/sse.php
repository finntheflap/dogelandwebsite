<?php
/* ============================================================================
   SSE — Server-Sent Events realtime stream
   ========================================================================== */

if($p==='sse'){
  if(!$user){ http_response_code(403); exit; }
  @set_time_limit(35); @ini_set('zlib.output_compression','0');
  header('Content-Type: text/event-stream; charset=utf-8');
  header('Cache-Control: no-cache, no-transform');
  header('X-Accel-Buffering: no'); header('Connection: keep-alive');
  while(ob_get_level()>0) @ob_end_flush(); @ob_implicit_flush(true);
  $uname=$user; $isAdmin=$IS_ADMIN;
  session_write_close();
  $sinceN=(int)($_GET['n']??0); $sinceC=(int)($_GET['c']??0);
  echo ": ok\n\n"; @flush();
  $start=time();
  while(time()-$start < 25){
    try{
      $st=db()->prepare("SELECT id,type,title,body,link,actor,created FROM web_notifications WHERE username=? AND id>? ORDER BY id ASC LIMIT 25");
      $st->execute([$uname,$sinceN]); $rows=$st->fetchAll();
      foreach($rows as $r){ $sinceN=max($sinceN,(int)$r['id']); echo "event: notif\ndata: ".json_encode($r,JSON_UNESCAPED_UNICODE)."\n\n"; }
      $uc=db()->prepare("SELECT COUNT(*) FROM web_notifications WHERE username=? AND is_read=0"); $uc->execute([$uname]);
      echo "event: count\ndata: ".json_encode(['unread'=>(int)$uc->fetchColumn(),'n'=>$sinceN])."\n\n";
      if($isAdmin){
        chat_check_stale_tickets();
        $cs=db()->prepare("SELECT id,username,message,kind,created FROM web_chat WHERE id>? ORDER BY id ASC LIMIT 40"); $cs->execute([$sinceC]); $crows=$cs->fetchAll();
        foreach($crows as $r){ $sinceC=max($sinceC,(int)$r['id']); echo "event: chat\ndata: ".json_encode($r,JSON_UNESCAPED_UNICODE)."\n\n"; }
        echo "event: chatcur\ndata: ".json_encode(['c'=>$sinceC])."\n\n";
      }
    }catch(Exception $e){ echo "event: err\ndata: {}\n\n"; }
    @flush();
    if(connection_aborted()) break;
    sleep(2);
  }
  echo "event: bye\ndata: {}\n\n"; @flush(); exit;
}
