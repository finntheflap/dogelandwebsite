<?php
/* ============================================================================
   POST HANDLERS — dispatched theo $_POST['act']
   Đây là thân của block POST trong index.php gốc (lines 951-1684).
   Router đã: kiểm tra csrf, set $act = $_POST['act'] trước khi require file này.
   ========================================================================== */

  if($act==='register'){
    $u=trim($_POST['username']??''); $em=trim($_POST['email']??'');
    $pw=$_POST['password']??''; $pw2=$_POST['password2']??'';
    if(!preg_match('/^[A-Za-z0-9_]{3,16}$/',$u)) flash(['error','Tên tài khoản 3-16 ký tự, chỉ chữ/số/_.']);
    elseif(!filter_var($em,FILTER_VALIDATE_EMAIL)) flash(['error','Email không hợp lệ.']);
    elseif(strlen($pw)<6) flash(['error','Mật khẩu tối thiểu 6 ký tự.']);
    elseif($pw!==$pw2) flash(['error','Mật khẩu nhập lại không khớp.']);
    else{
      try{
        $pdo=db(); $t=$CFG['authme_table']; $lu=strtolower($u);
        $st=$pdo->prepare("SELECT 1 FROM `$t` WHERE LOWER(username)=? OR LOWER(email)=? LIMIT 1");
        $st->execute([$lu,strtolower($em)]);
        if($st->fetch()){ flash(['error','Tên hoặc email đã tồn tại trong game.']); }
        else{
          $token=bin2hex(random_bytes(32)); $now=ms();
          $pdo->prepare("DELETE FROM web_pending WHERE username=? OR email=?")->execute([$lu,$em]);
          $pdo->prepare("INSERT INTO web_pending(username,realname,email,password,token,expires,created) VALUES(?,?,?,?,?,?,?)")
              ->execute([$lu,$u,$em,authme_make_hash($pw),$token,$now+86400000,$now]);
          $link=$CFG['site_url'].'/?p=verify&token='.$token;
          $body='<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;background:#16181c;color:#eee;border-radius:14px;overflow:hidden">'
              .'<div style="background:#f2b631;color:#3a2700;padding:18px;font-size:20px;font-weight:bold;text-align:center">DOGELAND NETWORK</div>'
              .'<div style="padding:26px;line-height:1.6">Chào <b>'.h($u).'</b>,<br><br>Bấm nút bên dưới để xác minh email và kích hoạt tài khoản:<br><br>'
              .'<a href="'.$link.'" style="display:inline-block;background:#57b65a;color:#fff;text-decoration:none;padding:13px 26px;border-radius:10px;font-weight:bold">Xác minh tài khoản</a>'
              .'<br><br><span style="color:#9a9da3;font-size:13px">Hoặc mở link: '.$link.'<br>Link hết hạn sau 24 giờ.</span></div></div>';
          $ok=false;
          if($CFG['dev_mode']){
            flash(['devlink',$link]);
            flash(['ok','Chế độ DEV đang bật: bấm link phía trên để xác minh (chưa gửi email). Khi deploy thật, đặt dev_mode = false.']);
          } else {
            $ok=send_mail($em,'Xác minh tài khoản Dogeland Network',$body);
            if($ok) flash(['ok','Đã gửi email xác minh tới '.$em.'. Vui lòng kiểm tra hộp thư (cả mục Spam).']);
            else    flash(['error','Không gửi được email. Kiểm tra cấu hình SMTP trong file.']);
          }
          redirect('login');
        }
      }catch(Exception $e){ flash(['error',db_err($e)]); }
    }
    redirect('register');
  }

  if($act==='login'){
    $u=trim($_POST['username']??''); $pw=$_POST['password']??'';
    $ip=$_SERVER['REMOTE_ADDR']??''; $ua=mb_substr_safe($_SERVER['HTTP_USER_AGENT']??'',0,255);
    try{
      $pdo=db(); $t=$CFG['authme_table'];
      $st=$pdo->prepare("SELECT realname,password FROM `$t` WHERE LOWER(username)=? LIMIT 1");
      $st->execute([strtolower($u)]); $row=$st->fetch();
      if($row && authme_verify($pw,$row['password'])){
        $name=$row['realname']?:$u;
        $bw=wallet($name);
        // Auto-unban nếu ban_until đã hết hạn
        if(!empty($bw['banned']) && (int)($bw['ban_until']??0)>0 && (int)$bw['ban_until']<ms()){
          try{ db()->prepare("UPDATE web_wallet SET banned=0, ban_reason='', banned_by='', banned_at=0, ban_until=0, ban_ip='' WHERE username=?")->execute([$name]); }catch(Exception $e){}
          $bw['banned']=0;
        }
        if(!empty($bw['banned'])){
          try{ db()->prepare("INSERT INTO web_login_log(username,type,ip,ua,success,created) VALUES(?,?,?,?,0,?)")->execute([$name,'web',$ip,$ua,ms()]); }catch(Exception $e){}
          $bUntil = (int)($bw['ban_until']??0);
          $untilTxt = $bUntil>0 ? ' (đến '.date('d/m/Y H:i',(int)($bUntil/1000)).')' : ' (vĩnh viễn)';
          flash(['error','Tài khoản đã bị khoá (ban)'.$untilTxt.'.'.(!empty($bw['ban_reason'])?' Lý do: '.$bw['ban_reason']:'').' Liên hệ admin để được hỗ trợ.']);
          redirect('login');
        }
        $_SESSION['user']=$name;
        try{ db()->prepare("INSERT INTO web_wallet(username,logins,last_login,created) VALUES(?,1,?,?) ON DUPLICATE KEY UPDATE logins=logins+1,last_login=VALUES(last_login)")->execute([$name,ms(),ms()]); }catch(Exception $e){}
        try{ db()->prepare("INSERT INTO web_login_log(username,type,ip,ua,success,created) VALUES(?,?,?,?,1,?)")->execute([$name,'web',$ip,$ua,ms()]); }catch(Exception $e){}
        if(!empty($_POST['remember'])) remember_issue($name);
        flash(['ok','Đăng nhập thành công. Chào '.$name.'!']);
        redirect('home');
      } else {
        try{ if($u!=='') db()->prepare("INSERT INTO web_login_log(username,type,ip,ua,success,created) VALUES(?,?,?,?,0,?)")->execute([$u,'web',$ip,$ua,ms()]); }catch(Exception $e){}
        flash(['error','Sai tên đăng nhập hoặc mật khẩu.']);
      }
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('login');
  }

  /* --- FORGOT PASSWORD: gửi mail reset link --- */
  if($act==='forgot_password'){
    $input = trim($_POST['identifier'] ?? '');
    if($input === ''){ flash(['error','Nhập email hoặc tên tài khoản.']); redirect('forgot'); }
    try{
      $ip = $_SERVER['REMOTE_ADDR'] ?? '';
      $rl = db()->prepare("SELECT COUNT(*) FROM web_password_reset WHERE request_ip=? AND created>?");
      $rl->execute([$ip, ms() - 3600000]);
      if((int)$rl->fetchColumn() >= 3){ flash(['error','Bạn đã yêu cầu reset quá nhiều lần. Thử lại sau 1 giờ.']); redirect('forgot'); }
    }catch(Exception $e){}
    $row = null;
    try{
      $t = $CFG['authme_table'];
      $st = db()->prepare("SELECT realname, username, email FROM `$t` WHERE LOWER(username)=? OR LOWER(email)=? LIMIT 1");
      $st->execute([strtolower($input), strtolower($input)]);
      $row = $st->fetch();
    }catch(Exception $e){}
    if($row && !empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)){
      try{
        $token = bin2hex(random_bytes(32));
        $now = ms();
        db()->prepare("UPDATE web_password_reset SET used=1 WHERE username=? AND used=0")->execute([$row['username']]);
        db()->prepare("INSERT INTO web_password_reset(username,email,token,expires,used,request_ip,created) VALUES(?,?,?,?,0,?,?)")
            ->execute([$row['username'], $row['email'], $token, $now + 3600000, $_SERVER['REMOTE_ADDR'] ?? '', $now]);
        $resetLink = $CFG['site_url'].'/?p=reset&token='.$token;
        $name = $row['realname'] ?: $row['username'];
        $body = "Chào ".h($name).",<br><br>"
              ."Bạn (hoặc ai đó) đã yêu cầu reset mật khẩu cho tài khoản Dogeland Network.<br><br>"
              ."Mở link sau để đặt mật khẩu mới:<br>"
              ."<a href=\"".h($resetLink)."\">".h($resetLink)."</a><br><br>"
              ."Link hết hạn sau 1 giờ và chỉ dùng được 1 lần.<br><br>"
              ."Nếu bạn KHÔNG yêu cầu reset, bỏ qua mail này — mật khẩu của bạn vẫn an toàn.<br><br>"
              ."— Dogeland Network";
        send_mail($row['email'], 'Reset mật khẩu Dogeland Network', $body);
      }catch(Exception $e){}
    }
    flash(['ok','Nếu email/username tồn tại, mail reset đã được gửi. Vui lòng kiểm tra hộp thư (cả Spam) trong 1-2 phút.']);
    redirect('login');
  }

  /* --- RESET PASSWORD: nhập password mới sau khi click link mail --- */
  if($act==='reset_password'){
    $token = preg_replace('/[^a-f0-9]/', '', $_POST['token'] ?? '');
    $pw  = $_POST['password']  ?? '';
    $pw2 = $_POST['password2'] ?? '';
    if($token === '' || strlen($token) !== 64){ flash(['error','Link reset không hợp lệ.']); redirect('login'); }
    if(strlen($pw) < 6){ flash(['error','Mật khẩu tối thiểu 6 ký tự.']); redirect('reset&token='.$token); }
    if($pw !== $pw2){ flash(['error','Mật khẩu nhập lại không khớp.']); redirect('reset&token='.$token); }
    try{
      $st = db()->prepare("SELECT * FROM web_password_reset WHERE token=? LIMIT 1");
      $st->execute([$token]); $row = $st->fetch();
      if(!$row){ flash(['error','Link không hợp lệ hoặc đã dùng.']); redirect('login'); }
      if($row['used']){ flash(['error','Link reset đã được dùng rồi.']); redirect('forgot'); }
      if($row['expires'] < ms()){ flash(['error','Link reset đã hết hạn.']); redirect('forgot'); }
      $t = $CFG['authme_table'];
      db()->prepare("UPDATE `$t` SET password=? WHERE LOWER(username)=?")->execute([authme_make_hash($pw), strtolower($row['username'])]);
      db()->prepare("UPDATE web_password_reset SET used=1 WHERE id=?")->execute([$row['id']]);
      flash(['ok','Đổi mật khẩu thành công! Đăng nhập với mật khẩu mới.']);
    }catch(Exception $e){ flash(['error','Lỗi xử lý reset.']); }
    redirect('login');
  }

  /* --- SERVER CONSOLE: gõ lệnh RCON lên server Minecraft qua queue --- */
  if($act==='rcon_exec'){
    $isAjax = !empty($_POST['ajax']) || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch');
    $reply = function($ok, $msg) use ($isAjax) {
      if ($isAjax) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>$ok,'msg'=>$msg],JSON_UNESCAPED_UNICODE); exit; }
      flash([$ok?'ok':'error', $msg]); redirect('admin&tab=console');
    };
    if(!can_console($user)) $reply(false, 'Bạn không có quyền console.');
    $sid = trim($_POST['server_id'] ?? '');
    $cmd = trim($_POST['command'] ?? '');
    $modes = $CFG['modes'] ?? [];
    // Accept server-id nếu có trong config HOẶC đang online (heartbeat < 30s)
    $validSid = isset($modes[$sid]);
    if (!$validSid && $sid !== '') {
      try {
        $st = db()->prepare("SELECT 1 FROM web_sync_heartbeat WHERE server_id=? AND last_beat > ? LIMIT 1");
        $st->execute([$sid, ms() - 30000]);
        $validSid = (bool)$st->fetchColumn();
      } catch(Exception $e){}
    }
    if(!$validSid) $reply(false, 'Server không hợp lệ hoặc offline.');
    if($cmd === '') $reply(false, 'Nhập lệnh cần gõ.');
    $cmd = preg_replace('/[\r\n\x00]+/',' ',$cmd);
    $cmd = mb_substr_safe(trim($cmd),0,240);
    if($cmd === '') $reply(false, 'Lệnh rỗng sau khi sanitize.');
    try{
      db()->prepare("INSERT INTO web_rcon_queue(command, server_id, requested_by, status, created) VALUES(?,?,?, 'pending', ?)")
          ->execute([$cmd, $sid, $user, ms()]);
      admin_log($user,'rcon_exec','['.$sid.'] '.mb_substr_safe($cmd,0,200));
      $reply(true, 'Đã gửi tới '.$modes[$sid].' — kết quả hiện sau ~1-2s.');
    }catch(Exception $e){ $reply(false, 'Lỗi gửi lệnh: '.$e->getMessage()); }
  }

  /* --- SERVER CONSOLE: cấp/thu quyền console — Supervisor only --- */
  if($act==='console_grant'){
    if(!is_supervisor($user)){ flash(['error','Chỉ Supervisor cấp được quyền console.']); redirect('home'); }
    $uname = trim($_POST['username'] ?? '');
    if($uname === ''){ flash(['error','Nhập username.']); redirect('admin&tab=console'); }
    try{
      $chk = db()->prepare("SELECT 1 FROM web_admins WHERE LOWER(username)=? LIMIT 1");
      $chk->execute([strtolower($uname)]);
      if(!$chk->fetch()){ flash(['error',$uname.' chưa phải admin. Cấp Admin trước rồi mới cấp Console.']); redirect('admin&tab=console'); }
      db()->prepare("UPDATE web_admins SET console=1 WHERE LOWER(username)=?")->execute([strtolower($uname)]);
      admin_log($user,'console_grant',$uname);
      flash(['ok','Đã cấp quyền console cho '.$uname.'.']);
    }catch(Exception $e){ flash(['error',$e->getMessage()]); }
    redirect('admin&tab=console');
  }
  if($act==='console_revoke'){
    if(!is_supervisor($user)){ flash(['error','Chỉ Supervisor thu được quyền console.']); redirect('home'); }
    $uname = trim($_POST['username'] ?? '');
    if($uname === '' || is_owner($uname)){ redirect('admin&tab=console'); }
    try{
      db()->prepare("UPDATE web_admins SET console=0 WHERE LOWER(username)=?")->execute([strtolower($uname)]);
      admin_log($user,'console_revoke',$uname);
      flash(['ok','Đã thu quyền console của '.$uname.'.']);
    }catch(Exception $e){ flash(['error',$e->getMessage()]); }
    redirect('admin&tab=console');
  }

  if($act==='topup'){
    if(!$user){ flash(['error','Vui lòng đăng nhập trước khi nạp.']); redirect('login'); }
    $amount=(int)($_POST['amount']??0);
    $method=in_array(($_POST['method']??''),['bank','qr','momo'],true)?$_POST['method']:'bank';
    $pkg=null; foreach($PACKAGES as $pk){ if((int)$pk['amount']===$amount){ $pkg=$pk; break; } }
    if(!$pkg){ flash(['error','Gói nạp không hợp lệ.']); redirect('topup'); }
    $dia=apply_promo_dia((int)$pkg['dia']);
    try{
      db()->prepare("INSERT INTO web_topups(username,package,amount,diamonds,method,status,created) VALUES(?,?,?,?,?, 'pending', ?)")
          ->execute([$user, number_format($amount,0,',','.').'đ', $amount, $dia, $method, ms()]);
      /* TODO: gọi API cổng thanh toán (Momo/QR/ATM) ở đây; khi thành công thì
         UPDATE status='success' và cộng Kim Cương vào game cho người chơi. */
      notify_admins('topup','Yêu cầu nạp mới',$user.' nạp '.number_format($amount,0,',','.').'đ qua '.strtoupper($method),'?p=admin&tab=topups',$user);
      flash(['ok','Đã ghi nhận giao dịch nạp '.number_format($amount,0,',','.').'đ — đang xử lý.']);
    }catch(Exception $e){ flash(['error','Lỗi lưu giao dịch.']); }
    redirect('topup');
  }

  if($act==='post_save'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $id=(int)($_POST['id']??0);
    $type=in_array(($_POST['type']??''),post_types_allowed(),true)?$_POST['type']:'news';
    $title=trim($_POST['title']??''); $content=trim($_POST['content']??'');
    $image=trim($_POST['image']??''); $pinned=!empty($_POST['pinned'])?1:0;
    $event_at = !empty($_POST['event_at']) ? strtotime($_POST['event_at'])*1000 : null;
    $server=trim($_POST['server']??''); $server=mb_substr_safe($server,0,48);
    if($title===''||$content===''){ flash(['error','Vui lòng nhập tiêu đề và nội dung.']); redirect('admin&tab=posts'); }
    try{
      if($id){ db()->prepare("UPDATE web_posts SET type=?,title=?,content=?,image=?,event_at=?,server=?,pinned=? WHERE id=?")->execute([$type,$title,$content,$image,$event_at,$server,$pinned,$id]); flash(['ok','Đã cập nhật bài viết.']); }
      else { db()->prepare("INSERT INTO web_posts(type,title,content,image,event_at,server,pinned,author,created) VALUES(?,?,?,?,?,?,?,?,?)")->execute([$type,$title,$content,$image,$event_at,$server,$pinned,$user,ms()]); admin_log($user,'post_create','['.$type.'] '.$title); flash(['ok','Đã đăng bài viết.']); }
    }catch(Exception $e){ flash(['error','Lỗi lưu bài viết.']); }
    redirect('admin&tab=posts');
  }
  if($act==='post_delete'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    try{ db()->prepare("DELETE FROM web_posts WHERE id=?")->execute([(int)($_POST['id']??0)]); admin_log($user,'post_delete','ID '.(int)($_POST['id']??0)); flash(['ok','Đã xoá bài viết.']); }catch(Exception $e){}
    redirect('admin');
  }

  /* --- Người dùng đổi mật khẩu của chính mình --- */
  if($act==='profile_pw'){
    if(!$user){ redirect('login'); }
    $cur=$_POST['current']??''; $new=$_POST['new']??''; $new2=$_POST['new2']??'';
    try{
      $t=$CFG['authme_table'];
      $st=db()->prepare("SELECT password FROM `$t` WHERE LOWER(username)=?"); $st->execute([strtolower($user)]); $r=$st->fetch();
      if(!$r || !authme_verify($cur,$r['password'])) flash(['error','Mật khẩu hiện tại không đúng.']);
      elseif(strlen($new)<6) flash(['error','Mật khẩu mới tối thiểu 6 ký tự.']);
      elseif($new!==$new2) flash(['error','Mật khẩu mới nhập lại không khớp.']);
      else{ db()->prepare("UPDATE `$t` SET password=? WHERE LOWER(username)=?")->execute([authme_make_hash($new),strtolower($user)]); flash(['ok','Đã đổi mật khẩu thành công.']); }
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('profile');
  }

  /* --- ADMIN: sửa thông tin người dùng (Xu, Kim cương, verified, email, mật khẩu) --- */
  if($act==='user_save'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $uname=trim($_POST['username']??''); $doge=(int)($_POST['dogecoin']??0);
    $ver=!empty($_POST['verified'])?1:0; $email=trim($_POST['email']??''); $newpw=$_POST['newpw']??'';
    $rank=trim($_POST['rank_name']??''); $suffix=trim($_POST['suffix']??'');
    try{
      $t=$CFG['authme_table']; $old=wallet($uname);
      db()->prepare("INSERT INTO web_wallet(username,verified,rank_name,suffix,created) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE verified=VALUES(verified),rank_name=VALUES(rank_name),suffix=VALUES(suffix)")->execute([$uname,$ver,$rank,$suffix,ms()]);
      doge_set($uname,$doge); // ghi cả PlayerPoints lẫn mirror
      if($email!=='') db()->prepare("UPDATE `$t` SET email=? WHERE LOWER(username)=?")->execute([$email,strtolower($uname)]);
      if($newpw!=='') db()->prepare("UPDATE `$t` SET password=? WHERE LOWER(username)=?")->execute([authme_make_hash($newpw),strtolower($uname)]);
      // Whitelist tham số trước khi nối vào lệnh RCON (xem rcon_arg/rcon_text).
      $rcUname=rcon_arg($uname,32); $rcRank=rcon_arg($rank,32); $rcSuffix=rcon_text($suffix,24);
      if($rcUname!=='' && $rank!==($old['rank_name']??'') && $rcRank!=='') rcon_queue('lp user '.$rcUname.' parent set '.$rcRank, $user);
      if($rcUname!=='' && $suffix!==($old['suffix']??'')) rcon_queue('lp user '.$rcUname.' meta setsuffix "'.$rcSuffix.'"', $user);
      admin_log($user,'user_edit',$uname.' (doge='.$doge.($rank?', rank='.$rank:'').($suffix!==''?', suffix='.$suffix:'').')');
      flash(['ok','Đã cập nhật tài khoản '.$uname.'.'.(($rank!==($old['rank_name']??'')||$suffix!==($old['suffix']??''))?' Lệnh rank/suffix đã đưa vào hàng đợi áp dụng in-game.':'')]);
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('admin&tab=users&euser='.urlencode($uname));
  }
  if($act==='user_delete'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $uname=trim($_POST['username']??'');
    try{ $t=$CFG['authme_table']; db()->prepare("DELETE FROM `$t` WHERE LOWER(username)=?")->execute([strtolower($uname)]); db()->prepare("DELETE FROM web_wallet WHERE username=?")->execute([$uname]); admin_log($user,'user_delete',$uname); flash(['ok','Đã xoá tài khoản '.$uname.'.']); }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('admin&tab=users');
  }
  /* --- ADMIN: ban / gỡ ban người chơi --- */
  if($act==='user_ban'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $uname=trim($_POST['username']??''); $reason=mb_substr_safe(trim($_POST['reason']??''),0,190);
    $days=(int)($_POST['days']??0); if($days<0) $days=0; if($days>3650) $days=3650; // 10 năm max
    $alsoIp = !empty($_POST['ban_ip']);
    if($uname===''){ redirect('admin&tab=users'); }
    if(is_owner($uname)){ flash(['error','Không thể ban Supervisor.']); redirect('admin&tab=users'); }
    if(strtolower($uname)===strtolower((string)$user)){ flash(['error','Không thể tự ban chính mình.']); redirect('admin&tab=users'); }
    try{
      wallet($uname);
      $banUntil = $days>0 ? (ms() + $days*86400000) : 0;
      // Lấy IP cuối cùng từ AuthMe để banip luôn
      $playerIp=''; try{
        $t=$CFG['authme_table'];
        $st=db()->prepare("SELECT ip FROM `$t` WHERE LOWER(username)=? LIMIT 1");
        $st->execute([strtolower($uname)]); $playerIp = (string)$st->fetchColumn();
      }catch(Exception $e){}
      $storeIp = ($alsoIp && $playerIp!=='' && filter_var($playerIp, FILTER_VALIDATE_IP)) ? $playerIp : '';
      db()->prepare("UPDATE web_wallet SET banned=1, ban_reason=?, banned_by=?, banned_at=?, ban_until=?, ban_ip=? WHERE username=?")
          ->execute([$reason,$user,ms(),$banUntil,$storeIp,$uname]);
      $rcUname=rcon_arg($uname,32); $rcReason=rcon_text($reason!==''?$reason:'Vi phạm nội quy',120);
      $nServers=0; $bannedIp=false;
      if($rcUname!==''){
        // AuthMe ban (username) — queue cho tất cả server
        $nServers = rcon_queue_all('authme ban '.$rcUname.' '.$rcReason, $user);
        if($storeIp!==''){
          rcon_queue_all('authme banip '.$storeIp.' '.$rcReason, $user);
          $bannedIp=true;
        }
      }
      $durTxt = $days>0 ? ' '.$days.' ngày' : ' vĩnh viễn';
      notify($uname,'info','Tài khoản bị khoá (ban)'.$durTxt,$reason!==''?('Lý do: '.$reason):'Liên hệ admin để biết thêm.','','SYSTEM');
      admin_log($user,'user_ban',$uname.$durTxt.($bannedIp?' [+banip '.$storeIp.']':'').($reason!==''?' — '.$reason:''));
      flash(['ok','Đã ban '.$uname.$durTxt.' qua AuthMe trên '.$nServers.' server'.($bannedIp?' + banip '.$storeIp:'').'.']);
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('admin&tab=users'.($_POST['back_edit']??false?'&euser='.urlencode($uname):''));
  }
  if($act==='user_unban'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $uname=trim($_POST['username']??''); if($uname===''){ redirect('admin&tab=users'); }
    try{
      // Lấy IP đã ban (nếu có) từ wallet — ưu tiên hơn là lấy IP hiện tại
      $storedIp=''; try{
        $w=db()->prepare("SELECT ban_ip FROM web_wallet WHERE username=? LIMIT 1");
        $w->execute([$uname]); $storedIp = (string)$w->fetchColumn();
      }catch(Exception $e){}
      db()->prepare("UPDATE web_wallet SET banned=0, ban_reason='', banned_by='', banned_at=0, ban_until=0, ban_ip='' WHERE username=?")->execute([$uname]);
      // Fallback: nếu không có IP stored, lấy từ authme
      $playerIp = $storedIp;
      if($playerIp===''){
        try{
          $t=$CFG['authme_table'];
          $st=db()->prepare("SELECT ip FROM `$t` WHERE LOWER(username)=? LIMIT 1");
          $st->execute([strtolower($uname)]); $playerIp = (string)$st->fetchColumn();
        }catch(Exception $e){}
      }
      $rcUname=rcon_arg($uname,32); $nServers=0; $unbannedIp=false;
      if($rcUname!==''){
        $nServers = rcon_queue_all('authme unban '.$rcUname, $user);
        if($playerIp!=='' && filter_var($playerIp, FILTER_VALIDATE_IP)){
          rcon_queue_all('authme unbanip '.$playerIp, $user);
          $unbannedIp=true;
        }
      }
      notify($uname,'info','Tài khoản đã được mở khoá','Bạn có thể đăng nhập lại bình thường.','?p=login','SYSTEM');
      admin_log($user,'user_unban',$uname.($unbannedIp?' [+unbanip '.$playerIp.']':''));
      flash(['ok','Đã gỡ ban '.$uname.' trên '.$nServers.' server'.($unbannedIp?' + IP '.$playerIp:'').'.']);
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('admin&tab=users'.($_POST['back_edit']??false?'&euser='.urlencode($uname):''));
  }
  /* --- ADMIN: chỉnh sửa kho đồ của user --- */
  if($act==='inv_add'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $uname=trim($_POST['username']??''); $mode=trim($_POST['mode']??''); $item=trim($_POST['item']??'');
    $qty=max(1,(int)($_POST['qty']??1)); $color=trim($_POST['color']??'#888888');
    $ikey=ikey_norm($_POST['item_key']??'');
    $image=trim($_POST['image']??''); $up=item_upload('image_file'); if($up!=='') $image=$up; // ưu tiên file upload
    if($uname===''||$item===''){ flash(['error','Thiếu thông tin vật phẩm.']); redirect('admin&tab=users'); }
    try{ db()->prepare("INSERT INTO web_inventory(username,mode,item,item_key,qty,color,image) VALUES(?,?,?,?,?,?,?)")->execute([$uname,$mode,$item,$ikey,$qty,$color,$image]); admin_log($user,'inv_add',$uname.': +'.$qty.' '.$item.' ('.$mode.')'); flash(['ok','Đã thêm '.$item.' vào kho '.$uname.'.']); }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('admin&tab=users&euser='.urlencode($uname));
  }
  if($act==='inv_delete'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $id=(int)($_POST['id']??0); $uname=trim($_POST['username']??'');
    try{ db()->prepare("DELETE FROM web_inventory WHERE id=?")->execute([$id]); admin_log($user,'inv_delete',$uname.' #'.$id); flash(['ok','Đã xoá vật phẩm.']); }catch(Exception $e){}
    redirect('admin&tab=users&euser='.urlencode($uname));
  }

  /* --- ADMIN: duyệt / từ chối giao dịch nạp (duyệt = cộng Kim Cương) --- */
  if($act==='topup_set'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $id=(int)($_POST['id']??0); $status=in_array(($_POST['status']??''),['success','rejected','pending'],true)?$_POST['status']:'pending';
    try{
      $st=db()->prepare("SELECT * FROM web_topups WHERE id=?"); $st->execute([$id]); $tx=$st->fetch();
      if($tx){
        // Idempotent: chỉ cộng tiền cho LẦN gọi thực sự chuyển trạng thái sang
        // 'success'. Hai cú click admin song song sẽ chỉ có 1 lần rowCount==1.
        $upd=db()->prepare("UPDATE web_topups SET status=? WHERE id=? AND status<>?");
        $upd->execute([$status,$id,$status]);
        $changed = $upd->rowCount()===1;
        if($changed && $status==='success'){
          doge_add($tx['username'], (int)$tx['diamonds']);
          notify($tx['username'],'topup','Nạp thành công 🎉','+'.doge_fmt((int)$tx['diamonds']).' ('.h($tx['package']).') đã vào ví.','?p=profile',$user);
        } elseif($changed && $status==='rejected'){
          notify($tx['username'],'topup','Giao dịch bị từ chối','Giao dịch '.h($tx['package']).' đã bị từ chối. Liên hệ admin nếu cần hỗ trợ.','?p=topup',$user);
        }
        if($changed){
          admin_log($user,'topup_'.$status,'GD #'.$id.' của '.$tx['username']);
          flash(['ok','Đã cập nhật giao dịch #'.$id.' → '.$status]);
        } else {
          flash(['ok','Giao dịch #'.$id.' đã ở trạng thái '.$status.'.']);
        }
      }
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('admin&tab=topups');
  }

  /* --- ADMIN: cửa hàng (rank + vật phẩm) --- */
  if($act==='shop_save'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $id=(int)($_POST['id']??0); $cat=in_array(($_POST['category']??''),['rank','item'],true)?$_POST['category']:'item';
    $name=trim($_POST['name']??''); $price=(int)($_POST['price']??0); $color=trim($_POST['color']??'#888888'); $detail=trim($_POST['detail']??'');
    if($name===''){ flash(['error','Nhập tên sản phẩm.']); redirect('admin&tab=shop'); }
    try{
      if($id) db()->prepare("UPDATE web_shop SET category=?,name=?,price=?,color=?,detail=? WHERE id=?")->execute([$cat,$name,$price,$color,$detail,$id]);
      else db()->prepare("INSERT INTO web_shop(category,name,price,color,detail,sort) VALUES(?,?,?,?,?,?)")->execute([$cat,$name,$price,$color,$detail,99]);
      admin_log($user,'shop_save',$name); flash(['ok','Đã lưu sản phẩm.']);
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('admin&tab=shop');
  }
  if($act==='shop_delete'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    try{ db()->prepare("DELETE FROM web_shop WHERE id=?")->execute([(int)($_POST['id']??0)]); admin_log($user,'shop_delete','ID '.(int)($_POST['id']??0)); flash(['ok','Đã xoá sản phẩm.']); }catch(Exception $e){}
    redirect('admin&tab=shop');
  }

  /* ============================ ĐẤU GIÁ ============================ */
  /* Người chơi MỞ phiên đấu giá — LẤY VẬT PHẨM TỪ KHO, phí mở (hoàn nếu không ai đấu) */
  if($act==='auc_open'){
    if(!$user){ flash(['error','Vui lòng đăng nhập.']); redirect('login'); }
    $invId=(int)($_POST['inv_id']??0);
    $start=max(1,(int)($_POST['start_price']??1)); $hours=max(0.1,min(168,(float)($_POST['hours']??24)));
    $fee=(int)($CFG['auction_open_fee']??2);
    try{
      $st=db()->prepare("SELECT * FROM web_inventory WHERE id=? AND username=?"); $st->execute([$invId,$user]); $iv=$st->fetch();
      if(!$iv){ flash(['error','Vật phẩm không có trong kho của bạn.']); redirect('auction'); }
      $qty=max(1,min((int)$iv['qty'],(int)($_POST['qty']??1)));
      if(doge_balance($user) < $fee){ flash(['error','Cần '.doge_fmt($fee).' để mở phiên (số dư không đủ).']); redirect('auction'); }
      $ikey = $iv['item_key']!=='' ? $iv['item_key'] : ikey_norm($iv['item']);
      doge_take($user,$fee,false); // phí mở — KHÔNG tính vào "đã tiêu" vì có thể hoàn
      // rút khỏi kho (escrow)
      if($qty>=(int)$iv['qty']) db()->prepare("DELETE FROM web_inventory WHERE id=?")->execute([$invId]);
      else db()->prepare("UPDATE web_inventory SET qty=qty-? WHERE id=?")->execute([$qty,$invId]);
      $end=ms()+(int)round($hours*3600000);
      db()->prepare("INSERT INTO web_auctions(item,item_key,qty,image,from_inv,mode,seller,color,price,start_price,top_bidder,bid_count,listing_fee,status,end_at,created) VALUES(?,?,?,?,1,?,?,?,?,?,'',0,?, 'active',?,?)")
          ->execute([$iv['item'],$ikey,$qty,$iv['image']??'',$iv['mode'],$user,$iv['color']?:'#f2b631',$start,$start,$fee,$end,ms()]);
      flash(['ok','Đã mở phiên đấu giá '.$qty.'× "'.$iv['item'].'". Phí mở '.doge_fmt($fee).' sẽ hoàn (và vật phẩm trả về kho) nếu hết giờ không ai đấu.']);
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('auction');
  }
  /* Đặt giá — escrow: trừ tiền người đấu, hoàn tiền người đấu trước đó */
  if($act==='auc_bid'){
    if(!$user){ flash(['error','Vui lòng đăng nhập.']); redirect('login'); }
    $id=(int)($_POST['id']??0); $amt=(int)($_POST['amount']??0);
    try{
      auc_settle_due();
      $st=db()->prepare("SELECT * FROM web_auctions WHERE id=?"); $st->execute([$id]); $a=$st->fetch();
      if(!$a || $a['status']!=='active' || (int)$a['end_at']<=ms()){ flash(['error','Phiên đấu giá đã kết thúc.']); redirect('auction'); }
      if(strtolower($a['seller'])===strtolower($user)){ flash(['error','Không thể đấu giá phiên của chính mình.']); redirect('auction'); }
      $minBid = $a['top_bidder']!=='' ? (int)$a['price']+1 : (int)$a['price'];
      if($amt < $minBid){ flash(['error','Giá phải ≥ '.doge_fmt($minBid).'.']); redirect('auction'); }
      if(!doge_take($user,$amt,false)){ flash(['error','Số dư không đủ để đặt '.doge_fmt($amt).'.']); redirect('auction'); }
      if($a['top_bidder']!==''){ doge_add($a['top_bidder'],(int)$a['price']); // hoàn cho người bị vượt
        notify($a['top_bidder'],'info','Bạn đã bị trả giá cao hơn','Phiên "'.$a['item'].'" — đã hoàn '.doge_fmt((int)$a['price']).'.','?p=auction','SYSTEM'); }
      db()->prepare("UPDATE web_auctions SET price=?, top_bidder=?, bid_count=bid_count+1 WHERE id=?")->execute([$amt,$user,$id]);
      db()->prepare("INSERT INTO web_auction_bids(auction_id,bidder,amount,created) VALUES(?,?,?,?)")->execute([$id,$user,$amt,ms()]);
      flash(['ok','Đã đặt giá '.doge_fmt($amt).' cho "'.$a['item'].'".']);
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('auction');
  }
  /* Huỷ phiên (chủ phiên khi chưa có ai đấu, hoặc admin) — hoàn phí mở */
  if($act==='auc_cancel'){
    if(!$user){ redirect('login'); }
    $id=(int)($_POST['id']??0);
    try{
      $st=db()->prepare("SELECT * FROM web_auctions WHERE id=?"); $st->execute([$id]); $a=$st->fetch();
      if($a && $a['status']==='active'){
        $owner = strtolower($a['seller'])===strtolower($user);
        if(!$owner && !$IS_ADMIN){ flash(['error','Không có quyền.']); redirect('auction'); }
        if($a['top_bidder']!==''){ doge_add($a['top_bidder'],(int)$a['price']); } // hoàn người đang giữ giá
        doge_add($a['seller'],(int)$a['listing_fee']);                            // hoàn phí mở
        if(!empty($a['from_inv'])){ // trả vật phẩm về kho người bán
          db()->prepare("INSERT INTO web_inventory(username,mode,item,item_key,qty,color,image) VALUES(?,?,?,?,?, '#f2b631', ?)")
              ->execute([$a['seller'],$a['mode']??'',$a['item'],$a['item_key'],max(1,(int)($a['qty']??1)),$a['image']??'']);
        }
        db()->prepare("UPDATE web_auctions SET status='cancelled' WHERE id=?")->execute([$id]);
        flash(['ok','Đã huỷ phiên đấu giá, hoàn cọc'.(!empty($a['from_inv'])?' & trả vật phẩm về kho':'').'.']);
      }
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect($IS_ADMIN && ($_POST['admin']??'')?'admin&tab=auc':'auction');
  }
  /* ADMIN: tạo/sửa/xoá phiên thủ công */
  if($act==='auc_save'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $id=(int)($_POST['id']??0); $item=trim($_POST['item']??''); $seller=trim($_POST['seller']??'Admin');
    $ikey=ikey_norm($_POST['item_key']??'diamond') ?: 'diamond';
    $color=trim($_POST['color']??'#f2b631'); $price=(int)($_POST['price']??0); $hours=(float)($_POST['hours']??24);
    if($item===''){ flash(['error','Nhập tên vật phẩm.']); redirect('admin&tab=auc'); }
    try{
      $end=ms()+(int)round($hours*3600000);
      if($id) db()->prepare("UPDATE web_auctions SET item=?,item_key=?,seller=?,color=?,price=?,start_price=?,end_at=? WHERE id=?")->execute([$item,$ikey,$seller,$color,$price,$price,$end,$id]);
      else db()->prepare("INSERT INTO web_auctions(item,item_key,seller,color,price,start_price,status,end_at,created) VALUES(?,?,?,?,?,?, 'active',?,?)")->execute([$item,$ikey,$seller,$color,$price,$price,$end,ms()]);
      admin_log($user,'auction_save',$item); flash(['ok','Đã lưu phiên đấu giá.']);
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('admin&tab=auc');
  }
  if($act==='auc_delete'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $id=(int)($_POST['id']??0);
    try{
      $st=db()->prepare("SELECT * FROM web_auctions WHERE id=?"); $st->execute([$id]); $a=$st->fetch();
      if($a){
        $refunded=false;
        // Nếu phiên còn active thì phải hoàn cọc người đang giữ giá + phí mở của
        // người bán + trả vật phẩm về kho. Trước đây DELETE thẳng → mất hết escrow.
        if($a['status']==='active'){
          if($a['top_bidder']!==''){
            doge_add($a['top_bidder'],(int)$a['price']);
            notify($a['top_bidder'],'info','Phiên đấu giá bị admin huỷ','"'.$a['item'].'" — đã hoàn '.doge_short((int)$a['price']).'.','?p=auction','SYSTEM');
          }
          doge_add($a['seller'],(int)$a['listing_fee']);
          if(!empty($a['from_inv'])){
            db()->prepare("INSERT INTO web_inventory(username,mode,item,item_key,qty,color,image) VALUES(?,?,?,?,?, '#f2b631', ?)")
                ->execute([$a['seller'],$a['mode']??'',$a['item'],$a['item_key'],max(1,(int)($a['qty']??1)),$a['image']??'']);
          }
          $refunded=true;
        }
        db()->prepare("DELETE FROM web_auctions WHERE id=?")->execute([$id]);
        admin_log($user,'auction_delete','#'.$id.' "'.$a['item'].'" refunded='.($refunded?'yes':'no'));
        flash(['ok','Đã xoá phiên đấu giá'.($refunded?' và hoàn cọc/phí/vật phẩm':'').'.']);
      }
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('admin&tab=auc');
  }

  /* ============================ MUA RANK ============================ */
  if($act==='rank_save'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $id=(int)($_POST['id']??0); $scope=in_array(($_POST['scope']??''),['all','sdo'],true)?$_POST['scope']:'all';
    $name=mb_substr_safe(trim($_POST['name']??''),0,80); $price=max(0,(int)($_POST['price']??0));
    $color=trim($_POST['color']??'#f2b631'); $desc=trim($_POST['description']??''); $cmds=trim($_POST['commands']??'');
    $sort=(int)($_POST['sort']??0); $active=!empty($_POST['active'])?1:0;
    if($name===''){ flash(['error','Nhập tên rank.']); redirect('admin&tab=ranks'); }
    try{
      if($id) db()->prepare("UPDATE web_ranks SET scope=?,name=?,price=?,color=?,description=?,commands=?,sort=?,active=? WHERE id=?")->execute([$scope,$name,$price,$color,$desc,$cmds,$sort,$active,$id]);
      else db()->prepare("INSERT INTO web_ranks(scope,name,price,color,description,commands,sort,active,created) VALUES(?,?,?,?,?,?,?,?,?)")->execute([$scope,$name,$price,$color,$desc,$cmds,$sort,$active,ms()]);
      admin_log($user,'rank_save',$scope.':'.$name); flash(['ok','Đã lưu rank "'.$name.'".']);
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('admin&tab=ranks');
  }
  if($act==='rank_delete'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    try{ db()->prepare("DELETE FROM web_ranks WHERE id=?")->execute([(int)($_POST['id']??0)]); admin_log($user,'rank_delete','#'.(int)($_POST['id']??0)); flash(['ok','Đã xoá rank.']); }catch(Exception $e){}
    redirect('admin&tab=ranks');
  }
  /* Người chơi MUA rank — trừ Dogecoin & chạy lệnh admin đã cấu hình */
  if($act==='rank_buy'){
    if(!$user){ flash(['error','Vui lòng đăng nhập.']); redirect('login'); }
    $id=(int)($_POST['id']??0);
    try{
      $st=db()->prepare("SELECT * FROM web_ranks WHERE id=? AND active=1"); $st->execute([$id]); $r=$st->fetch();
      if(!$r){ flash(['error','Rank không tồn tại.']); redirect('ranks'); }
      if(!doge_take($user,(int)$r['price'])){ flash(['error','Không đủ '.($CFG['doge_label']??'Dogecoin').' (cần '.doge_fmt((int)$r['price']).').']); redirect('ranks&scope='.$r['scope']); }
      else{
        db()->prepare("INSERT INTO web_rank_purchases(username,rank_id,rank_name,scope,price,created) VALUES(?,?,?,?,?,?)")->execute([$user,$id,$r['name'],$r['scope'],(int)$r['price'],ms()]);
        // Áp rank + màu lên hồ sơ ngay (đảm bảo có wallet row trước).
        wallet($user);
        db()->prepare("UPDATE web_wallet SET rank_name=?, rank_color=? WHERE username=?")->execute([$r['name'],(string)($r['color']??''),$user]);
        $uuid=pp_key($user);
        foreach(preg_split('/\r\n|\r|\n/',(string)$r['commands']) as $line){ $line=trim($line); if($line==='') continue;
          $cmd=str_replace(['{player}','{user}','{uuid}','{rank}'],[$user,$user,$uuid,$r['name']],$line);
          rcon_queue(mb_substr_safe($cmd,0,255),$user);
        }
        notify($user,'gift','Mua rank thành công 🎖️','Bạn đã mua '.$r['name'].' (-'.doge_fmt((int)$r['price']).'). Lệnh đang được áp dụng in-game.','?p=ranks','SYSTEM');
        flash(['ok','Mua rank thành công: '.$r['name'].' (-'.doge_fmt((int)$r['price']).'). Lệnh đã đưa vào hàng đợi áp dụng in-game.']);
      }
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('ranks&scope='.($r['scope']??'all'));
  }

  /* ============================ CHỢ TRỜI ============================ */
  /* Đăng bán — escrow vật phẩm từ kho web_inventory của người bán */
  if($act==='market_list'){
    if(!$user){ flash(['error','Vui lòng đăng nhập.']); redirect('login'); }
    $invId=(int)($_POST['inv_id']??0); $price=max(1,(int)($_POST['price']??0));
    $desc=mb_substr_safe(trim($_POST['description']??''),0,255);
    $ikey=ikey_norm($_POST['item_key']??'');
    try{
      $st=db()->prepare("SELECT * FROM web_inventory WHERE id=? AND username=?"); $st->execute([$invId,$user]); $iv=$st->fetch();
      if(!$iv){ flash(['error','Vật phẩm không có trong kho của bạn.']); redirect('market'); }
      $qty=max(1,min((int)$iv['qty'],(int)($_POST['qty']??1)));
      if($ikey==='') $ikey = $iv['item_key']!=='' ? $iv['item_key'] : ikey_norm($iv['item']);
      // rút khỏi kho (escrow)
      if($qty>=(int)$iv['qty']) db()->prepare("DELETE FROM web_inventory WHERE id=?")->execute([$invId]);
      else db()->prepare("UPDATE web_inventory SET qty=qty-? WHERE id=?")->execute([$qty,$invId]);
      db()->prepare("INSERT INTO web_market(seller,item_name,item_key,qty,price,image,description,mode,status,created) VALUES(?,?,?,?,?,?,?,?, 'active',?)")
          ->execute([$user,$iv['item'],$ikey,$qty,$price,$iv['image']??'',$desc,$iv['mode'],ms()]);
      flash(['ok','Đã đăng bán '.$qty.'× '.$iv['item'].' với giá '.doge_fmt($price).'.']);
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('market');
  }
  /* Mua trên chợ trời — trừ tiền người mua, trả người bán (đã trừ phí %), chuyển đồ */
  if($act==='market_buy'){
    if(!$user){ flash(['error','Vui lòng đăng nhập.']); redirect('login'); }
    $id=(int)($_POST['id']??0); $fp=max(0,(int)($CFG['market_fee_percent']??5));
    try{
      $st=db()->prepare("SELECT * FROM web_market WHERE id=? AND status='active'"); $st->execute([$id]); $m=$st->fetch();
      if(!$m){ flash(['error','Mặt hàng không còn bán.']); redirect('market'); }
      if(strtolower($m['seller'])===strtolower($user)){ flash(['error','Không thể mua hàng của chính mình.']); redirect('market'); }
      if(!doge_take($user,(int)$m['price'])){ flash(['error','Không đủ '.($CFG['doge_label']??'Dogecoin').' (cần '.doge_fmt((int)$m['price']).').']); redirect('market'); }
      else{
        $fee=(int)floor((int)$m['price']*$fp/100); $net=(int)$m['price']-$fee;
        doge_add($m['seller'],$net); // người bán nhận sau phí; phần phí server giữ
        db()->prepare("INSERT INTO web_inventory(username,mode,item,item_key,qty,color,image) VALUES(?,?,?,?,?, '#f2b631', ?)")->execute([$user,$m['mode'],$m['item_name'],$m['item_key'],(int)$m['qty'],$m['image']??'']);
        db()->prepare("UPDATE web_market SET status='sold', buyer=?, sold_at=? WHERE id=?")->execute([$user,ms(),$id]);
        notify($m['seller'],'gift','Đã bán hàng trên Chợ Trời 💰',$user.' mua '.$m['qty'].'× '.$m['item_name'].' — bạn nhận '.doge_fmt($net).' (phí '.$fp.'%: '.doge_fmt($fee).').','?p=market','SYSTEM');
        flash(['ok','Mua thành công '.$m['qty'].'× '.$m['item_name'].' (-'.doge_fmt((int)$m['price']).'). Đã vào kho của bạn.']);
      }
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('market');
  }
  /* Gỡ tin (người bán hoặc admin) — trả đồ về kho */
  if($act==='market_cancel'){
    if(!$user){ redirect('login'); }
    $id=(int)($_POST['id']??0);
    try{
      $st=db()->prepare("SELECT * FROM web_market WHERE id=?"); $st->execute([$id]); $m=$st->fetch();
      if($m && $m['status']==='active'){
        if(strtolower($m['seller'])!==strtolower($user) && !$IS_ADMIN){ flash(['error','Không có quyền.']); redirect('market'); }
        db()->prepare("INSERT INTO web_inventory(username,mode,item,item_key,qty,color,image) VALUES(?,?,?,?,?, '#f2b631', ?)")->execute([$m['seller'],$m['mode'],$m['item_name'],$m['item_key'],(int)$m['qty'],$m['image']??'']);
        db()->prepare("UPDATE web_market SET status='cancelled' WHERE id=?")->execute([$id]);
        flash(['ok','Đã gỡ tin & trả vật phẩm về kho.']);
      }
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect($IS_ADMIN && ($_POST['admin']??'')?'admin&tab=market':'market');
  }

  /* --- Bình luận bài viết (hỗ trợ trả lời + tag @user) --- */
  if($act==='comment_add'){
    if(!$user){ flash(['error','Đăng nhập để bình luận.']); redirect('login'); }
    $pid=(int)($_POST['post_id']??0); $parent=(int)($_POST['parent_id']??0); $c=trim($_POST['content']??'');
    if($c!==''){
      $c=mb_substr_safe($c,0,1000);
      // Reply phải thuộc cùng bài viết
      $parentRow=null;
      if($parent>0){ try{ $ps=db()->prepare("SELECT id,post_id,username FROM web_comments WHERE id=?"); $ps->execute([$parent]); $parentRow=$ps->fetch(); }catch(Exception $e){} if(!$parentRow || (int)$parentRow['post_id']!==$pid) $parent=0; }
      try{
        db()->prepare("INSERT INTO web_comments(post_id,parent_id,username,content,created) VALUES(?,?,?,?,?)")->execute([$pid,$parent?:null,$user,$c,ms()]);
        // tiêu đề bài để hiển thị trong thông báo
        $ptitle=''; try{ $pt=db()->prepare("SELECT title FROM web_posts WHERE id=?"); $pt->execute([$pid]); $ptitle=(string)$pt->fetchColumn(); }catch(Exception $e){}
        $link='?p=post&id='.$pid; $sent=[strtolower($user)=>1];
        // thông báo cho người được trả lời
        if($parentRow && !isset($sent[strtolower($parentRow['username'])])){
          notify($parentRow['username'],'reply',$user.' đã trả lời bình luận của bạn',mb_substr_safe($c,0,120),$link,$user);
          $sent[strtolower($parentRow['username'])]=1;
        }
        // thông báo cho mọi @mention
        foreach(parse_mentions($c) as $mn){ if(!isset($sent[strtolower($mn)])){ notify($mn,'tag',$user.' đã nhắc đến bạn',($ptitle?'Trong "'.$ptitle.'": ':'').mb_substr_safe($c,0,120),$link,$user); $sent[strtolower($mn)]=1; } }
        flash(['ok',$parent?'Đã gửi trả lời.':'Đã gửi bình luận.']);
      }catch(Exception $e){}
    }
    redirect('post&id='.$pid);
  }
  if($act==='comment_delete'){
    $cid=(int)($_POST['id']??0); $pid=(int)($_POST['post_id']??0);
    try{ $own=db()->prepare("SELECT username FROM web_comments WHERE id=?"); $own->execute([$cid]); $cr=$own->fetch();
      if($cr && ($IS_ADMIN || strtolower($cr['username'])===strtolower((string)$user))){ db()->prepare("DELETE FROM web_comments WHERE id=? OR parent_id=?")->execute([$cid,$cid]); } }catch(Exception $e){}
    redirect('post&id='.$pid);
  }

  /* --- Ticket: tạo / trả lời / cập nhật --- */
  if($act==='ticket_create'){
    if(!$user){ redirect('login'); }
    $sub=trim($_POST['subject']??''); $cat=trim($_POST['category']??'Khác'); $srv=trim($_POST['server']??''); $msg=trim($_POST['message']??'');
    $atts = tk_upload_files('attachments'); $attJson = $atts ? json_encode($atts, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : null;
    if($sub===''||$msg===''){ flash(['error','Nhập tiêu đề và nội dung.']); redirect('tickets'); }
    try{ $now=ms(); db()->prepare("INSERT INTO web_tickets(username,subject,category,server,status,created,updated) VALUES(?,?,?,?,'open',?,?)")->execute([$user,$sub,$cat,$srv,$now,$now]);
      $tid=db()->lastInsertId(); $code=ticket_code($tid); db()->prepare("UPDATE web_tickets SET code=? WHERE id=?")->execute([$code,$tid]);
      db()->prepare("INSERT INTO web_ticket_replies(ticket_id,username,message,attachments,is_staff,created) VALUES(?,?,?,?,0,?)")->execute([$tid,$user,$msg,$attJson,$now]);
      notify_admins('ticket','Ticket mới '.$code,$user.' · '.h($cat).': '.mb_substr_safe($sub,0,90),'?p=ticket&id='.$tid,$user);
      flash(['ok','Đã gửi ticket '.$code.'. Admin sẽ phản hồi sớm.']); redirect('ticket&id='.$tid);
    }catch(Exception $e){ flash(['error',db_err($e)]); redirect('tickets'); }
  }
  if($act==='ticket_reply'){
    if(!$user){ redirect('login'); }
    $tid=(int)($_POST['id']??0); $msg=trim($_POST['message']??'');
    $atts = tk_upload_files('attachments'); $attJson = $atts ? json_encode($atts, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : null;
    try{ $st=db()->prepare("SELECT * FROM web_tickets WHERE id=?"); $st->execute([$tid]); $tk=$st->fetch();
      if($tk && ($IS_ADMIN || strtolower($tk['username'])===strtolower($user)) && ($msg!=='' || $atts)){
        if($msg==='') $msg = '(đính kèm)';
        db()->prepare("INSERT INTO web_ticket_replies(ticket_id,username,message,attachments,is_staff,created) VALUES(?,?,?,?,?,?)")->execute([$tid,$user,$msg,$attJson,$IS_ADMIN?1:0,ms()]);
        db()->prepare("UPDATE web_tickets SET updated=?, chat_alert=0".($IS_ADMIN?", status=IF(status='open','in_progress',status), assignee=IFNULL(assignee,?)":"")." WHERE id=?")->execute($IS_ADMIN?[ms(),$user,$tid]:[ms(),$tid]);
        $code=$tk['code']?:ticket_code($tid); $link='?p=ticket&id='.$tid;
        if($IS_ADMIN){ admin_log($user,'ticket_reply','Trả lời ticket '.$code);
          notify($tk['username'],'ticket','Admin đã phản hồi ticket '.$code,mb_substr_safe($msg,0,120),$link,$user);
        } else {
          notify_admins('ticket','Phản hồi mới ở ticket '.$code,$user.': '.mb_substr_safe($msg,0,110),$link,$user, $user);
        }
      }
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('ticket&id='.$tid);
  }
  if($act==='ticket_set'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $tid=(int)($_POST['id']??0); $status=in_array(($_POST['status']??''),['open','in_progress','closed'],true)?$_POST['status']:'open';
    try{
      $st=db()->prepare("SELECT username,code,status FROM web_tickets WHERE id=?"); $st->execute([$tid]); $tk0=$st->fetch();
      db()->prepare("UPDATE web_tickets SET status=?, assignee=?, updated=?, chat_alert=0 WHERE id=?")->execute([$status,$user,ms(),$tid]);
      if($tk0){ $code=$tk0['code']?:ticket_code($tid);
        if($status==='closed' && $tk0['status']!=='closed'){
          chat_sys('✅ Ticket '.$code.' đã được giải quyết bởi '.$user.'.');
          notify($tk0['username'],'ticket','Ticket '.$code.' đã được giải quyết','Cảm ơn bạn đã liên hệ — ticket đã được xử lý xong.','?p=ticket&id='.$tid,$user);
        }
      }
      flash(['ok','Đã cập nhật ticket #'.$tid.'.']);
    }catch(Exception $e){}
    redirect('ticket&id='.$tid);
  }
  /* Admin chỉnh sửa: loại ticket, server, người xử lý */
  if($act==='ticket_admin_update'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $tid=(int)($_POST['id']??0);
    $newCat=trim($_POST['category']??''); $newSrv=trim($_POST['server']??''); $newAssignee=trim($_POST['assignee']??'');
    $allowedCats = $CFG['ticket_categories'] ?? []; $allowedSrvs = $CFG['ticket_servers'] ?? [];
    // Validate
    $sets = []; $vals = [];
    if($newCat !== '' && in_array($newCat, $allowedCats, true)){ $sets[] = 'category=?'; $vals[] = $newCat; }
    if($newSrv === '' || in_array($newSrv, $allowedSrvs, true)){ $sets[] = 'server=?'; $vals[] = $newSrv; }
    if($newAssignee !== ''){
      // Cho phép '__unassign__' để bỏ assign
      if($newAssignee === '__unassign__'){ $sets[] = 'assignee=NULL'; }
      else {
        // Phải là admin trong web_admins
        try{ $st=db()->prepare("SELECT 1 FROM web_admins WHERE LOWER(username)=? LIMIT 1"); $st->execute([strtolower($newAssignee)]); $ok=(bool)$st->fetch(); }catch(Exception $e){ $ok=false; }
        if($ok){ $sets[] = 'assignee=?'; $vals[] = $newAssignee; }
      }
    }
    if($sets){
      $sets[] = 'updated=?'; $vals[] = ms(); $vals[] = $tid;
      try{ db()->prepare("UPDATE web_tickets SET ".implode(',',$sets)." WHERE id=?")->execute($vals); flash(['ok','Đã cập nhật ticket #'.$tid.'.']); }catch(Exception $e){ flash(['error',db_err($e)]); }
    }
    redirect('ticket&id='.$tid);
  }

  /* --- Hồ sơ: đổi email --- */
  if($act==='profile_email'){
    if(!$user){ redirect('login'); }
    $em=trim($_POST['email']??'');
    if(!filter_var($em,FILTER_VALIDATE_EMAIL)){ flash(['error','Email không hợp lệ.']); redirect('profile'); }
    try{ db()->prepare("UPDATE `".$CFG['authme_table']."` SET email=? WHERE LOWER(username)=?")->execute([$em,strtolower($user)]); flash(['ok','Đã cập nhật email.']); }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('profile');
  }
  /* --- Xác thực SĐT (gửi mã; dev_mode hiện mã ngay) --- */
  if($act==='phone_start'){
    if(!$user){ redirect('login'); }
    $ph=preg_replace('/[^0-9+]/','',$_POST['phone']??'');
    if(strlen($ph)<9){ flash(['error','Số điện thoại không hợp lệ.']); redirect('profile'); }
    $code=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
    try{ verify_row($user); db()->prepare("UPDATE web_verify SET phone=?, phone_code=?, phone_verified=0 WHERE username=?")->execute([$ph,$code,$user]);
      /* TODO: gửi SMS chứa $code qua cổng SMS (eSMS/Twilio...). */
      if(!empty($CFG['dev_mode'])) flash(['ok','Mã xác thực (DEV): '.$code.' — nhập vào ô bên dưới.']);
      else flash(['ok','Đã gửi mã xác thực tới SĐT '.$ph.'.']);
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('profile');
  }
  if($act==='phone_verify'){
    if(!$user){ redirect('login'); }
    $code=preg_replace('/[^0-9]/','',$_POST['code']??'');
    try{ $r=verify_row($user);
      if($r['phone_code']!=='' && hash_equals($r['phone_code'],$code)){ db()->prepare("UPDATE web_verify SET phone_verified=1, phone_code='' WHERE username=?")->execute([$user]); flash(['ok','Xác thực SĐT thành công.']); }
      else flash(['error','Mã xác thực không đúng.']);
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('profile');
  }
  if($act==='discord_demo'){ // chỉ dùng khi chưa cấu hình OAuth (dev)
    if(!$user){ redirect('login'); }
    try{ verify_row($user); db()->prepare("UPDATE web_verify SET discord_name=?, discord_verified=1 WHERE username=?")->execute([$user.'#0001',$user]); flash(['ok','Đã liên kết Discord (demo).']); }catch(Exception $e){}
    redirect('profile');
  }

  /* --- SUPERVISOR: set role (support/admin/supervisor) + console flag cho 1 user --- */
  if($act==='role_set'){
    if(!is_supervisor($user)){ flash(['error','Chỉ Supervisor mới đổi được role.']); redirect('admin&tab=users'); }
    $uname=trim($_POST['username']??'');
    $role=trim($_POST['role']??'');
    $console=!empty($_POST['console'])?1:0;
    $allowedRoles=['','support','admin','supervisor'];
    if(!in_array($role,$allowedRoles,true)){ flash(['error','Role không hợp lệ.']); redirect('admin&tab=users&euser='.urlencode($uname)); }
    if(is_owner($uname)){ flash(['error','Không thể đổi role của Supervisor gốc (config).']); redirect('admin&tab=users&euser='.urlencode($uname)); }
    if($uname==='') redirect('admin&tab=users');
    try{
      if($role===''){
        db()->prepare("DELETE FROM web_admins WHERE LOWER(username)=?")->execute([strtolower($uname)]);
        admin_log($user,'role_revoke','Thu role của '.$uname);
        flash(['ok','Đã gỡ quyền admin của '.$uname.'.']);
      } else {
        db()->prepare("INSERT INTO web_admins(username,role,console,granted_by,created) VALUES(?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE role=VALUES(role), console=VALUES(console)")
            ->execute([$uname,$role,$console,$user,ms()]);
        admin_log($user,'role_set',$uname.' → '.$role.($console?' + console':''));
        flash(['ok','Đã đặt '.$uname.' = '.role_label($role).($console?' + Console':'').'.']);
      }
    }catch(Exception $e){ flash(['error',$e->getMessage()]); }
    redirect('admin&tab=users&euser='.urlencode($uname));
  }

  /* --- LEGACY: cấp / thu quyền admin (giữ tương thích) --- */
  if($act==='admin_grant'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $uname=trim($_POST['username']??'');
    if($uname!==''){
      if(is_owner($uname)){ flash(['error','Không thể thay đổi quyền của Supervisor.']); redirect('admin&tab=staff'); }
      try{ db()->prepare("INSERT INTO web_admins(username,granted_by,created) VALUES(?,?,?) ON DUPLICATE KEY UPDATE granted_by=VALUES(granted_by)")->execute([$uname,$user,ms()]); admin_log($user,'grant_admin','Cấp quyền cho '.$uname); flash(['ok','Đã cấp quyền admin cho '.$uname.'.']); }catch(Exception $e){ flash(['error',db_err($e)]); }
    }
    redirect('admin&tab=staff');
  }
  if($act==='admin_revoke'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $uname=strtolower(trim($_POST['username']??''));
    if(is_owner($uname)){ flash(['error','Không thể thu quyền của Supervisor.']); redirect('admin&tab=staff'); }
    if($uname===strtolower((string)$user) && !is_owner($user)){ flash(['error','Không thể tự thu quyền của chính mình.']); redirect('admin&tab=staff'); }
    try{ db()->prepare("DELETE FROM web_admins WHERE LOWER(username)=?")->execute([$uname]); admin_log($user,'revoke_admin','Thu quyền của '.$uname); flash(['ok','Đã thu quyền admin.']); }catch(Exception $e){}
    redirect('admin&tab=staff');
  }

  /* --- THÔNG BÁO KHẨN (banner toàn trang) --- */
  if($act==='announce_save'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $m=trim($_POST['message']??''); $lvl=in_array(($_POST['level']??''),['info','warn','danger'],true)?$_POST['level']:'warn';
    $hours=(float)($_POST['hours']??0);
    if($m===''){ flash(['error','Nhập nội dung thông báo.']); redirect('admin&tab=announce'); }
    try{
      $exp = $hours>0 ? ms()+(int)round($hours*3600000) : null;
      db()->prepare("UPDATE web_announce SET active=0 WHERE active=1")->execute(); // chỉ 1 thông báo hiện tại
      db()->prepare("INSERT INTO web_announce(message,level,author,active,expires,created) VALUES(?,?,?,1,?,?)")->execute([$m,$lvl,$user,$exp,ms()]);
      admin_log($user,'announce','['.$lvl.'] '.$m);
      $emoji=['info'=>'ℹ️','warn'=>'⚠️','danger'=>'🔴'][$lvl];
      discord_notify($emoji.' **THÔNG BÁO** từ '.$user.":\n> ".$m.($exp?"\n_(hết hạn sau ".$hours."h)_":''));
      // Auto /bc to ALL MC servers — chỉ nội dung, bold (&l), màu theo level
      $mcColor = ['info'=>'&b','warn'=>'&e','danger'=>'&c'][$lvl] ?? '&e';
      $bcCmd = 'bc '.$mcColor.'&l'.$m;
      // Sanitize: remove newlines, limit length
      $bcCmd = preg_replace('/[\r\n\x00]+/',' ',$bcCmd);
      $bcCmd = mb_substr_safe(trim($bcCmd),0,240);
      $bcServers = 0;
      foreach (($CFG['modes'] ?? []) as $sid => $sname) {
        try {
          db()->prepare("INSERT INTO web_rcon_queue(command, server_id, requested_by, status, created) VALUES(?,?,?, 'pending', ?)")
              ->execute([$bcCmd, $sid, $user, ms()]);
          $bcServers++;
        } catch (Exception $e) {}
      }
      flash(['ok','Đã đăng thông báo khẩn'.($CFG['discord_webhook']?' + gửi Discord':'').' + broadcast tới '.$bcServers.' server MC qua /bc.']);
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('admin&tab=announce');
  }
  if($act==='announce_off'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    try{ db()->prepare("UPDATE web_announce SET active=0 WHERE id=?")->execute([(int)($_POST['id']??0)]); admin_log($user,'announce_off','Tắt thông báo #'.(int)($_POST['id']??0)); flash(['ok','Đã tắt thông báo.']); }catch(Exception $e){}
    redirect('admin&tab=announce');
  }

  /* --- NẠP TỰ CHỌN (custom) — chỉ ATM / Momo / QR --- */
  if($act==='topup_custom'){
    if(!$user){ flash(['error','Vui lòng đăng nhập trước khi nạp.']); redirect('login'); }
    $amount=(int)preg_replace('/[^0-9]/','',$_POST['amount']??'0');
    $method=in_array(($_POST['method']??''),['bank','momo','qr'],true)?$_POST['method']:'momo';
    $min=(int)$CFG['custom_min']; $max=(int)$CFG['custom_max'];
    if($amount<$min||$amount>$max){ flash(['error','Số tiền phải từ '.number_format($min,0,',','.').'đ đến '.number_format($max,0,',','.').'đ.']); redirect('topup'); }
    $dia=apply_promo_dia((int)floor($amount/max(1,(int)$CFG['vnd_per_diamond'])));
    $mname=['bank'=>'ATM / Banking','momo'=>'Momo','qr'=>'QR Code Pay'][$method]??$method;
    try{
      db()->prepare("INSERT INTO web_topups(username,package,amount,diamonds,method,status,created) VALUES(?,?,?,?,?, 'pending', ?)")
          ->execute([$user, number_format($amount,0,',','.').'đ (tự chọn)', $amount, $dia, $method, ms()]);
      notify_admins('topup','Yêu cầu nạp mới',$user.' nạp '.number_format($amount,0,',','.').'đ qua '.$mname,'?p=admin&tab=topups',$user);
      flash(['ok','Đã tạo yêu cầu nạp '.number_format($amount,0,',','.').'đ (≈ '.doge_fmt($dia).') qua '.$mname.'. Admin sẽ duyệt sau khi nhận được thanh toán.']);
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('topup');
  }

  /* --- ADMIN: chỉnh giá nạp, tỉ giá Kim Cương & khuyến mãi --- */
  if($act==='price_save'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $rate=max(1,(int)($_POST['vnd_per_diamond']??100));
    $cmin=max(1000,(int)($_POST['custom_min']??1000)); $cmax=max($cmin,(int)($_POST['custom_max']??10000000));
    $promo=max(0,min(500,(int)($_POST['promo_percent']??0)));
    $puntil=trim($_POST['promo_until']??'');
    $until = ($promo>0 && $puntil!=='') ? (int)(strtotime($puntil)*1000) : 0;
    dgl_set_setting('vnd_per_diamond',$rate); dgl_set_setting('custom_min',$cmin); dgl_set_setting('custom_max',$cmax);
    dgl_set_setting('promo_percent',$promo);
    dgl_set_setting('promo_until', $until>0 ? $until : 0);
    admin_log($user,'price_save','tỉ giá='.$rate.', KM='.$promo.'%');
    flash(['ok','Đã lưu cấu hình giá & khuyến mãi.']);
    redirect('admin&tab=pricing');
  }
  if($act==='pkg_save'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $id=(int)($_POST['id']??0); $amount=max(1000,(int)($_POST['amount']??0)); $dia=max(0,(int)($_POST['dia']??0));
    $xu=max(0,(int)($_POST['xu']??0)); $bonus=mb_substr_safe(trim($_POST['bonus']??''),0,64); $hot=!empty($_POST['hot'])?1:0;
    try{
      if($id) db()->prepare("UPDATE web_packages SET amount=?,dia=?,xu=?,bonus=?,hot=? WHERE id=?")->execute([$amount,$dia,$xu,$bonus,$hot,$id]);
      else db()->prepare("INSERT INTO web_packages(amount,dia,xu,bonus,hot,sort) VALUES(?,?,?,?,?,?)")->execute([$amount,$dia,$xu,$bonus,$hot,99]);
      admin_log($user,'pkg_save',number_format($amount,0,',','.').'đ → '.$dia.' KC'); flash(['ok','Đã lưu gói nạp.']);
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect('admin&tab=pricing');
  }
  if($act==='pkg_delete'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    try{ db()->prepare("DELETE FROM web_packages WHERE id=?")->execute([(int)($_POST['id']??0)]); admin_log($user,'pkg_delete','#'.(int)($_POST['id']??0)); flash(['ok','Đã xoá gói nạp.']); }catch(Exception $e){}
    redirect('admin&tab=pricing');
  }

  /* --- ADMIN: Gift Code (tạo / bật-tắt / xoá) --- */
  if($act==='gift_create'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    $code=strtoupper(preg_replace('/[^A-Za-z0-9_-]/','',$_POST['code']??''));
    if($code===''){ $code='DGL'.strtoupper(bin2hex(random_bytes(3))); }
    $doge=max(0,(int)($_POST['doge']??0));
    $max=max(1,(int)($_POST['max_uses']??1)); $note=mb_substr_safe(trim($_POST['note']??''),0,120);
    $eat=trim($_POST['expire_at']??''); $exp=$eat!==''?(int)(strtotime($eat)*1000):null; if($exp!==null && $exp<=0) $exp=null;
    if($doge<=0){ flash(['error','Phần thưởng Dogecoin phải lớn hơn 0.']); redirect('admin&tab=gift'); }
    try{ db()->prepare("INSERT INTO web_giftcodes(code,doge,dia,xu,max_uses,note,expires,created_by,created) VALUES(?,?,0,0,?,?,?,?,?)")
          ->execute([$code,$doge,$max,$note,$exp,$user,ms()]);
      admin_log($user,'gift_create',$code.' ('.$doge.' Đ ×'.$max.')'); flash(['ok','Đã tạo gift code: '.$code]);
    }catch(Exception $e){ flash(['error','Code đã tồn tại hoặc lỗi lưu.']); }
    redirect('admin&tab=gift');
  }
  if($act==='gift_toggle'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    try{ db()->prepare("UPDATE web_giftcodes SET active=1-active WHERE id=?")->execute([(int)($_POST['id']??0)]); }catch(Exception $e){}
    redirect('admin&tab=gift');
  }
  if($act==='gift_delete'){
    if(!$IS_ADMIN){ flash(['error','Bạn không có quyền.']); redirect('home'); }
    try{ $id=(int)($_POST['id']??0); db()->prepare("DELETE FROM web_giftcodes WHERE id=?")->execute([$id]); db()->prepare("DELETE FROM web_giftcode_redeems WHERE code_id=?")->execute([$id]); admin_log($user,'gift_delete','#'.$id); flash(['ok','Đã xoá gift code.']); }catch(Exception $e){}
    redirect('admin&tab=gift');
  }

  /* --- USER: nhập gift code đổi quà --- */
  if($act==='gift_redeem'){
    $from=preg_replace('/[^a-z_]/','', $_POST['from']??'home') ?: 'home';
    if(!$user){ flash(['error','Vui lòng đăng nhập.']); redirect('login'); }
    $code=strtoupper(preg_replace('/[^A-Za-z0-9_-]/','',$_POST['code']??''));
    if($code===''){ flash(['error','Nhập gift code.']); redirect($from); }
    try{
      $st=db()->prepare("SELECT * FROM web_giftcodes WHERE code=? LIMIT 1"); $st->execute([$code]); $g=$st->fetch();
      if(!$g || !$g['active']) flash(['error','Gift code không tồn tại hoặc đã bị khoá.']);
      elseif($g['expires'] && $g['expires']<ms()) flash(['error','Gift code đã hết hạn.']);
      elseif($g['used']>=$g['max_uses']) flash(['error','Gift code đã hết lượt sử dụng.']);
      else{
        $chk=db()->prepare("SELECT 1 FROM web_giftcode_redeems WHERE code_id=? AND username=?"); $chk->execute([$g['id'],$user]);
        if($chk->fetch()) flash(['error','Bạn đã sử dụng gift code này rồi.']);
        else{
          $reward=(int)($g['doge'] ?? 0); if($reward<=0) $reward=(int)$g['dia']+(int)$g['xu'];
          db()->prepare("INSERT INTO web_giftcode_redeems(code_id,username,created) VALUES(?,?,?)")->execute([$g['id'],$user,ms()]);
          db()->prepare("UPDATE web_giftcodes SET used=used+1 WHERE id=?")->execute([$g['id']]);
          doge_add($user,$reward);
          notify($user,'gift','Đổi gift code thành công 🎁','+'.doge_fmt($reward),'?p=profile','SYSTEM');
          flash(['ok','Đổi gift code thành công: +'.doge_fmt($reward).'.']);
        }
      }
    }catch(Exception $e){ flash(['error',db_err($e)]); }
    redirect($from);
  }
