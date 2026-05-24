<?php
/* ============================================================================
   AUTH CALLBACKS — email verify + logout + discord OAuth callback
   ========================================================================== */

/* GET verify */
if($p==='verify'){
  $token=preg_replace('/[^a-f0-9]/','',$_GET['token']??'');
  try{
    $pdo=db();
    $st=$pdo->prepare("SELECT * FROM web_pending WHERE token=? LIMIT 1"); $st->execute([$token]); $row=$st->fetch();
    if(!$row){ $verify_msg=['error','Link xác minh không hợp lệ hoặc đã được dùng.']; }
    elseif($row['expires']<ms()){ $pdo->prepare("DELETE FROM web_pending WHERE id=?")->execute([$row['id']]); $verify_msg=['error','Link xác minh đã hết hạn. Vui lòng đăng ký lại.']; }
    else{
      $t=$CFG['authme_table']; $now=ms();
      $pdo->prepare("INSERT INTO `$t`(username,realname,password,email,regdate,regip,ip,lastlogin) VALUES(?,?,?,?,?,?,?,0)")
          ->execute([$row['username'],$row['realname'],$row['password'],$row['email'],$now,$_SERVER['REMOTE_ADDR']??'','']);
      $pdo->prepare("DELETE FROM web_pending WHERE id=?")->execute([$row['id']]);
      try{ $pdo->prepare("INSERT INTO web_wallet(username,verified,created) VALUES(?,1,?) ON DUPLICATE KEY UPDATE verified=1")->execute([$row['realname'],ms()]); }catch(Exception $e){}
      $_SESSION['user']=$row['realname'];
      $verify_msg=['ok','Xác minh thành công! Tài khoản đã kích hoạt. Bạn có thể vào game với IP '.$CFG['server_ip'].'.'];
    }
  }catch(Exception $e){ $verify_msg=['error','Lỗi xử lý xác minh.']; }
}

if($p==='logout'){ remember_revoke(); $_SESSION=[]; flash(['ok','Đã đăng xuất. Hẹn gặp lại!']); redirect('home'); }
/* ?p=updates đã gộp vào ?p=events. Redirect để link cũ không vỡ. */
if($p==='updates'){ redirect('events'); }

/* GET: Discord OAuth callback */
if($p==='discord_cb' && $user && isset($_GET['code']) && $CFG['discord_client_id']){
  try{
    $redir=$CFG['site_url'].'/?p=discord_cb';
    $post=http_build_query(['client_id'=>$CFG['discord_client_id'],'client_secret'=>$CFG['discord_client_secret'],'grant_type'=>'authorization_code','code'=>$_GET['code'],'redirect_uri'=>$redir]);
    $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/x-www-form-urlencoded\r\n",'content'=>$post,'ignore_errors'=>true]]);
    $tok=json_decode(@file_get_contents('https://discord.com/api/oauth2/token',false,$ctx),true);
    if(!empty($tok['access_token'])){
      $uctx=stream_context_create(['http'=>['header'=>"Authorization: Bearer ".$tok['access_token']."\r\n",'ignore_errors'=>true]]);
      $du=json_decode(@file_get_contents('https://discord.com/api/users/@me',false,$uctx),true);
      if(!empty($du['id'])){ verify_row($user); $name=$du['username'].(isset($du['discriminator'])&&$du['discriminator']!=='0'?'#'.$du['discriminator']:''); db()->prepare("UPDATE web_verify SET discord_id=?, discord_name=?, discord_verified=1 WHERE username=?")->execute([$du['id'],$name,$user]); flash(['ok','Đã liên kết Discord: '.$name]); }
    } else flash(['error','Liên kết Discord thất bại.']);
  }catch(Exception $e){ flash(['error','Lỗi liên kết Discord.']); }
  redirect('profile');
}
