<?php
/* ============================================================================
   DATABASE — PDO lazy connection + auto-create schema
   ========================================================================== */

function db(){
  global $CFG; static $pdo=null;
  if($pdo) return $pdo;
  $pdo = new PDO(
    "mysql:host={$CFG['db_host']};dbname={$CFG['db_name']};charset=utf8mb4",
    $CFG['db_user'], $CFG['db_pass'],
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
  );
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_pending(
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL, realname VARCHAR(64) NOT NULL,
    email VARCHAR(190) NOT NULL, password VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL, expires BIGINT NOT NULL, created BIGINT NOT NULL,
    UNIQUE KEY uq_user(username), KEY k_token(token)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_topups(
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL, package VARCHAR(64) NOT NULL,
    amount INT NOT NULL, diamonds INT NOT NULL, method VARCHAR(32) NOT NULL,
    telco VARCHAR(16) NULL, serial VARCHAR(64) NULL, code VARCHAR(64) NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending', created BIGINT NOT NULL,
    KEY k_user(username)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  if(!empty($CFG['auto_create_authme'])){
    $t=$CFG['authme_table'];
    $pdo->exec("CREATE TABLE IF NOT EXISTS `$t`(
      id INT AUTO_INCREMENT PRIMARY KEY,
      username VARCHAR(255) NOT NULL UNIQUE,
      realname VARCHAR(255) NOT NULL DEFAULT 'Player',
      password VARCHAR(255) NOT NULL DEFAULT '',
      ip VARCHAR(40) NOT NULL DEFAULT '127.0.0.1',
      lastlogin BIGINT DEFAULT 0,
      x DOUBLE NOT NULL DEFAULT 0, y DOUBLE NOT NULL DEFAULT 0, z DOUBLE NOT NULL DEFAULT 0,
      world VARCHAR(255) NOT NULL DEFAULT 'world',
      regdate BIGINT NOT NULL DEFAULT 0,
      regip VARCHAR(40) NOT NULL DEFAULT '127.0.0.1',
      email VARCHAR(255) DEFAULT 'your@email.com',
      isLogged INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_posts(
    id INT AUTO_INCREMENT PRIMARY KEY, type VARCHAR(16) NOT NULL DEFAULT 'news',
    title VARCHAR(200) NOT NULL, content TEXT NOT NULL, image VARCHAR(400) NULL,
    event_at BIGINT NULL, author VARCHAR(64) NOT NULL DEFAULT 'Admin',
    pinned TINYINT NOT NULL DEFAULT 0, created BIGINT NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  /* Migrate: thêm cột server (tên server liên quan — chủ yếu cho bài Cập nhật). */
  try{ $pdo->exec("ALTER TABLE web_posts ADD COLUMN server VARCHAR(48) NOT NULL DEFAULT '' AFTER event_at"); }catch(Exception $e){}
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_auctions(
    id INT AUTO_INCREMENT PRIMARY KEY, item VARCHAR(120) NOT NULL, seller VARCHAR(64) NOT NULL,
    color VARCHAR(16) NOT NULL DEFAULT '#888888', price INT NOT NULL DEFAULT 0,
    end_at BIGINT NOT NULL, created BIGINT NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  if(!$pdo->query("SELECT COUNT(*) FROM web_auctions")->fetchColumn()){
    $now=ms();
    $seed=[['Kiếm Kim Cương (Sắc bén V)','Steve_VN','#56cfd6',1250,2*3600+14*60],
           ['Giáp Netherite Full Set','NhamThach','#5b4636',8900,5*3600],
           ['Trứng Rồng Con (Pet)','DogeKing','#e0584a',15000,3600+30*60],
           ['Táo Vàng Phép x16','MinerPro','#f2b631',3200,12*3600],
           ['Ngựa Trắng (Tốc độ cao)','Alex_Builder','#e8e2d2',2100,45*60],
           ['Đầu Doge (Trang trí)','Admin','#d9a441',990,6*3600]];
    $ins=$pdo->prepare("INSERT INTO web_auctions(item,seller,color,price,end_at,created) VALUES(?,?,?,?,?,?)");
    foreach($seed as $s) $ins->execute([$s[0],$s[1],$s[2],$s[3],$now+$s[4]*1000,$now]);
  }
  if(!$pdo->query("SELECT COUNT(*) FROM web_posts")->fetchColumn()){
    $now=ms();
    $ins=$pdo->prepare("INSERT INTO web_posts(type,title,content,image,event_at,pinned,author,created) VALUES(?,?,?,?,?,?,?,?)");
    $ins->execute(['event','Lễ Hội Mùa Hè Doge 2026',"Săn rương báu trên toàn bản đồ, đổi vật phẩm giới hạn và nhận pet Shiba Vàng độc quyền.\n\nThời gian: 20/05 - 30/05/2026. Tham gia bằng lệnh /event ngay trong game!",null,$now+8*86400000,1,'Admin',$now-3600000]);
    $ins->execute(['news','Khai trương Website chính thức',"Dogeland Network ra mắt website mới: đăng ký tài khoản, nạp thẻ và theo dõi sự kiện ngay tại đây.\n\nChúc các bạn chơi game vui vẻ!",null,null,0,'Admin',$now-7200000]);
    $ins->execute(['news','Lịch bảo trì định kỳ',"Server bảo trì 02:00 - 04:00 sáng thứ 3 hàng tuần để cập nhật phiên bản mới. Mong các bạn thông cảm.",null,null,0,'Admin',$now-86400000]);
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_wallet(
    username VARCHAR(64) PRIMARY KEY, xu INT NOT NULL DEFAULT 0, diamonds INT NOT NULL DEFAULT 0,
    verified TINYINT NOT NULL DEFAULT 0, logins INT NOT NULL DEFAULT 0, last_login BIGINT NOT NULL DEFAULT 0,
    rank_name VARCHAR(48) NOT NULL DEFAULT '', suffix VARCHAR(48) NOT NULL DEFAULT '',
    created BIGINT NOT NULL DEFAULT 0
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  try{ $pdo->exec("ALTER TABLE web_wallet ADD COLUMN rank_name VARCHAR(48) NOT NULL DEFAULT '' AFTER last_login"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_wallet ADD COLUMN suffix VARCHAR(48) NOT NULL DEFAULT '' AFTER rank_name"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_wallet ADD COLUMN rank_color VARCHAR(16) NOT NULL DEFAULT '' AFTER suffix"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_wallet ADD COLUMN banned TINYINT NOT NULL DEFAULT 0"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_wallet ADD COLUMN ban_reason VARCHAR(190) NOT NULL DEFAULT ''"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_wallet ADD COLUMN banned_by VARCHAR(64) NOT NULL DEFAULT ''"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_wallet ADD COLUMN banned_at BIGINT NOT NULL DEFAULT 0"); }catch(Exception $e){}
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_shop(
    id INT AUTO_INCREMENT PRIMARY KEY, category VARCHAR(8) NOT NULL DEFAULT 'item',
    name VARCHAR(120) NOT NULL, price INT NOT NULL DEFAULT 0, color VARCHAR(16) NOT NULL DEFAULT '#888888',
    detail TEXT NULL, sort INT NOT NULL DEFAULT 0
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_purchases(
    id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(64) NOT NULL, item VARCHAR(120) NOT NULL,
    price INT NOT NULL, created BIGINT NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  if(!$pdo->query("SELECT COUNT(*) FROM web_shop")->fetchColumn()){
    $ins=$pdo->prepare("INSERT INTO web_shop(category,name,price,color,detail,sort) VALUES(?,?,?,?,?,?)");
    $ins->execute(['rank','VIP',500,'#86d488',"Tiền tố [VIP] màu xanh\n/kit vip mỗi ngày\nTối đa 5 điểm /home\n+10% Xu khi farm",1]);
    $ins->execute(['rank','VIP+',1000,'#b39ce8',"Toàn bộ đặc quyền VIP\nTiền tố [VIP+] màu tím\n/fly trong khu nhà riêng\nPet độc quyền · +20% Xu",2]);
    $ins->execute(['rank','MVP',2000,'#f2b631',"Toàn bộ đặc quyền VIP+\nTiền tố [MVP] cầu vồng\nVào server full, không xếp hàng\nSkin & cánh độc quyền · +40% Xu",3]);
    $items=[['Vé /fly 7 ngày',250,'#56cfd6'],['Pet Shiba Vàng',800,'#d9a441'],['Bộ Giáp Netherite',1200,'#5b4636'],['Kiếm Phù Phép VIP',600,'#9aa0a6'],['Hộp Quà Ngẫu Nhiên',150,'#e0584a'],['Gói /sethome +5',300,'#67c96a'],['Cánh Thiên Thần (Skin)',950,'#b39ce8'],['Thú Cưỡi Rồng',1500,'#f2b631']];
    $i=10; foreach($items as $it) $ins->execute(['item',$it[0],$it[1],$it[2],null,$i++]);
  }
  // --- Tài khoản & ví DEMO (CHỈ khi dev_mode VÀ truy cập từ localhost).
  //     Production: đặt dev_mode=false + xoá. ---
  if(!empty($CFG['dev_mode']) && dgl_is_local()){
    try{
      $t=$CFG['authme_table'];
      $demos=[['DogeAdmin','admin123','admin@dogeland.vn',50000,9999,1,142,2000000],
              ['Player','123456','player@dogeland.vn',12000,500,1,87,500000],
              ['Steve_VN','123456','steve@dogeland.vn',8400,320,1,210,1200000],
              ['NhamThach','123456','nt@dogeland.vn',25600,1500,1,53,3500000],
              ['DogeKing','123456','dk@dogeland.vn',3100,80,0,38,150000],
              ['MinerPro','123456','mp@dogeland.vn',17800,640,1,176,800000],
              ['Alex_Builder','123456','ab@dogeland.vn',940,210,1,64,300000]];
      $chk=$pdo->prepare("SELECT 1 FROM `$t` WHERE LOWER(username)=? LIMIT 1");
      $insA=$pdo->prepare("INSERT INTO `$t`(username,realname,password,email,regdate,regip,ip,lastlogin) VALUES(?,?,?,?,?,?,?,?)");
      $insW=$pdo->prepare("INSERT INTO web_wallet(username,xu,diamonds,verified,logins,last_login,created) VALUES(?,?,?,?,?,?,?)");
      $insT=$pdo->prepare("INSERT INTO web_topups(username,package,amount,diamonds,method,status,created) VALUES(?,?,?,?,'bank','success',?)");
      $now=ms();
      foreach($demos as $d){
        $lu=strtolower($d[0]); $chk->execute([$lu]);
        if(!$chk->fetch()){
          $insA->execute([$lu,$d[0],authme_make_hash($d[1]),$d[2],$now,'127.0.0.1','127.0.0.1',$now-rand(0,86400000)]);
          $insW->execute([$d[0],$d[3],$d[4],$d[5],$d[6],$now-rand(0,3600000),$now]);
          if($d[7]>0) $insT->execute([$d[0],number_format($d[7],0,',','.').'đ',$d[7],$d[4],$now-rand(0,30*86400000)]);
        }
      }
    }catch(Exception $e){}
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_admins(username VARCHAR(64) PRIMARY KEY, granted_by VARCHAR(64), created BIGINT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_comments(id INT AUTO_INCREMENT PRIMARY KEY, post_id INT NOT NULL, username VARCHAR(64) NOT NULL, content TEXT NOT NULL, created BIGINT NOT NULL, KEY k_post(post_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_tickets(id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(64) NOT NULL, subject VARCHAR(180) NOT NULL, category VARCHAR(48) NOT NULL DEFAULT 'Khác', server VARCHAR(48) NOT NULL DEFAULT '', status VARCHAR(16) NOT NULL DEFAULT 'open', assignee VARCHAR(64) NULL, created BIGINT NOT NULL, updated BIGINT NOT NULL, KEY k_user(username)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_ticket_replies(id INT AUTO_INCREMENT PRIMARY KEY, ticket_id INT NOT NULL, username VARCHAR(64) NOT NULL, message TEXT NOT NULL, attachments TEXT NULL, is_staff TINYINT NOT NULL DEFAULT 0, created BIGINT NOT NULL, KEY k_t(ticket_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  /* Migrate: thêm cột nếu DB cũ chưa có */
  try{ $pdo->exec("ALTER TABLE web_tickets ADD COLUMN server VARCHAR(48) NOT NULL DEFAULT '' AFTER category"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_tickets MODIFY COLUMN category VARCHAR(48) NOT NULL DEFAULT 'Khác'"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_ticket_replies ADD COLUMN attachments TEXT NULL AFTER message"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_tickets ADD COLUMN code VARCHAR(24) NULL AFTER id"); }catch(Exception $e){}
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_announce(id INT AUTO_INCREMENT PRIMARY KEY, message VARCHAR(255) NOT NULL, level VARCHAR(12) NOT NULL DEFAULT 'warn', author VARCHAR(64) NOT NULL, active TINYINT NOT NULL DEFAULT 1, expires BIGINT NULL, created BIGINT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_admin_log(id INT AUTO_INCREMENT PRIMARY KEY, admin VARCHAR(64) NOT NULL, action VARCHAR(48) NOT NULL, detail VARCHAR(255) NOT NULL DEFAULT '', created BIGINT NOT NULL, KEY k_admin(admin)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_rcon_queue(id INT AUTO_INCREMENT PRIMARY KEY, command VARCHAR(255) NOT NULL, requested_by VARCHAR(64) NOT NULL, status VARCHAR(12) NOT NULL DEFAULT 'pending', created BIGINT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  try{ foreach($pdo->query("SELECT id FROM web_tickets WHERE code IS NULL OR code=''")->fetchAll(PDO::FETCH_COLUMN) as $tid0){ $pdo->prepare("UPDATE web_tickets SET code=? WHERE id=?")->execute([($CFG['ticket_prefix']??'DGL').'-'.str_pad((string)$tid0,6,'0',STR_PAD_LEFT),$tid0]); } }catch(Exception $e){}
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_inventory(id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(64) NOT NULL, mode VARCHAR(24) NOT NULL, item VARCHAR(80) NOT NULL, qty INT NOT NULL DEFAULT 1, color VARCHAR(16) NOT NULL DEFAULT '#888888', KEY k_um(username,mode)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_verify(username VARCHAR(64) PRIMARY KEY, phone VARCHAR(20) NOT NULL DEFAULT '', phone_verified TINYINT NOT NULL DEFAULT 0, phone_code VARCHAR(8) NOT NULL DEFAULT '', discord_id VARCHAR(40) NOT NULL DEFAULT '', discord_name VARCHAR(80) NOT NULL DEFAULT '', discord_verified TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  if(!empty($CFG['dev_mode']) && dgl_is_local()){
    try{
      $t=$CFG['authme_table']; $now=ms();
      $chk=$pdo->prepare("SELECT 1 FROM `$t` WHERE LOWER(username)=? LIMIT 1");
      $chk->execute([strtolower($CFG['owner'])]);
      if(!$chk->fetch()){
        $pdo->prepare("INSERT INTO `$t`(username,realname,password,email,regdate,regip,ip,lastlogin) VALUES(?,?,?,?,?,?,?,?)")->execute([strtolower($CFG['owner']),$CFG['owner'],authme_make_hash('owner123'),'owner@dogeland.vn',$now,'127.0.0.1','127.0.0.1',$now]);
        $pdo->prepare("INSERT INTO web_wallet(username,xu,diamonds,verified,logins,last_login,created) VALUES(?,?,?,1,99,?,?)")->execute([$CFG['owner'],99999,99999,$now,$now]);
      }
      if(!$pdo->query("SELECT COUNT(*) FROM web_admins")->fetchColumn())
        $pdo->prepare("INSERT INTO web_admins(username,granted_by,created) VALUES('DogeAdmin',?,?)")->execute([$CFG['owner'],$now]);
      if(!$pdo->query("SELECT COUNT(*) FROM web_inventory")->fetchColumn()){
        $inv=$pdo->prepare("INSERT INTO web_inventory(username,mode,item,qty,color) VALUES(?,?,?,?,?)");
        $seed=[['Player','towny','Kim Cương',64,'#56cfd6'],['Player','towny','Gỗ Sồi',128,'#a07840'],['Player','towny','Táo Vàng',8,'#f2b631'],['Player','towny','Sắt Thỏi',32,'#cfd2d6'],
               ['Player','sdo','Kiếm Ánh Sáng',1,'#9fd2ff'],['Player','sdo','Tinh Thể SAO',12,'#b39ce8'],['Player','sdo','Giáp Hiệp Sĩ',1,'#e8e2d2'],
               ['Player','survival','Cá Hồi',16,'#e0584a'],['Player','survival','Đuốc',64,'#f2b631']];
        foreach($seed as $s) $inv->execute($s);
      }
      if(!$pdo->query("SELECT COUNT(*) FROM web_tickets")->fetchColumn()){
        $pdo->prepare("INSERT INTO web_tickets(username,subject,category,status,assignee,created,updated) VALUES('Player','Mất đồ sau khi server restart','Báo lỗi','open',NULL,?,?)")->execute([$now-7200000,$now-7200000]);
        $tid=$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO web_ticket_replies(ticket_id,username,message,is_staff,created) VALUES(?,?,?,0,?)")->execute([$tid,'Player','Em bị mất full giáp netherite sau khi server bảo trì, nhờ admin kiểm tra giúp ạ.',$now-7200000]);
      }
    }catch(Exception $e){}
  }
  /* ===== BẢNG MỞ RỘNG (giá nạp, thông báo, chat admin, gift code, reply) ===== */
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_settings(k VARCHAR(48) PRIMARY KEY, v TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_packages(
    id INT AUTO_INCREMENT PRIMARY KEY, amount INT NOT NULL, dia INT NOT NULL, xu INT NOT NULL DEFAULT 0,
    bonus VARCHAR(64) NOT NULL DEFAULT '', hot TINYINT NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  if(!$pdo->query("SELECT COUNT(*) FROM web_packages")->fetchColumn()){
    global $PACKAGES; $i=1;
    $insP=$pdo->prepare("INSERT INTO web_packages(amount,dia,xu,bonus,hot,sort) VALUES(?,?,?,?,?,?)");
    foreach(($PACKAGES?:[]) as $pk){ $insP->execute([(int)$pk['amount'],(int)$pk['dia'],(int)($pk['xu']??0),(string)($pk['bonus']??''),!empty($pk['hot'])?1:0,$i++]); }
    /* Gói mặc định đã ở tỉ giá mới (1.000đ=1) → đánh dấu để KHÔNG bị quy đổi lại */
    try{ $pdo->prepare("INSERT INTO web_settings(k,v) VALUES('doge_rate_v2','1') ON DUPLICATE KEY UPDATE v='1'")->execute(); }catch(Exception $e){}
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_notifications(
    id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(64) NOT NULL, type VARCHAR(24) NOT NULL DEFAULT 'info',
    title VARCHAR(190) NOT NULL, body VARCHAR(255) NOT NULL DEFAULT '', link VARCHAR(190) NOT NULL DEFAULT '',
    actor VARCHAR(64) NOT NULL DEFAULT '', is_read TINYINT NOT NULL DEFAULT 0, created BIGINT NOT NULL,
    KEY k_user(username,is_read,id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_chat(
    id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(64) NOT NULL DEFAULT 'SYSTEM',
    message TEXT NOT NULL, kind VARCHAR(16) NOT NULL DEFAULT 'msg', created BIGINT NOT NULL, KEY k_c(id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_giftcodes(
    id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(48) NOT NULL UNIQUE, dia INT NOT NULL DEFAULT 0, xu INT NOT NULL DEFAULT 0,
    max_uses INT NOT NULL DEFAULT 1, used INT NOT NULL DEFAULT 0, note VARCHAR(120) NOT NULL DEFAULT '',
    expires BIGINT NULL, active TINYINT NOT NULL DEFAULT 1, created_by VARCHAR(64) NOT NULL DEFAULT '', created BIGINT NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_giftcode_redeems(
    id INT AUTO_INCREMENT PRIMARY KEY, code_id INT NOT NULL, username VARCHAR(64) NOT NULL, created BIGINT NOT NULL,
    UNIQUE KEY uq_cu(code_id,username)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  /* Migrate: reply lồng nhau cho bình luận + cờ nhắc ticket tồn đọng */
  try{ $pdo->exec("ALTER TABLE web_comments ADD COLUMN parent_id INT NULL AFTER post_id"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_tickets ADD COLUMN chat_alert TINYINT NOT NULL DEFAULT 0 AFTER status"); }catch(Exception $e){}

  /* ===================== DOGECOIN — gộp Xu + Kim Cương ===================== */
  try{ $pdo->exec("ALTER TABLE web_wallet ADD COLUMN dogecoin BIGINT NOT NULL DEFAULT 0 AFTER username"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_wallet ADD COLUMN doge_spent BIGINT NOT NULL DEFAULT 0 AFTER dogecoin"); }catch(Exception $e){}
  /* Một lần: gộp diamonds + xu vào dogecoin (chỉ chạy 1 lần, có cờ chống lặp) */
  try{
    $done=$pdo->query("SELECT v FROM web_settings WHERE k='doge_migrated'")->fetchColumn();
    if(!$done){
      $pdo->exec("UPDATE web_wallet SET dogecoin = dogecoin + COALESCE(diamonds,0) + COALESCE(xu,0)");
      $pdo->prepare("INSERT INTO web_settings(k,v) VALUES('doge_migrated','1') ON DUPLICATE KEY UPDATE v='1'")->execute();
    }
  }catch(Exception $e){}
  /* Một lần: sửa tỉ giá Dogecoin về 1.000đ = 1 (10k = 10). Quy đổi lại gói nạp cũ (÷10) */
  try{
    $done2=$pdo->query("SELECT v FROM web_settings WHERE k='doge_rate_v2'")->fetchColumn();
    if(!$done2){
      $pdo->exec("UPDATE web_packages SET dia = GREATEST(1, ROUND(dia/10)), xu = 0");
      $pdo->exec("UPDATE web_settings SET v='1000' WHERE k='vnd_per_diamond' AND CAST(v AS UNSIGNED) < 1000");
      $pdo->prepare("INSERT INTO web_settings(k,v) VALUES('doge_rate_v2','1') ON DUPLICATE KEY UPDATE v='1'")->execute();
    }
  }catch(Exception $e){}
  /* Cache UUID (online-mode) */
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_uuid(username VARCHAR(64) PRIMARY KEY, uuid VARCHAR(40) NOT NULL, created BIGINT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  /* ===================== MUA RANK (2 phạm vi: all / sdo) ===================== */
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_ranks(
    id INT AUTO_INCREMENT PRIMARY KEY,
    scope VARCHAR(16) NOT NULL DEFAULT 'all',           /* 'all' = tất cả server | 'sdo' = Sword Dark Online */
    name VARCHAR(80) NOT NULL, price BIGINT NOT NULL DEFAULT 0,
    color VARCHAR(16) NOT NULL DEFAULT '#f2b631',
    description TEXT NULL,                                /* mỗi dòng = 1 đặc quyền */
    commands TEXT NULL,                                  /* mỗi dòng = 1 lệnh chạy khi mua; {player} {uuid} */
    sort INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, created BIGINT NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  /* Seed: chuyển rank cũ từ web_shop sang web_ranks (scope all) nếu trống */
  if(!$pdo->query("SELECT COUNT(*) FROM web_ranks")->fetchColumn()){
    $now=ms(); $sort=1;
    $insR=$pdo->prepare("INSERT INTO web_ranks(scope,name,price,color,description,commands,sort,active,created) VALUES(?,?,?,?,?,?,?,1,?)");
    try{
      foreach($pdo->query("SELECT * FROM web_shop WHERE category='rank' ORDER BY sort,id")->fetchAll() as $r){
        $insR->execute(['all',$r['name'],(int)$r['price'],$r['color'],$r['detail'],"lp user {player} parent addtemp ".strtolower($r['name'])." 30d\nbc &6{player} &fvừa mua rank &e".$r['name'],$sort++,$now]);
      }
    }catch(Exception $e){}
    /* Rank riêng cho Sword Dark Online */
    $insR->execute(['sdo','SAO Bronze',800,'#c98b3a',"Tiền tố [Bronze] trong SDO\n/kit sao_bronze mỗi ngày\n+10% EXP khi luyện cấp","lp user {player} parent addtemp sao_bronze 30d server sdo\nbc &b[SDO] &f{player} &7mua &6SAO Bronze",1,$now]);
    $insR->execute(['sdo','SAO Silver',1600,'#cdd2da',"Toàn bộ quyền Bronze\nTiền tố [Silver]\nMở khoá Skill Tree bậc 2\n+25% EXP","lp user {player} parent addtemp sao_silver 30d server sdo\nbc &b[SDO] &f{player} &7mua &fSAO Silver",2,$now]);
    $insR->execute(['sdo','SAO Gold',3200,'#f2b631',"Toàn bộ quyền Silver\nTiền tố [Gold] phát sáng\nPet Boss độc quyền\nVào dungeon không giới hạn · +50% EXP","lp user {player} parent addtemp sao_gold 30d server sdo\nbc &b[SDO] &f{player} &7mua &eSAO Gold",3,$now]);
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_rank_purchases(
    id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(64) NOT NULL, rank_id INT NOT NULL,
    rank_name VARCHAR(80) NOT NULL, scope VARCHAR(16) NOT NULL DEFAULT 'all', price BIGINT NOT NULL, created BIGINT NOT NULL,
    KEY k_u(username)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  /* ===================== ĐẤU GIÁ (rework) ===================== */
  try{ $pdo->exec("ALTER TABLE web_auctions ADD COLUMN item_key VARCHAR(64) NOT NULL DEFAULT 'diamond' AFTER item"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_auctions ADD COLUMN start_price BIGINT NOT NULL DEFAULT 0 AFTER price"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_auctions ADD COLUMN top_bidder VARCHAR(64) NOT NULL DEFAULT '' AFTER start_price"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_auctions ADD COLUMN bid_count INT NOT NULL DEFAULT 0 AFTER top_bidder"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_auctions ADD COLUMN listing_fee BIGINT NOT NULL DEFAULT 0 AFTER bid_count"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_auctions ADD COLUMN status VARCHAR(12) NOT NULL DEFAULT 'active' AFTER listing_fee"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_auctions ADD COLUMN qty INT NOT NULL DEFAULT 1 AFTER item_key"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_auctions ADD COLUMN image VARCHAR(400) NOT NULL DEFAULT '' AFTER qty"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_auctions ADD COLUMN from_inv TINYINT NOT NULL DEFAULT 0 AFTER image"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_auctions ADD COLUMN mode VARCHAR(24) NOT NULL DEFAULT '' AFTER from_inv"); }catch(Exception $e){}
  try{ $pdo->exec("UPDATE web_auctions SET start_price=price WHERE start_price=0 AND price>0"); }catch(Exception $e){}
  /* Kho đồ: thêm ảnh tuỳ chỉnh cho từng vật phẩm */
  try{ $pdo->exec("ALTER TABLE web_inventory ADD COLUMN item_key VARCHAR(64) NOT NULL DEFAULT '' AFTER item"); }catch(Exception $e){}
  try{ $pdo->exec("ALTER TABLE web_inventory ADD COLUMN image VARCHAR(400) NOT NULL DEFAULT '' AFTER color"); }catch(Exception $e){}
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_auction_bids(
    id INT AUTO_INCREMENT PRIMARY KEY, auction_id INT NOT NULL, bidder VARCHAR(64) NOT NULL,
    amount BIGINT NOT NULL, created BIGINT NOT NULL, KEY k_a(auction_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  /* ===================== CHỢ TRỜI (P2P, phí %) ===================== */
  $pdo->exec("CREATE TABLE IF NOT EXISTS web_market(
    id INT AUTO_INCREMENT PRIMARY KEY, seller VARCHAR(64) NOT NULL,
    item_name VARCHAR(120) NOT NULL, item_key VARCHAR(64) NOT NULL DEFAULT 'diamond',
    qty INT NOT NULL DEFAULT 1, price BIGINT NOT NULL DEFAULT 0, image VARCHAR(400) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL DEFAULT '', mode VARCHAR(24) NOT NULL DEFAULT '',
    status VARCHAR(12) NOT NULL DEFAULT 'active', buyer VARCHAR(64) NOT NULL DEFAULT '',
    created BIGINT NOT NULL, sold_at BIGINT NULL, KEY k_s(seller), KEY k_st(status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  try{ $pdo->exec("ALTER TABLE web_market ADD COLUMN image VARCHAR(400) NOT NULL DEFAULT '' AFTER price"); }catch(Exception $e){}

  /* Gift code: thêm cột thưởng Dogecoin (gộp từ dia+xu cho code cũ) */
  try{ $pdo->exec("ALTER TABLE web_giftcodes ADD COLUMN doge BIGINT NOT NULL DEFAULT 0 AFTER code"); }catch(Exception $e){}
  try{ $pdo->exec("UPDATE web_giftcodes SET doge = doge + COALESCE(dia,0) + COALESCE(xu,0) WHERE doge=0"); }catch(Exception $e){}

  return $pdo;
}
