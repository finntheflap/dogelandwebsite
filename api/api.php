<?php
/* ============================================================================
   API JSON — chuông thông báo + chat admin (AJAX)
   ========================================================================== */

if($p==='api'){
  $a=$_GET['a']??'';
  if(!$user) json_out(['ok'=>false,'err'=>'auth']);
  $skin=$CFG['skin_api'];
  if($a==='snapshot'){
    $unread=0; $items=[]; $chatMax=0;
    try{ $u=db()->prepare("SELECT COUNT(*) FROM web_notifications WHERE username=? AND is_read=0"); $u->execute([$user]); $unread=(int)$u->fetchColumn(); }catch(Exception $e){}
    try{ $st=db()->prepare("SELECT id,type,title,body,link,actor,is_read,created FROM web_notifications WHERE username=? ORDER BY id DESC LIMIT 25"); $st->execute([$user]); $items=$st->fetchAll(); }catch(Exception $e){}
    if($IS_ADMIN){ try{ $chatMax=(int)db()->query("SELECT COALESCE(MAX(id),0) FROM web_chat")->fetchColumn(); }catch(Exception $e){} }
    $maxN=0; foreach($items as $it) $maxN=max($maxN,(int)$it['id']);
    json_out(['ok'=>true,'unread'=>$unread,'items'=>$items,'n'=>$maxN,'c'=>$chatMax,'admin'=>$IS_ADMIN?1:0,'skin'=>$skin]);
  }
  if($a==='notif_read'){
    if(!ajax_csrf_ok()) json_out(['ok'=>false,'err'=>'csrf']);
    try{ db()->prepare("UPDATE web_notifications SET is_read=1 WHERE username=? AND is_read=0")->execute([$user]); }catch(Exception $e){}
    json_out(['ok'=>true,'unread'=>0]);
  }
  if($a==='notif_read_one'){
    if(!ajax_csrf_ok()) json_out(['ok'=>false,'err'=>'csrf']);
    $id=(int)($_POST['id']??0);
    try{ db()->prepare("UPDATE web_notifications SET is_read=1 WHERE id=? AND username=?")->execute([$id,$user]); }catch(Exception $e){}
    $unread=0; try{ $u=db()->prepare("SELECT COUNT(*) FROM web_notifications WHERE username=? AND is_read=0"); $u->execute([$user]); $unread=(int)$u->fetchColumn(); }catch(Exception $e){}
    json_out(['ok'=>true,'unread'=>$unread]);
  }
  if($a==='chat_list'){
    if(!$IS_ADMIN) json_out(['ok'=>false,'err'=>'forbidden']);
    chat_check_stale_tickets();
    $since=(int)($_GET['since']??0); $rows=[];
    try{ $st=db()->prepare("SELECT id,username,message,kind,created FROM web_chat WHERE id>? ORDER BY id ASC LIMIT 80"); $st->execute([$since]); $rows=$st->fetchAll(); }catch(Exception $e){}
    if(!$since && !$rows){ try{ $rows=array_reverse(db()->query("SELECT id,username,message,kind,created FROM web_chat ORDER BY id DESC LIMIT 60")->fetchAll()); }catch(Exception $e){} }
    $max=$since; foreach($rows as $r) $max=max($max,(int)$r['id']);
    json_out(['ok'=>true,'items'=>$rows,'c'=>$max,'skin'=>$skin]);
  }
  if($a==='chat_send'){
    if(!$IS_ADMIN) json_out(['ok'=>false,'err'=>'forbidden']);
    if(!ajax_csrf_ok()) json_out(['ok'=>false,'err'=>'csrf']);
    $m=trim($_POST['message']??''); if($m===''||mb_strlen($m,'UTF-8')>800) json_out(['ok'=>false,'err'=>'empty']);
    $id=chat_send($user,$m);
    json_out(['ok'=>$id>0,'id'=>$id]);
  }
  json_out(['ok'=>false,'err'=>'unknown']);
}
