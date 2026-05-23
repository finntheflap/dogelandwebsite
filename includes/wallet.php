<?php
/* ============================================================================
   WALLET — web_wallet row + Dogecoin (PlayerPoints mirror)
   ========================================================================== */

function wallet($u){
  $blank=['username'=>$u,'xu'=>0,'diamonds'=>0,'verified'=>0,'logins'=>0,'last_login'=>0,'rank_name'=>'','suffix'=>'','banned'=>0,'ban_reason'=>''];
  try{ $pdo=db(); $st=$pdo->prepare("SELECT * FROM web_wallet WHERE username=?"); $st->execute([$u]); $w=$st->fetch();
    if(!$w){ $pdo->prepare("INSERT INTO web_wallet(username,created) VALUES(?,?)")->execute([$u,ms()]); return $blank; }
    return $w;
  }catch(Exception $e){ return $blank; }
}

/* ============================================================================
   DOGECOIN — lớp tiền tệ thống nhất
   ========================================================================== */

/* Đọc số dư Dogecoin */
function doge_balance($u){
  global $CFG;
  if(!empty($CFG['pp_enabled'])){
    try{ $key=pp_key($u);
      $st=db()->prepare("SELECT `{$CFG['pp_points_col']}` AS p FROM `{$CFG['pp_table']}` WHERE `{$CFG['pp_uuid_col']}`=? LIMIT 1");
      $st->execute([$key]); $r=$st->fetch(); return $r?(int)$r['p']:0;
    }catch(Exception $e){ /* rơi xuống mirror nếu lỗi */ }
  }
  try{ $st=db()->prepare("SELECT dogecoin FROM web_wallet WHERE username=? LIMIT 1"); $st->execute([$u]); $r=$st->fetch();
    if($r!==false) return (int)$r['dogecoin'];
    db()->prepare("INSERT INTO web_wallet(username,created) VALUES(?,?)")->execute([$u,ms()]); return 0;
  }catch(Exception $e){ return 0; }
}
/* Ghi đè số dư (dùng nội bộ) */
function doge_set($u,$amount){
  global $CFG; $amount=max(0,(int)$amount);
  if(!empty($CFG['pp_enabled'])){
    try{ $key=pp_key($u);
      db()->prepare("INSERT INTO `{$CFG['pp_table']}`(`{$CFG['pp_uuid_col']}`,`{$CFG['pp_points_col']}`) VALUES(?,?) ON DUPLICATE KEY UPDATE `{$CFG['pp_points_col']}`=VALUES(`{$CFG['pp_points_col']}`)")->execute([$key,$amount]);
    }catch(Exception $e){}
  }
  try{ db()->prepare("INSERT INTO web_wallet(username,dogecoin,created) VALUES(?,?,?) ON DUPLICATE KEY UPDATE dogecoin=VALUES(dogecoin)")->execute([$u,$amount,ms()]); }catch(Exception $e){}
}
/* Cộng Dogecoin — UPDATE delta atomic (không còn read-modify-write). */
function doge_add($u,$amt){
  global $CFG; $amt=(int)$amt; if($amt<=0) return true;
  if(!empty($CFG['pp_enabled'])){
    try{ $key=pp_key($u);
      db()->prepare("INSERT INTO `{$CFG['pp_table']}`(`{$CFG['pp_uuid_col']}`,`{$CFG['pp_points_col']}`) VALUES(?,?) ON DUPLICATE KEY UPDATE `{$CFG['pp_points_col']}`=`{$CFG['pp_points_col']}`+VALUES(`{$CFG['pp_points_col']}`)")->execute([$key,$amt]);
    }catch(Exception $e){}
  }
  try{ db()->prepare("INSERT INTO web_wallet(username,dogecoin,created) VALUES(?,?,?) ON DUPLICATE KEY UPDATE dogecoin=dogecoin+VALUES(dogecoin)")->execute([$u,$amt,ms()]); }catch(Exception $e){}
  return true;
}
/* Trừ Dogecoin — UPDATE có guard `dogecoin>=?`; chỉ thành công khi rowCount==1
   (ngăn over-draft khi 2 request chạy song song). $track=true cộng vào "đã tiêu" BXH. */
function doge_take($u,$amt,$track=true){
  global $CFG; $amt=(int)$amt; if($amt<=0) return true;
  $ok=false;
  if(!empty($CFG['pp_enabled'])){
    try{ $key=pp_key($u);
      $st=db()->prepare("UPDATE `{$CFG['pp_table']}` SET `{$CFG['pp_points_col']}`=`{$CFG['pp_points_col']}`-? WHERE `{$CFG['pp_uuid_col']}`=? AND `{$CFG['pp_points_col']}`>=?");
      $st->execute([$amt,$key,$amt]);
      $ok = $st->rowCount()===1;
    }catch(Exception $e){ return false; }
  } else {
    try{
      db()->prepare("INSERT IGNORE INTO web_wallet(username,created) VALUES(?,?)")->execute([$u,ms()]);
      $st=db()->prepare("UPDATE web_wallet SET dogecoin=dogecoin-? WHERE username=? AND dogecoin>=?");
      $st->execute([$amt,$u,$amt]);
      $ok = $st->rowCount()===1;
    }catch(Exception $e){ return false; }
  }
  if(!$ok) return false;
  if($track){ try{ db()->prepare("INSERT INTO web_wallet(username,doge_spent,created) VALUES(?,?,?) ON DUPLICATE KEY UPDATE doge_spent=doge_spent+VALUES(doge_spent)")->execute([$u,$amt,ms()]); }catch(Exception $e){} }
  return true;
}
/* Định dạng hiển thị Dogecoin */
function doge_fmt($n){ global $CFG; return number_format((int)$n,0,',','.').' '.($CFG['doge_label']??'Dogecoin'); }
function doge_short($n){ global $CFG; return ($CFG['doge_symbol']??'Ð').number_format((int)$n,0,',','.'); }
