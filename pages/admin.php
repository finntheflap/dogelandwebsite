<?php
/* Page: admin — extracted from index.php lines 2803-3278 */
?>
<?php
  if(!$IS_ADMIN){ echo '<div class="phead"><div class="k">Quản trị</div><h1>Không có quyền</h1><p>Trang này chỉ dành cho Admin. '.($user?'':'<a href="?p=login" style="color:var(--green)">Đăng nhập</a>').'</p></div>'; }
  else {
    $tab=$_GET['tab']??'dash'; $T=$CFG['authme_table'];
    // nhóm điều hướng (icon đơn giản bằng ký tự)
    // 8 nhóm chính — rút gọn từ 13+ mục, mỗi nhóm có sub-tab logic liên quan.
    $groups=[
      'Dashboard'  => [['dash','Tổng quan','▦']],
      'Bài viết'   => [['posts','Bài viết & Tin tức','✎'],['announce','Thông báo khẩn','📢']],
      'Người dùng' => [['users','Quản lý user','👤']],
      'Nạp tiền'   => [['topups','Giao dịch nạp','💳'],['pricing','Giá nạp & KM','💱'],['gift','Gift Code','🎁']],
      'Shop'       => [['ranks','Mua Rank','🎖'],['auc','Đấu giá','🔨'],['market','Chợ Trời','🛒']],
      'Ticket'     => [['tickets','Hỗ trợ player','🎫']],
      'Server'     => [],
      'Log'        => [['logs','Nhật ký Admin','📜']],
    ];
    if(can_console($user)) $groups['Server'][]=['console','Server Console','⌨'];
    if(empty($groups['Server'])) unset($groups['Server']); // ẩn nhóm Server nếu không có sub-tab
    $titles=[]; foreach($groups as $gs) foreach($gs as $it) $titles[$it[0]]=$it[1];
    $oc=open_tickets();
?>
  <div class="adminmode">
    <div class="wrap"><div class="amshell">
      <div class="ammain">
        <div class="amhead"><div><div class="k" style="margin:0">Admin Mode</div><h1 style="font-size:1.8rem;font-weight:800;line-height:1.1;margin-top:4px"><?=h($titles[$tab]??'Dashboard')?></h1></div><span class="amwho"><img src="<?=h($CFG['skin_api'])?>/avatar/<?=urlencode($user)?>/30" data-skin-user="<?=h($user)?>" data-skin-size="30" onerror="skinFallback(this)" alt=""><?=h($user)?> · <?= is_supervisor($user)?'Supervisor':role_label(user_role($user)) ?></span></div>

    <?php if($tab==='dash'){
      $stat=function($q,$d=0){ try{ return (int)db()->query($q)->fetchColumn(); }catch(Exception $e){ return $d; } };
      $now0 = ms();
      $day7 = $now0 - 7*86400000;
      $day24 = $now0 - 86400000;
      $day30 = $now0 - 30*86400000;
      // Stats cơ bản
      $users      = $stat("SELECT COUNT(*) FROM `$T`");
      $usersNew7  = $stat("SELECT COUNT(*) FROM `$T` WHERE regdate>$day7");
      $usersNew24 = $stat("SELECT COUNT(*) FROM `$T` WHERE regdate>$day24");
      $rev7       = $stat("SELECT COALESCE(SUM(amount),0) FROM web_topups WHERE status='success' AND created>$day7");
      $revTotal   = $stat("SELECT COALESCE(SUM(amount),0) FROM web_topups WHERE status='success'");
      $pendT      = $stat("SELECT COUNT(*) FROM web_topups WHERE status='pending'");
      $opent      = $stat("SELECT COUNT(*) FROM web_tickets WHERE status<>'closed'");
      $banned     = $stat("SELECT COUNT(*) FROM web_wallet WHERE banned=1");
      $promo      = dgl_promo();
      $activeGifts= $stat("SELECT COUNT(*) FROM web_giftcodes WHERE active=1 AND used<max_uses AND (expires IS NULL OR expires>$now0)");
      // Live online (qua heartbeat)
      $totalOnline = function_exists('get_total_online') ? get_total_online() : 0;
      $srvStatus = function_exists('get_servers_status') ? get_servers_status(false) : [];
      // Top spender 30d
      $topSpender = []; try{ $topSpender = db()->query("SELECT username, SUM(amount) AS total FROM web_topups WHERE status='success' AND created>$day30 GROUP BY username ORDER BY total DESC LIMIT 3")->fetchAll(); }catch(Exception $e){}
      // Recent activity
      $recentT=[]; try{ $recentT=db()->query("SELECT * FROM web_tickets ORDER BY updated DESC LIMIT 5")->fetchAll(); }catch(Exception $e){}
      $recentL=[]; try{ $recentL=db()->query("SELECT * FROM web_admin_log ORDER BY id DESC LIMIT 6")->fetchAll(); }catch(Exception $e){}
      $recentTop=[]; try{ $recentTop=db()->query("SELECT * FROM web_topups WHERE status='pending' ORDER BY id DESC LIMIT 5")->fetchAll(); }catch(Exception $e){}
      $slt=['open'=>'Đang mở','in_progress'=>'Đang xử lý','closed'=>'Đã đóng'];
    ?>

      <!-- TOP ROW: 4 KPI cards lớn -->
      <div class="dash-kpi">
        <div class="kpi-card kpi-online">
          <div class="kpi-ico">⚡</div>
          <div class="kpi-body">
            <div class="kpi-val"><?=number_format($totalOnline)?></div>
            <div class="kpi-lbl">Đang online</div>
            <div class="kpi-sub"><?=count(array_filter($srvStatus, fn($s)=>$s['online']))?>/<?=count($srvStatus)?> server</div>
          </div>
        </div>
        <div class="kpi-card kpi-users">
          <div class="kpi-ico">👥</div>
          <div class="kpi-body">
            <div class="kpi-val"><?=number_format($users)?></div>
            <div class="kpi-lbl">Tổng người dùng</div>
            <div class="kpi-sub"><span style="color:#7dd47f">+<?=number_format($usersNew7)?></span> trong 7 ngày · <span style="color:#7dd47f">+<?=number_format($usersNew24)?></span> hôm nay</div>
          </div>
        </div>
        <div class="kpi-card kpi-rev">
          <div class="kpi-ico">💰</div>
          <div class="kpi-body">
            <div class="kpi-val"><?=number_format($rev7,0,',','.')?>đ</div>
            <div class="kpi-lbl">Doanh thu 7 ngày</div>
            <div class="kpi-sub">Tổng tích lũy: <?=number_format($revTotal,0,',','.')?>đ</div>
          </div>
        </div>
        <a class="kpi-card kpi-alert" href="?p=admin&tab=topups">
          <div class="kpi-ico"><?= $pendT>0?'🔴':'✅' ?></div>
          <div class="kpi-body">
            <div class="kpi-val"><?=$pendT?></div>
            <div class="kpi-lbl">Giao dịch chờ duyệt</div>
            <div class="kpi-sub"><?= $pendT>0?'Cần xử lý →':'Không có việc cần làm' ?></div>
          </div>
        </a>
      </div>

      <!-- SECOND ROW: server status (full width) -->
      <div class="card dash-servers" style="margin-top:14px">
        <div class="ahd" style="border:0;padding:14px 18px 8px;display:flex;align-items:center;gap:8px"><span>🖥</span><span>Trạng thái server</span></div>
        <?php if(!$srvStatus){ echo '<div class="cmid" style="padding:14px">Plugin chưa hook hoặc chưa có server nào.</div>'; }
        else { ?>
        <div class="dash-srvlist">
          <?php foreach($srvStatus as $s){
            $dot = $s['online'] ? 'on' : 'off';
            echo '<div class="dash-srvitem"><span class="srvdot '.$dot.'"></span>'
                .'<div class="dash-srvname">'.h($s['name']).'</div>'
                .'<div class="dash-srvcount">'.($s['online']?(int)$s['count'].' online':'<span class="sub2">Offline</span>').'</div>'
                .'</div>';
          } ?>
        </div>
        <?php } ?>
      </div>

      <!-- THIRD ROW: Top spender + Pending topups + Recent activity -->
      <div class="dash-row3" style="margin-top:14px">
        <div class="card" style="padding:0;overflow:hidden">
          <div class="ahd" style="display:flex;align-items:center;gap:8px"><span>🏆</span><span>Top nạp 30 ngày</span></div>
          <?php if(!$topSpender) echo '<div class="cmid">Chưa có giao dịch.</div>';
            else { $rk=0; foreach($topSpender as $sp){ $rk++;
              echo '<a class="tk" href="?p=admin&tab=users&euser='.urlencode($sp['username']).'">'
                  .'<span class="tno" style="color:'.($rk===1?'#f2b631':($rk===2?'#c0c4cd':'#cd7f32')).'">#'.$rk.'</span>'
                  .'<div class="ti"><div class="ts">'.h($sp['username']).'</div><div class="tmeta">'.number_format($sp['total'],0,',','.').'đ</div></div>'
                  .'<img src="'.h($CFG['skin_api']).'/avatar/'.urlencode($sp['username']).'/28" data-skin-user="'.h($sp['username']).'" data-skin-size="28" onerror="skinFallback(this)" style="width:28px;height:28px;border-radius:6px" alt="">'
                  .'</a>';
            }} ?>
        </div>
        <div class="card" style="padding:0;overflow:hidden">
          <div class="ahd" style="display:flex;align-items:center;gap:8px"><span>⏳</span><span>Chờ duyệt (<?=count($recentTop)?>)</span></div>
          <?php if(!$recentTop) echo '<div class="cmid">Không có giao dịch chờ.</div>';
            else foreach($recentTop as $tp){
              echo '<a class="tk" href="?p=admin&tab=topups">'
                  .'<span class="tno" style="color:var(--gold)">'.number_format((int)$tp['amount'],0,',','.').'đ</span>'
                  .'<div class="ti"><div class="ts">'.h($tp['username']).'</div><div class="tmeta">'.h($tp['method']).' · '.date('d/m H:i',(int)($tp['created']/1000)).'</div></div>'
                  .'<span class="tst in_progress">PENDING</span>'
                  .'</a>';
            } ?>
        </div>
        <div class="card" style="padding:0;overflow:hidden">
          <div class="ahd" style="display:flex;align-items:center;gap:8px"><span>🎫</span><span>Ticket gần đây</span></div>
          <?php if(!$recentT) echo '<div class="cmid">Chưa có ticket.</div>';
            else foreach($recentT as $t) echo '<a class="tk" href="?p=ticket&id='.(int)$t['id'].'"><span class="tno">'.h($t['code']?:ticket_code($t['id'])).'</span><div class="ti"><div class="ts">'.h($t['subject']).'</div><div class="tmeta">'.h($t['username']).' · '.date('d/m H:i',(int)($t['updated']/1000)).'</div></div><span class="tst '.h($t['status']).'">'.($slt[$t['status']]??$t['status']).'</span></a>'; ?>
        </div>
      </div>

      <!-- FOURTH ROW: Admin activity log (full width) -->
      <div class="card" style="padding:0;overflow:hidden;margin-top:14px">
        <div class="ahd" style="display:flex;align-items:center;gap:8px"><span>📜</span><span>Hoạt động Admin gần đây</span></div>
        <?php if(!$recentL) echo '<div class="cmid">Chưa có hoạt động.</div>';
          else { echo '<div style="padding:6px 0">'; foreach($recentL as $lg) echo '<div class="logrow"><img src="'.h($CFG['skin_api']).'/avatar/'.urlencode($lg['admin']).'/28" data-skin-user="'.h($lg['admin']).'" data-skin-size="28" onerror="skinFallback(this)" alt=""><div class="lgb"><span class="lga">'.h($lg['admin']).'</span> <span class="lgac">'.h($lg['action']).'</span><div class="lgd">'.h($lg['detail']).'</div></div><span class="lgt">'.date('d/m H:i',(int)($lg['created']/1000)).'</span></div>'; echo '</div>'; } ?>
      </div>

    <?php } elseif($tab==='posts'){
      $edit=null; if(!empty($_GET['edit'])){ try{ $st=db()->prepare("SELECT * FROM web_posts WHERE id=?"); $st->execute([(int)$_GET['edit']]); $edit=$st->fetch(); }catch(Exception $e){} }
      $all=[]; try{ $all=db()->query("SELECT * FROM web_posts ORDER BY pinned DESC,id DESC")->fetchAll(); }catch(Exception $e){} ?>
      <div class="admin-grid">
        <div class="card" style="padding:26px">
          <h3 class="ah"><?= $edit?'Sửa bài viết':'Đăng bài mới' ?></h3>
          <form method="post" action="?p=admin">
            <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="post_save"><input type="hidden" name="id" value="<?= $edit?(int)$edit['id']:0 ?>">
            <div class="field"><label>Loại bài</label><select name="type">
              <?php $curT = $edit?$edit['type']:'news';
                foreach(post_types_allowed() as $tk) echo '<option value="'.h($tk).'"'.($curT===$tk?' selected':'').'>'.h(post_type_label($tk)).'</option>'; ?>
            </select><div class="sub2" style="margin-top:4px;font-size:.78rem">
              <b>Sự kiện</b>: lễ hội / event giới hạn (có ngày diễn ra). <b>Thông báo</b>: tin chung. <b>Hướng dẫn</b>: cách chơi, mẹo. <b>Nội quy</b>: luật server. <b>Cập nhật</b>: patch notes / changelog (nên ghi tên server bên dưới).
            </div></div>
            <div class="field"><label>Tiêu đề</label><input name="title" value="<?= $edit?h($edit['title']):'' ?>" required></div>
            <div class="field"><label>Ảnh bìa (URL)</label><input name="image" value="<?= $edit?h($edit['image']):'' ?>" placeholder="https://i.imgur.com/..."></div>
            <div class="g2">
              <div class="field"><label>Ngày diễn ra (Sự kiện)</label><input type="date" name="event_at" value="<?= ($edit&&$edit['event_at'])?date('Y-m-d',(int)($edit['event_at']/1000)):'' ?>"></div>
              <div class="field"><label>Server (gắn bài cho server riêng — hiện ở tab Tin tức của server đó)</label>
                <input name="server" value="<?= $edit?h($edit['server']??''):'' ?>" placeholder="VD: RPG Towny Survival / Sword Dark Online / Skyblock — để trống nếu áp dụng toàn network" list="srv-suggest">
                <datalist id="srv-suggest">
                  <?php foreach(($CFG['modes']??[]) as $msid=>$mname){ echo '<option value="'.h($mname).'">'.h($msid).'</option>'; } ?>
                </datalist>
                <div class="sub2" style="margin-top:4px;font-size:.78rem">Gõ <b>tên đầy đủ</b> server (vd: "RPG Towny Survival") để bài hiện trong tab "Tin tức" của trang server.</div>
              </div>
            </div>
            <div class="field"><label>Nội dung</label><textarea name="content" required><?= $edit?h($edit['content']):'' ?></textarea></div>
            <label class="chk"><input type="checkbox" name="pinned" value="1"<?= ($edit&&$edit['pinned'])?' checked':'' ?>> Ghim lên đầu trang</label>
            <button class="btn btn-green btn-block" type="submit"><?= $edit?'Cập nhật':'Đăng bài' ?></button>
            <?php if($edit) echo '<a class="btn btn-ghost btn-block" href="?p=admin&tab=posts" style="margin-top:10px">Huỷ</a>'; ?>
          </form>
        </div>
        <div class="card" style="padding:0;overflow:hidden">
          <div class="ahd">Tất cả bài viết (<?=count($all)?>)</div>
          <div style="overflow-x:auto"><table class="tbl"><tbody>
          <?php if(!$all) echo '<tr><td class="cmid">Chưa có bài viết.</td></tr>';
            else foreach($all as $a){ $srvTag = !empty($a['server']) ? ' <span class="ptag-srv">🖥️ '.h($a['server']).'</span>' : '';
              echo '<tr><td><span class="ptag '.h($a['type']).'" style="margin:0 0 6px">'.h(post_type_label($a['type'])).'</span>'.$srvTag.'<div style="font-weight:700;margin-top:4px">'.h($a['title']).'</div><div class="sub2">'.date('d/m/Y',(int)($a['created']/1000)).($a['pinned']?' · Đã ghim':'').'</div></td><td class="tr"><a class="btn btn-ghost btn-sm" href="?p=admin&tab=posts&edit='.(int)$a['id'].'">Sửa</a> <form method="post" action="?p=admin" style="display:inline" onsubmit="return confirm(\'Xoá?\')"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="post_delete"><input type="hidden" name="id" value="'.(int)$a['id'].'"><button class="btn btn-sm bdel">Xoá</button></form></td></tr>';
            } ?>
          </tbody></table></div>
        </div>
      </div>

    <?php } elseif($tab==='users'){
      $eu=$_GET['euser']??null; $eue=null; $euw=null;
      if($eu){ try{ $st=db()->prepare("SELECT * FROM `$T` WHERE LOWER(username)=?"); $st->execute([strtolower($eu)]); $eue=$st->fetch(); $euw=wallet($eu); }catch(Exception $e){} }
      // ===== Search + filter =====
      $q       = trim($_GET['q'] ?? '');
      $fStatus = trim($_GET['status'] ?? '');
      $fSort   = trim($_GET['sort'] ?? 'newest');
      $offset  = max(0, (int)($_GET['offset'] ?? 0));
      $perPage = 50;
      // Build WHERE + binds
      $where = []; $binds = [];
      if ($q !== '') {
        $where[] = '(LOWER(a.username) LIKE ? OR LOWER(a.email) LIKE ? OR LOWER(a.realname) LIKE ?)';
        $like = '%'.strtolower($q).'%'; $binds[] = $like; $binds[] = $like; $binds[] = $like;
      }
      if ($fStatus === 'banned')    $where[] = 'w.banned=1';
      elseif ($fStatus === 'verified') $where[] = 'w.verified=1';
      elseif ($fStatus === 'unverified') $where[] = '(w.verified=0 OR w.verified IS NULL)';
      elseif ($fStatus === 'admin')   $where[] = 'EXISTS (SELECT 1 FROM web_admins ad WHERE LOWER(ad.username)=LOWER(a.realname))';
      $whereSql = $where ? ' WHERE '.implode(' AND ',$where) : '';
      $orderBy = ['newest'=>'a.id DESC','oldest'=>'a.id ASC','lastlogin'=>'COALESCE(a.lastlogin,0) DESC','az'=>'a.realname ASC'][$fSort] ?? 'a.id DESC';
      // Count + paged query
      $totalU = 0; $users = [];
      try {
        $cs = db()->prepare("SELECT COUNT(*) FROM `$T` a LEFT JOIN web_wallet w ON w.username=a.realname".$whereSql);
        $cs->execute($binds); $totalU = (int)$cs->fetchColumn();
        $sql = "SELECT a.realname uname, a.email, a.regdate, a.lastlogin AS authme_lastlogin, a.ip AS authme_ip,
                  COALESCE(w.verified,0) verified, COALESCE(w.banned,0) banned, COALESCE(w.ban_reason,'') ban_reason,
                  COALESCE((SELECT role FROM web_admins ad WHERE LOWER(ad.username)=LOWER(a.realname) LIMIT 1),'') AS adm_role,
                  COALESCE((SELECT console FROM web_admins ad2 WHERE LOWER(ad2.username)=LOWER(a.realname) LIMIT 1),0) AS has_console
                FROM `$T` a LEFT JOIN web_wallet w ON w.username=a.realname".$whereSql." ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
        $us = db()->prepare($sql); $us->execute($binds); $users = $us->fetchAll();
      } catch(Exception $e){}
      // Build URL with filters preserved
      $qsBase = ['p'=>'admin','tab'=>'users'];
      if($q!=='') $qsBase['q']=$q;
      if($fStatus!=='') $qsBase['status']=$fStatus;
      if($fSort!=='newest') $qsBase['sort']=$fSort;
      $mkUrl = function($extra=[]) use($qsBase){ return '?'.http_build_query(array_merge($qsBase,$extra)); };
    ?>
      <?php if($eue){
        $einv=[]; try{ $is=db()->prepare("SELECT * FROM web_inventory WHERE username=? ORDER BY mode,id"); $is->execute([$eue['realname']]); foreach($is->fetchAll() as $row) $einv[$row['mode']][]=$row; }catch(Exception $e){}
        $modes=$CFG['modes'];
        // AuthMe info
        $authIp = $eue['ip'] ?? ''; $authRegIp = $eue['regip'] ?? '';
        $authLastLogin = (int)($eue['lastlogin'] ?? 0);
        $authRegDate = (int)($eue['regdate'] ?? 0);
        $authWorld = $eue['world'] ?? '';
        $authX = (float)($eue['x'] ?? 0); $authY = (float)($eue['y'] ?? 0); $authZ = (float)($eue['z'] ?? 0);
        // Server stats per-server (web_player_stats)
        $srvStats = []; try{ $ss=db()->prepare("SELECT * FROM web_player_stats WHERE username=? ORDER BY playtime_sec DESC"); $ss->execute([$eue['realname']]); $srvStats=$ss->fetchAll(); }catch(Exception $e){}
        // Topup summary
        $topupTotal = 0; $topupCount = 0; $recentTopups = [];
        try {
          $tt = db()->prepare("SELECT COALESCE(SUM(amount),0), COUNT(*) FROM web_topups WHERE username=? AND status='success'");
          $tt->execute([$eue['realname']]); $row=$tt->fetch(PDO::FETCH_NUM); $topupTotal=(int)$row[0]; $topupCount=(int)$row[1];
          $rt = db()->prepare("SELECT * FROM web_topups WHERE username=? ORDER BY id DESC LIMIT 5"); $rt->execute([$eue['realname']]); $recentTopups=$rt->fetchAll();
        } catch(Exception $e){}
        // Login history (web + game)
        $loginHist = [];
        try { $lh = db()->prepare("SELECT * FROM web_login_log WHERE LOWER(username)=? ORDER BY id DESC LIMIT 20");
          $lh->execute([strtolower($eue['realname'])]); $loginHist = $lh->fetchAll(); }
        catch(Exception $e){}
        // Auto-unban nếu ban_until đã hết hạn
        if(!empty($euw['banned']) && (int)($euw['ban_until']??0)>0 && (int)$euw['ban_until']<ms()){
          try{ db()->prepare("UPDATE web_wallet SET banned=0, ban_reason='', banned_by='', banned_at=0, ban_until=0, ban_ip='' WHERE username=?")->execute([$eue['realname']]);
            $euw['banned']=0; $euw['ban_until']=0; $euw['ban_ip']=''; $euw['ban_reason']=''; $euw['banned_by']=''; }catch(Exception $e){}
        }
        // Online detection: scan heartbeat players
        $onlineOn = '';
        if (function_exists('get_servers_status')) {
          foreach(get_servers_status(false) as $sid=>$sv){
            if (!empty($sv['online']) && !empty($sv['players']) && in_array($eue['realname'], $sv['players'], true)) { $onlineOn = $sv['name'] ?: $sid; break; }
          }
        }
        // Permissions check
        $userRole = user_role($eue['realname']);
        $isUserAdmin = ($userRole !== '');
        $isUserConsole = false;
        try {
          $ck = db()->prepare("SELECT console FROM web_admins WHERE LOWER(username)=? LIMIT 1");
          $ck->execute([strtolower($eue['realname'])]);
          if($r=$ck->fetch()) $isUserConsole=!empty($r['console']);
        } catch(Exception $e){}
        $isUserOwner = is_owner($eue['realname']);  // hardcoded supervisor từ config
      ?>
        <a class="btn btn-ghost btn-sm" href="?p=admin&tab=users" style="margin-bottom:14px">← Danh sách user</a>

        <!-- USER HEADER with avatar + quick stats -->
        <div class="card" style="padding:24px;margin-bottom:16px">
          <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap">
            <img src="<?=h($CFG['skin_api'])?>/avatar/<?=urlencode($eue['realname'])?>/80" data-skin-user="<?=h($eue['realname'])?>" data-skin-size="80" onerror="skinFallback(this)" style="width:80px;height:80px;border-radius:14px;background:#1a1d22" alt="">
            <div style="flex:1;min-width:200px">
              <h2 style="font-size:1.6rem;font-weight:800;margin:0;line-height:1.2"><?=h($eue['realname'])?>
                <?php if($isUserOwner) echo '<span class="st" style="background:rgba(255,141,176,.18);color:#ff8db0;margin-left:6px">SUPERVISOR</span>';
                  elseif($isUserAdmin) echo '<span class="st" style="background:rgba(91,141,239,.18);color:#8fb4ff;margin-left:6px">ADMIN</span>'; ?>
              </h2>
              <div class="sub2" style="margin-top:4px">
                <code><?=h($eue['username'])?></code>
                <?php if($eue['email']) echo ' · '.h($eue['email']); ?>
                <?php if($authRegDate>0) echo ' · Đăng ký '.date('d/m/Y',(int)($authRegDate/1000)); ?>
              </div>
            </div>
            <div style="display:flex;gap:18px;text-align:right">
              <div><div style="font-size:1.4rem;font-weight:800;color:var(--gold)"><?=doge_balance($eue['realname'])?></div><div class="sub2">Dogecoin</div></div>
              <div><div style="font-size:1.4rem;font-weight:800;color:#7dd47f"><?=number_format($topupTotal,0,',','.')?>đ</div><div class="sub2">Đã nạp (<?=$topupCount?> lần)</div></div>
              <div><div style="font-size:1.4rem;font-weight:800;color:#9fd2ff"><?=(int)($euw['logins']??0)?></div><div class="sub2">Login web</div></div>
            </div>
          </div>
        </div>

        <?php
          $isBanE = !empty($euw['banned']); $ownerE = is_owner($eue['realname']);
          $banUntilE = (int)($euw['ban_until']??0);
          $banIpE    = (string)($euw['ban_ip']??'');
          $roleColors = ['supervisor'=>'#ff8db0','admin'=>'#7dd47f','support'=>'#8fb4ff'];
          $curColor   = $roleColors[$userRole] ?? '#9aa0a6';
        ?>

        <!-- ROW 1: AuthMe + Trạng thái + Ban (left) | Server activity (right) -->
        <div class="admin-grid" style="margin-bottom:16px">
          <div class="card" style="padding:22px<?= $isBanE?';border-color:rgba(224,88,74,.4)':'' ?>">
            <h3 class="ah" style="display:flex;align-items:center;gap:8px">🔐 Thông tin AuthMe & Trạng thái</h3>

            <!-- Status badges row -->
            <div class="ustatusrow">
              <?php if($onlineOn): ?>
                <span class="ustatus on"><span class="ustatus-dot"></span> Online · <?=h($onlineOn)?></span>
              <?php else: ?>
                <span class="ustatus off"><span class="ustatus-dot"></span> Offline<?= $authLastLogin>0?' · '.date('d/m H:i',(int)($authLastLogin/1000)):'' ?></span>
              <?php endif; ?>
              <?php if($isBanE){
                if($banUntilE>0){
                  $hours = max(0, ($banUntilE - ms())/3600000);
                  $left = $hours>=24 ? round($hours/24).' ngày' : round($hours,1).' giờ';
                  echo '<span class="ustatus banned">🚫 Ban '.$left.' nữa · '.date('d/m/Y H:i',(int)($banUntilE/1000)).'</span>';
                } else echo '<span class="ustatus banned">🚫 Ban vĩnh viễn</span>';
                if($banIpE!=='') echo '<span class="ustatus ipban">📡 IP banned · <code>'.h($banIpE).'</code></span>';
              } else echo '<span class="ustatus ok">✓ Không bị ban</span>'; ?>
            </div>
            <?php if($isBanE && !empty($euw['ban_reason'])): ?>
              <div class="banreason">Lý do: <b><?=h($euw['ban_reason'])?></b><?= !empty($euw['banned_by'])?' · bởi '.h($euw['banned_by']):'' ?><?= !empty($euw['banned_at'])?' · '.date('d/m/Y H:i',(int)($euw['banned_at']/1000)):'' ?></div>
            <?php endif; ?>

            <!-- AuthMe details -->
            <div class="udlist">
              <div><span class="udl-k">Last login game</span><span class="udl-v"><?= $authLastLogin>0?date('d/m/Y H:i:s',(int)($authLastLogin/1000)):'<span class="sub2">Chưa từng</span>' ?></span></div>
              <div><span class="udl-k">Login IP</span><span class="udl-v"><code><?=h($authIp?:'—')?></code></span></div>
              <div><span class="udl-k">Đăng ký lúc</span><span class="udl-v"><?= $authRegDate>0?date('d/m/Y H:i:s',(int)($authRegDate/1000)):'—' ?></span></div>
              <div><span class="udl-k">Đăng ký IP</span><span class="udl-v"><code><?=h($authRegIp?:'—')?></code></span></div>
              <div><span class="udl-k">Last location</span><span class="udl-v"><?= $authWorld?'<code>'.h($authWorld).'</code> '.round($authX).' / '.round($authY).' / '.round($authZ):'<span class="sub2">—</span>' ?></span></div>
            </div>

            <!-- Ban controls -->
            <?php if(!$ownerE): ?>
              <div class="bancontrols">
                <?php if($isBanE): ?>
                  <form method="post" action="?p=admin&tab=users" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="user_unban"><input type="hidden" name="username" value="<?=h($eue['realname'])?>"><input type="hidden" name="back_edit" value="1">
                    <button class="btn btn-green btn-sm" type="submit">✓ Gỡ ban</button>
                    <span class="sub2">Hệ thống sẽ gỡ AuthMe ban + IP ban trên mọi server.</span>
                  </form>
                <?php else: ?>
                  <form method="post" action="?p=admin&tab=users" class="banform" onsubmit="return confirm('Ban tài khoản <?=h($eue['realname'])?>?')">
                    <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="user_ban"><input type="hidden" name="username" value="<?=h($eue['realname'])?>"><input type="hidden" name="back_edit" value="1">
                    <select name="days" class="banform-d">
                      <option value="0">Vĩnh viễn</option>
                      <option value="1">1 ngày</option>
                      <option value="3">3 ngày</option>
                      <option value="7">7 ngày</option>
                      <option value="30" selected>30 ngày</option>
                      <option value="90">90 ngày</option>
                      <option value="365">1 năm</option>
                    </select>
                    <input name="reason" placeholder="Lý do (tuỳ chọn)" class="banform-r">
                    <label class="banform-ip" title="Ban luôn IP cuối đăng nhập (chặn cả tài khoản phụ cùng IP)"><input type="checkbox" name="ban_ip" value="1"> Ban IP</label>
                    <button class="btn bdel btn-sm" type="submit" style="background:var(--red);color:#fff">🚫 Ban</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="card" style="padding:22px">
            <h3 class="ah" style="display:flex;align-items:center;gap:8px">🖥 Hoạt động trên server</h3>
            <?php if(!$srvStats) echo '<p class="sub2" style="margin:0">Chưa có data stats. Plugin DogelandSync chưa sync cho user này.</p>';
              else { echo '<div class="udlist">';
                foreach($srvStats as $ss0){
                  $srvName = $modes[$ss0['server_id']] ?? $ss0['server_id'];
                  $playh = function_exists('fmt_playtime') ? fmt_playtime((int)$ss0['playtime_sec']) : ((int)$ss0['playtime_sec'].'s');
                  $lastSeen = (int)$ss0['last_seen']>0 ? date('d/m H:i',(int)($ss0['last_seen']/1000)) : '—';
                  echo '<div><span class="udl-k">'.h($srvName).'</span><span class="udl-v">'.h($playh).' chơi · Lv '.(int)$ss0['level'].' · Last seen '.$lastSeen.'</span></div>';
                }
              echo '</div>'; } ?>
          </div>
        </div>

        <!-- ROW 2: Topup history (left) | Login history (right) -->
        <div class="admin-grid" style="margin-bottom:16px">
          <div class="card" style="padding:22px">
            <h3 class="ah" style="display:flex;align-items:center;gap:8px">💳 Lịch sử nạp tiền</h3>
            <?php if(!$recentTopups) echo '<p class="sub2" style="margin:0">Chưa có giao dịch nào.</p>';
              else { echo '<div class="udlist">';
                foreach($recentTopups as $tp){
                  $stCls=['success'=>'success','rejected'=>'rejected','pending'=>'pending'][$tp['status']]??'pending';
                  echo '<div><span class="udl-k">'.date('d/m/Y H:i',(int)($tp['created']/1000)).'</span><span class="udl-v">'.number_format((int)$tp['amount'],0,',','.').'đ · '.h($tp['method']).' · <span class="st '.$stCls.'">'.h($tp['status']).'</span></span></div>';
                }
              echo '</div>'; }
              if($topupCount > 5) echo '<p class="sub2" style="margin-top:10px">... và '.($topupCount-5).' giao dịch khác. Tổng nạp đã duyệt: <b>'.number_format($topupTotal,0,',','.').'đ</b></p>';
            ?>
          </div>
          <div class="card" style="padding:22px">
            <h3 class="ah" style="display:flex;align-items:center;gap:8px">📜 Lịch sử đăng nhập <span class="sub2" style="font-weight:600;font-size:.78rem">(web · 20 mới nhất)</span></h3>
            <?php if(!$loginHist) echo '<p class="sub2" style="margin:0">Chưa ghi nhận lần đăng nhập web nào.</p>';
              else { echo '<div class="loginlog">';
                foreach($loginHist as $lg){
                  $ok = !empty($lg['success']);
                  $tType = $lg['type']==='game' ? '🎮' : '🌐';
                  $tIcon = $ok ? '<span class="lg-ok">●</span>' : '<span class="lg-fail">●</span>';
                  echo '<div class="loginlog-r">'
                    .'<span class="lg-time">'.date('d/m H:i:s',(int)($lg['created']/1000)).'</span>'
                    .'<span class="lg-status">'.$tIcon.' '.$tType.'</span>'
                    .'<span class="lg-ip"><code>'.h($lg['ip']?:'—').'</code></span>'
                    .'</div>';
                }
              echo '</div>'; } ?>
          </div>
        </div>

        <!-- COMPACT PERMISSIONS STRIP -->
        <?php if(!$isUserOwner): ?>
        <div class="permbar"<?= $isUserAdmin?' style="border-color:'.$curColor.'55"':'' ?>>
          <div class="permbar-lbl"><span>Phân quyền:</span>
            <?php
              if($userRole) echo '<span class="st" style="background:'.$curColor.'22;color:'.$curColor.';font-weight:800">'.role_label($userRole).'</span>';
              else echo '<span class="st" style="background:rgba(255,255,255,.05);color:var(--muted)">Player</span>';
              if($isUserConsole) echo ' <span class="st" style="background:rgba(125,212,127,.18);color:#7dd47f">Console</span>';
            ?>
          </div>
          <?php if(is_supervisor($user)){ ?>
            <form method="post" action="?p=admin&tab=users&euser=<?=urlencode($eue['realname'])?>" class="permbar-f">
              <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="role_set"><input type="hidden" name="username" value="<?=h($eue['realname'])?>">
              <select name="role">
                <option value=""<?=!$userRole?' selected':''?>>Player</option>
                <option value="support"<?=$userRole==='support'?' selected':''?>>Support</option>
                <option value="admin"<?=$userRole==='admin'?' selected':''?>>Admin</option>
              </select>
              <label><input type="checkbox" name="console" value="1"<?=$isUserConsole?' checked':''?>> Console</label>
              <button class="btn btn-green btn-sm" type="submit">Lưu</button>
            </form>
          <?php } else echo '<span class="sub2" style="margin-left:auto">Chỉ Supervisor mới đổi được.</span>'; ?>
        </div>
        <?php endif; ?>

        <!-- EDIT FORM (simplified — wallet + rank + verify) -->
        <div class="card" style="padding:24px;margin-bottom:20px">
          <h3 class="ah">✎ Sửa nhanh tài khoản</h3>
          <form method="post" action="?p=admin&tab=users&euser=<?=urlencode($eue['realname'])?>">
            <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="user_save"><input type="hidden" name="username" value="<?=h($eue['realname'])?>">
            <div class="g2">
              <div class="field"><label>Email</label><input name="email" value="<?=h($eue['email'])?>"></div>
              <div class="field"><label>Reset password (để trống = giữ)</label><input name="newpw" type="text" placeholder="Mật khẩu mới"></div>
              <div class="field"><label><?=h($CFG['doge_label']??'Dogecoin')?> (số dư)</label><input name="dogecoin" type="number" value="<?=doge_balance($eue['realname'])?>"></div>
              <div class="field"><label>Rank in-game</label><input name="rank_name" value="<?=h($euw['rank_name']??'')?>" placeholder="vip, mvp, default..."></div>
              <div class="field"><label>Suffix in-game</label><input name="suffix" value="<?=h($euw['suffix']??'')?>" placeholder="&c[Huyền Thoại]"></div>
              <div class="field"><label>&nbsp;</label><label class="chk" style="margin-top:6px"><input type="checkbox" name="verified" value="1"<?= $euw['verified']?' checked':'' ?>> Đã verify</label></div>
            </div>
            <p class="sub2" style="margin:-4px 0 14px">Đổi <b>Rank/Suffix</b> → tạo lệnh LuckPerms qua RCON queue.</p>
            <div style="display:flex;gap:10px"><button class="btn btn-green" type="submit">Lưu thay đổi</button><a class="btn btn-ghost" href="?p=admin&tab=users">Huỷ</a></div>
          </form>
        </div>
        <?php
          // ===== Inventory render từ DogelandSync (chỉ server có trong $CFG['modes']) =====
          $invByServer = []; // [server_id => [section => [items]]]
          $validModes = array_keys($modes);
          if ($validModes) {
            try{
              $ph = implode(',', array_fill(0, count($validModes), '?'));
              $iq=db()->prepare("SELECT * FROM web_inventory WHERE username=? AND mode IN ($ph) ORDER BY mode, section, slot, id");
              $iq->execute(array_merge([$eue['realname']], $validModes));
              foreach($iq->fetchAll() as $row){
                $invByServer[$row['mode']??''][$row['section']??'main'][] = $row;
              }
            }catch(Exception $e){}
          }
          $sectionLabels = [
            'main'    => ['Túi chính (36 ô)', '🎒'],
            'armor'   => ['Giáp', '🛡️'],
            'offhand' => ['Tay trái', '✋'],
            'ender'   => ['Hòm Ender (27 ô)', '🟪'],
          ];
        ?>
        <div class="card" style="padding:24px;margin-bottom:20px">
          <h3 class="ah" style="display:flex;align-items:center;gap:8px">📦 Kho đồ thật (DogelandSync) — <?=h($eue['realname'])?></h3>
          <p class="sub2" style="margin-bottom:18px">Inventory thật của player được sync từ plugin DogelandSync. Item <span style="color:#f2b631">khoá vàng 🔒</span> = đang rao bán/đấu giá. Item <span style="color:#9fd2ff">khoá xanh ⏳</span> = chờ giao cho player khi vào game.</p>

          <?php if (!$invByServer) { ?>
            <p class="sub2" style="margin:0">Kho đồ trống hoặc plugin chưa sync. Plugin sẽ tự snapshot khi player join/quit/đóng inventory.</p>
          <?php } else {
            foreach ($invByServer as $modeId => $sections) {
              $modeName = $modes[$modeId] ?? ($modeId ?: 'Unknown');
              echo '<div class="invsrv"><div class="invsrv-hd">🖥 '.h($modeName).' <code>'.h($modeId).'</code></div>';
              foreach (['main','armor','offhand','ender'] as $secKey) {
                $items = $sections[$secKey] ?? [];
                if (!$items) continue;
                $secLbl = $sectionLabels[$secKey] ?? [ucfirst($secKey),''];
                echo '<div class="invsec"><div class="invsec-hd">'.$secLbl[1].' '.h($secLbl[0]).' <span class="sub2">('.count($items).' item)</span></div>';
                echo '<div class="invgrid2">';
                foreach ($items as $it) {
                  $img = item_img($it['image']??'', $it['item_key']??$it['material']??'');
                  $qty = (int)($it['qty']??1);
                  $locked = (int)($it['locked']??0);
                  $displayName = $it['display_name'] ?? $it['item'] ?? '';
                  $material = $it['material'] ?? '';
                  $enchants = $it['enchants'] ?? '';
                  $damage = (int)($it['damage']??0); $maxDamage = (int)($it['max_damage']??0);
                  $durPct = ($maxDamage > 0) ? max(0, min(100, round((1 - $damage/$maxDamage)*100))) : null;
                  $tooltip = ($displayName?:$material).($material?' ('.$material.')':'').($qty>1?" ×$qty":'');
                  $classExtras = ($locked===1?' inv-locked':'').($locked===2?' inv-pending':'').($enchants?' inv-ench':'');
                  echo '<div class="inv2'.$classExtras.'" title="'.h($tooltip).'">';
                  echo '<img class="ico" src="'.h($img).'" onerror="this.onerror=null;this.src=\'?img=doge\'" alt="">';
                  if ($qty > 1) echo '<span class="qy">'.$qty.'</span>';
                  if ($locked === 1) echo '<span class="lock-badge" title="Đang rao bán/đấu giá">🔒</span>';
                  if ($locked === 2) echo '<span class="lock-badge2" title="Chờ giao khi vào game">⏳</span>';
                  if ($durPct !== null && $durPct < 100) {
                    $durColor = $durPct > 50 ? '#7dd47f' : ($durPct > 20 ? '#f2b631' : '#e0584a');
                    echo '<div class="dur"><div class="dur-bar" style="width:'.$durPct.'%;background:'.$durColor.'"></div></div>';
                  }
                  if ($displayName && $displayName !== $material) {
                    echo '<span class="inm">'.h(preg_replace('/§[0-9a-frlokmn]/i','',$displayName)).'</span>';
                  } else if ($material) {
                    $shortMat = preg_replace('/_/',' ',strtolower($material));
                    echo '<span class="inm">'.h($shortMat).'</span>';
                  }
                  echo '<form method="post" action="?p=admin&tab=users&euser='.urlencode($eue['realname']).'" onsubmit="return confirm(\'Xoá item này khỏi kho?\')" class="inv-del"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="inv_delete"><input type="hidden" name="id" value="'.(int)$it['id'].'"><input type="hidden" name="username" value="'.h($eue['realname']).'"><button type="submit" title="Xoá">×</button></form>';
                  echo '</div>';
                }
                echo '</div></div>';
              }
              echo '</div>';
            }
          } ?>

          <p class="sub2" style="margin-top:14px;font-size:.78rem">💡 Cần cấp item cho player? Dùng <a href="?p=admin&tab=console" style="color:var(--green);font-weight:700">Server Console</a> với lệnh <code>give &lt;player&gt; minecraft:diamond 64</code> — an toàn hơn và item có NBT chính xác.</p>
        </div>
      <?php } else { ?>
      <!-- ===== Search + filter bar (chỉ hiện khi KHÔNG xem user detail) ===== -->
      <form method="get" action="?p=admin" class="userfilter">
        <input type="hidden" name="p" value="admin"><input type="hidden" name="tab" value="users">
        <input type="search" name="q" value="<?=h($q)?>" placeholder="🔍 Tìm theo tên, email, IGN..." autocomplete="off" autofocus>
        <select name="status" onchange="this.form.submit()">
          <option value="">— Tất cả trạng thái —</option>
          <option value="verified"<?=$fStatus==='verified'?' selected':''?>>✓ Đã verify</option>
          <option value="unverified"<?=$fStatus==='unverified'?' selected':''?>>○ Chưa verify</option>
          <option value="banned"<?=$fStatus==='banned'?' selected':''?>>🚫 Bị ban</option>
          <option value="admin"<?=$fStatus==='admin'?' selected':''?>>🔑 Admin</option>
        </select>
        <select name="sort" onchange="this.form.submit()">
          <option value="newest"<?=$fSort==='newest'?' selected':''?>>Đăng ký mới nhất</option>
          <option value="oldest"<?=$fSort==='oldest'?' selected':''?>>Đăng ký cũ nhất</option>
          <option value="lastlogin"<?=$fSort==='lastlogin'?' selected':''?>>Login game gần nhất</option>
          <option value="az"<?=$fSort==='az'?' selected':''?>>Tên A → Z</option>
        </select>
        <button class="btn btn-green" type="submit">Lọc</button>
        <?php if($q!==''||$fStatus!==''||$fSort!=='newest') echo '<a class="btn btn-ghost" href="?p=admin&tab=users">Reset</a>'; ?>
      </form>

      <div class="card" style="padding:0;overflow:hidden;margin-top:14px">
        <div class="ahd" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <span>Tài khoản người chơi</span>
          <span class="st in_progress" style="margin:0"><?=number_format($totalU)?> match<?= $totalU!==count($users)?' · hiển thị '.($offset+1).'-'.($offset+count($users)):'' ?></span>
          <?php if($q!=='') echo '<span class="sub2">tìm: "<b>'.h($q).'</b>"</span>'; ?>
        </div>
        <div style="overflow-x:auto"><table class="tbl tbl-user">
          <thead><tr>
            <th style="width:48px"></th>
            <th>Tài khoản</th>
            <th style="width:130px">Role</th>
            <th style="width:130px">Trạng thái</th>
            <th style="width:150px">Last login game</th>
            <th class="tr" style="width:180px">Hành động</th>
          </tr></thead>
          <tbody>
          <?php if(!$users) echo '<tr><td colspan="6" class="cmid">'.($q!==''||$fStatus!==''?'Không tìm thấy user nào khớp với bộ lọc.':'Chưa có tài khoản.').'</td></tr>';
            else foreach($users as $u2){
              $isBan=!empty($u2['banned']); $owner=is_owner($u2['uname']);
              $admRole = $u2['adm_role'] ?? ''; $hasConsoleU = !empty($u2['has_console']);
              $effRole = $owner ? 'supervisor' : $admRole;
              // Role badge
              $rb = ['supervisor'=>['SUPERVISOR','#ff8db0'],'admin'=>['ADMIN','#7dd47f'],'support'=>['SUPPORT','#8fb4ff']];
              if (isset($rb[$effRole])) {
                $role = '<span class="st" style="background:'.$rb[$effRole][1].'22;color:'.$rb[$effRole][1].';font-weight:800">'.$rb[$effRole][0].'</span>';
                if ($hasConsoleU && $effRole !== 'supervisor') $role .= ' <span class="st" style="background:rgba(125,212,127,.18);color:#7dd47f;font-size:.65rem;margin-left:2px">Console</span>';
              } else $role = '<span class="st" style="background:rgba(255,255,255,.05);color:var(--muted)">Player</span>';
              // Status badge
              $stt = $isBan ? '<span class="st rejected">🚫 Bị ban</span>' : ($u2['verified']?'<span class="st success">✓ Verified</span>':'<span class="st pending">○ Chưa</span>');
              $banForm = $owner ? '<span class="sub2">—</span>' : ($isBan
                  ? '<form method="post" action="?p=admin&tab=users" style="display:inline"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="user_unban"><input type="hidden" name="username" value="'.h($u2['uname']).'"><button class="btn btn-green btn-sm" type="submit">Gỡ ban</button></form>'
                  : '<form method="post" action="?p=admin&tab=users" style="display:inline" onsubmit="this.querySelector(\'[name=reason]\').value=prompt(\'Lý do ban '.h($u2['uname']).' (tuỳ chọn):\',\'\')||\'\';return confirm(\'Ban '.h($u2['uname']).'?\')"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="user_ban"><input type="hidden" name="username" value="'.h($u2['uname']).'"><input type="hidden" name="reason" value=""><button class="btn btn-sm bdel" type="submit">Ban</button></form>');
              $avatar = h($CFG['skin_api']).'/avatar/'.urlencode($u2['uname']).'/36';
              $regd = $u2['regdate'] > 0 ? date('d/m/Y',(int)($u2['regdate']/1000)) : '—';
              $lastLogin = (int)($u2['authme_lastlogin'] ?? 0);
              $lastTxt = $lastLogin > 0 ? date('d/m H:i',(int)($lastLogin/1000)) : '<span class="sub2">Chưa từng</span>';
              $lastIp = $u2['authme_ip'] ?? '';
              echo '<tr>'
                .'<td><img src="'.$avatar.'" data-skin-user="'.h($u2['uname']).'" data-skin-size="36" onerror="skinFallback(this)" style="width:36px;height:36px;border-radius:7px;background:#1a1d22" alt=""></td>'
                .'<td><b>'.h($u2['uname']).'</b><div class="sub2">Đăng ký: '.$regd.'</div></td>'
                .'<td>'.$role.'</td>'
                .'<td>'.$stt.($isBan && $u2['ban_reason']?'<div class="sub2" style="max-width:180px">'.h($u2['ban_reason']).'</div>':'').'</td>'
                .'<td class="sub2">'.$lastTxt.($lastIp?'<div class="sub2" style="font-size:.7rem"><code>'.h($lastIp).'</code></div>':'').'</td>'
                .'<td class="tr" style="white-space:nowrap">'
                  .'<a class="btn btn-ghost btn-sm" href="?p=admin&tab=users&euser='.urlencode($u2['uname']).'">Xem</a> '
                  .$banForm
                  .' <form method="post" action="?p=admin&tab=users" style="display:inline" onsubmit="return confirm(\'Xoá tài khoản '.h($u2['uname']).'?\')"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="user_delete"><input type="hidden" name="username" value="'.h($u2['uname']).'"><button class="btn btn-sm bdel">Xoá</button></form>'
                .'</td></tr>';
            } ?>
          </tbody></table></div>
        <?php
          // Pagination
          if ($totalU > $perPage) {
            $totalPages = (int)ceil($totalU / $perPage);
            $curPage    = (int)floor($offset / $perPage) + 1;
            echo '<div class="userpager">';
            if ($curPage > 1) echo '<a class="btn btn-ghost btn-sm" href="'.h($mkUrl(['offset'=>($curPage-2)*$perPage])).'">← Trước</a>';
            echo '<span class="sub2" style="padding:0 12px">Trang '.$curPage.' / '.$totalPages.'</span>';
            if ($curPage < $totalPages) echo '<a class="btn btn-ghost btn-sm" href="'.h($mkUrl(['offset'=>$curPage*$perPage])).'">Sau →</a>';
            echo '</div>';
          }
        ?>
      </div>
      <?php } /* end else (not viewing user detail) */ ?>

    <?php } elseif($tab==='topups'){
      $txs=[]; try{ $txs=db()->query("SELECT * FROM web_topups ORDER BY id DESC LIMIT 100")->fetchAll(); }catch(Exception $e){} ?>
      <div class="card" style="padding:0;overflow:hidden">
        <div class="ahd">Giao dịch nạp — duyệt để cộng <?=h($CFG['doge_label']??'Dogecoin')?> vào ví</div>
        <div style="overflow-x:auto"><table class="tbl"><thead><tr><th>#</th><th>Thời gian</th><th>Người chơi</th><th>Số tiền</th><th>Kim Cương</th><th>Trạng thái</th><th></th></tr></thead><tbody>
        <?php if(!$txs) echo '<tr><td colspan="7" class="cmid">Chưa có giao dịch.</td></tr>';
          else foreach($txs as $tx){ $sl=['success'=>'Thành công','rejected'=>'Từ chối','pending'=>'Chờ duyệt'][$tx['status']]??$tx['status']; $sc=$tx['status']==='success'?'success':($tx['status']==='rejected'?'pending':'pending');
            echo '<tr><td class="sub2">'.$tx['id'].'</td><td class="sub2">'.date('d/m H:i',(int)($tx['created']/1000)).'</td><td style="font-weight:700">'.h($tx['username']).'</td><td>'.h($tx['package']).'</td><td class="cdia">'.number_format($tx['diamonds'],0,',','.').'</td><td><span class="st '.$sc.'">'.$sl.'</span></td><td class="tr">';
            if($tx['status']!=='success') echo '<form method="post" action="?p=admin&tab=topups" style="display:inline"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="topup_set"><input type="hidden" name="id" value="'.$tx['id'].'"><input type="hidden" name="status" value="success"><button class="btn btn-green btn-sm" type="submit">Duyệt</button></form> ';
            if($tx['status']==='pending') echo '<form method="post" action="?p=admin&tab=topups" style="display:inline"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="topup_set"><input type="hidden" name="id" value="'.$tx['id'].'"><input type="hidden" name="status" value="rejected"><button class="btn btn-sm bdel" type="submit">Từ chối</button></form>';
            echo '</td></tr>';
          } ?>
        </tbody></table></div>
      </div>

    <?php } elseif($tab==='shop'){
      $es=null; if(!empty($_GET['eshop'])){ try{ $st=db()->prepare("SELECT * FROM web_shop WHERE id=?"); $st->execute([(int)$_GET['eshop']]); $es=$st->fetch(); }catch(Exception $e){} }
      $shop=[]; try{ $shop=db()->query("SELECT * FROM web_shop ORDER BY category,sort,id")->fetchAll(); }catch(Exception $e){} ?>
      <div class="admin-grid">
        <div class="card" style="padding:26px">
          <h3 class="ah"><?= $es?'Sửa sản phẩm':'Thêm sản phẩm' ?></h3>
          <form method="post" action="?p=admin&tab=shop">
            <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="shop_save"><input type="hidden" name="id" value="<?= $es?(int)$es['id']:0 ?>">
            <div class="field"><label>Loại</label><select name="category"><option value="item"<?= (!$es||$es['category']==='item')?' selected':'' ?>>Vật phẩm</option><option value="rank"<?= ($es&&$es['category']==='rank')?' selected':'' ?>>Rank</option></select></div>
            <div class="field"><label>Tên</label><input name="name" value="<?= $es?h($es['name']):'' ?>" required></div>
            <div class="g2"><div class="field"><label>Giá (Kim Cương)</label><input name="price" type="number" value="<?= $es?(int)$es['price']:0 ?>"></div><div class="field"><label>Màu</label><input name="color" type="text" value="<?= $es?h($es['color']):'#56cfd6' ?>"></div></div>
            <div class="field"><label>Đặc quyền (mỗi dòng 1 ý — dùng cho Rank)</label><textarea name="detail"><?= $es?h($es['detail']):'' ?></textarea></div>
            <button class="btn btn-green btn-block" type="submit"><?= $es?'Cập nhật':'Thêm' ?></button>
            <?php if($es) echo '<a class="btn btn-ghost btn-block" href="?p=admin&tab=shop" style="margin-top:10px">Huỷ</a>'; ?>
          </form>
        </div>
        <div class="card" style="padding:0;overflow:hidden">
          <div class="ahd">Sản phẩm trong cửa hàng (<?=count($shop)?>)</div>
          <div style="overflow-x:auto"><table class="tbl"><tbody>
          <?php foreach($shop as $sp) echo '<tr><td><span class="th" style="background:'.h($sp['color']).';width:20px;height:20px;display:inline-block;border-radius:5px;vertical-align:middle;margin-right:8px"></span><b>'.h($sp['name']).'</b> <span class="sub2">('.($sp['category']==='rank'?'Rank':'Vật phẩm').')</span></td><td class="cdia tr">'.number_format($sp['price'],0,',','.').' KC</td><td class="tr"><a class="btn btn-ghost btn-sm" href="?p=admin&tab=shop&eshop='.(int)$sp['id'].'">Sửa</a> <form method="post" action="?p=admin&tab=shop" style="display:inline" onsubmit="return confirm(\'Xoá?\')"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="shop_delete"><input type="hidden" name="id" value="'.(int)$sp['id'].'"><button class="btn btn-sm bdel">Xoá</button></form></td></tr>'; ?>
          </tbody></table></div>
        </div>
      </div>

    <?php } elseif($tab==='auc'){
      auc_settle_due();
      $ea=null; if(!empty($_GET['eauc'])){ try{ $st=db()->prepare("SELECT * FROM web_auctions WHERE id=?"); $st->execute([(int)$_GET['eauc']]); $ea=$st->fetch(); }catch(Exception $e){} }
      $aucs=[]; try{ $aucs=db()->query("SELECT * FROM web_auctions ORDER BY (status='active') DESC, end_at DESC LIMIT 200")->fetchAll(); }catch(Exception $e){}
      $hleft = $ea ? max(0, round(($ea['end_at']-ms())/3600000,1)) : 24; ?>
      <div class="card" style="padding:26px;margin-bottom:20px">
        <h3 class="ah"><?= $ea?'Sửa phiên đấu giá':'Tạo phiên đấu giá (admin)' ?></h3>
        <form method="post" action="?p=admin&tab=auc">
          <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="auc_save"><input type="hidden" name="id" value="<?= $ea?(int)$ea['id']:0 ?>">
          <div class="g2">
            <div class="field"><label>Tên vật phẩm</label><input name="item" value="<?= $ea?h($ea['item']):'' ?>" required></div>
            <div class="field"><label>Icon (mã item)</label><input name="item_key" value="<?= $ea?h($ea['item_key']):'diamond_sword' ?>" placeholder="diamond_sword"></div>
            <div class="field"><label>Người bán</label><input name="seller" value="<?= $ea?h($ea['seller']):'Admin' ?>"></div>
            <div class="field"><label>Giá khởi điểm (<?=h($CFG['doge_label']??'Dogecoin')?>)</label><input name="price" type="number" value="<?= $ea?(int)$ea['price']:100 ?>"></div>
            <div class="field"><label>Thời lượng (giờ)</label><input name="hours" type="number" step="0.5" value="<?=$hleft?>"></div>
            <div class="field"><label>Màu nhãn</label><input name="color" type="color" value="<?= $ea?h($ea['color']):'#56cfd6' ?>" style="height:46px;padding:4px;cursor:pointer"></div>
          </div>
          <div style="display:flex;gap:10px"><button class="btn btn-green" type="submit"><?= $ea?'Cập nhật':'Tạo phiên' ?></button><?php if($ea) echo '<a class="btn btn-ghost" href="?p=admin&tab=auc">Huỷ</a>'; ?></div>
        </form>
      </div>
      <div class="card" style="padding:0;overflow:hidden">
        <div class="ahd">Phiên đấu giá (<?=count($aucs)?>)</div>
        <div style="overflow-x:auto"><table class="tbl"><thead><tr><th>Vật phẩm</th><th>Giá hiện tại</th><th>Người giữ giá</th><th>Kết thúc</th><th>Trạng thái</th><th></th></tr></thead><tbody>
        <?php if(!$aucs) echo '<tr><td colspan="6" class="cmid">Chưa có phiên đấu giá nào.</td></tr>';
          else foreach($aucs as $au){ $active=$au['status']==='active' && ($au['end_at']-ms())>0;
            $stmap=['active'=>['success','Đang diễn ra'],'sold'=>['success','Đã bán'],'expired'=>['rejected','Hết hạn'],'cancelled'=>['rejected','Đã huỷ']];
            $stv=$stmap[$au['status']]??['','?'];
            echo '<tr><td><img class="iimg" style="width:30px;height:30px;vertical-align:middle;margin-right:8px;padding:3px" src="'.h(item_icon($au['item_key'])).'" onerror="this.style.display=\'none\'"><b>'.h($au['item']).'</b><div class="sub2">'.h($au['seller']).'</div></td>'
              .'<td style="color:#f7c948;font-weight:700">Ð'.number_format($au['price'],0,',','.').'</td>'
              .'<td class="sub2">'.($au['top_bidder']!==''?h($au['top_bidder']):'—').' <span class="sub2">('.(int)$au['bid_count'].' lượt)</span></td>'
              .'<td class="sub2">'.($active?'<span class="tm" data-end="'.(int)$au['end_at'].'">⏱</span>':date('d/m/Y H:i',(int)($au['end_at']/1000))).'</td>'
              .'<td><span class="st '.$stv[0].'">'.$stv[1].'</span></td>'
              .'<td class="tr" style="white-space:nowrap">'.($active?'<form method="post" action="?p=admin&tab=auc" style="display:inline" onsubmit="return confirm(\'Huỷ & hoàn cọc?\')"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="auc_cancel"><input type="hidden" name="admin" value="1"><input type="hidden" name="id" value="'.(int)$au['id'].'"><button class="btn btn-ghost btn-sm">Huỷ</button></form> ':'').'<form method="post" action="?p=admin&tab=auc" style="display:inline" onsubmit="return confirm(\'Xoá hẳn phiên này?\')"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="auc_delete"><input type="hidden" name="id" value="'.(int)$au['id'].'"><button class="btn btn-sm bdel">Xoá</button></form></td></tr>';
          } ?>
        </tbody></table></div>
      </div>

    <?php } elseif($tab==='ranks'){
      $er=null; if(!empty($_GET['erank'])){ try{ $st=db()->prepare("SELECT * FROM web_ranks WHERE id=?"); $st->execute([(int)$_GET['erank']]); $er=$st->fetch(); }catch(Exception $e){} }
      $all=[]; $sdo=[]; try{ $all=db()->query("SELECT * FROM web_ranks WHERE scope='all' ORDER BY sort,id")->fetchAll(); $sdo=db()->query("SELECT * FROM web_ranks WHERE scope='sdo' ORDER BY sort,id")->fetchAll(); }catch(Exception $e){} ?>
      <div class="card" style="padding:26px;margin-bottom:20px">
        <h3 class="ah"><?= $er?('Sửa rank: '.h($er['name'])):'Thêm rank mới' ?></h3>
        <form method="post" action="?p=admin&tab=ranks">
          <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="rank_save"><input type="hidden" name="id" value="<?= $er?(int)$er['id']:0 ?>">
          <div class="g2">
            <div class="field"><label>Phạm vi</label><select name="scope">
              <option value="all"<?= ($er&&$er['scope']==='all')||!$er?' selected':'' ?>>🌐 Tất cả server</option>
              <option value="sdo"<?= $er&&$er['scope']==='sdo'?' selected':'' ?>>⚔️ Sword Dark Online</option>
            </select></div>
            <div class="field"><label>Tên rank</label><input name="name" value="<?= $er?h($er['name']):'' ?>" required></div>
            <div class="field"><label>Giá (<?=h($CFG['doge_label']??'Dogecoin')?>)</label><input name="price" type="number" min="0" value="<?= $er?(int)$er['price']:1000 ?>"></div>
            <div class="field"><label>Màu chữ</label><input name="color" type="color" value="<?= $er?h($er['color']):'#f2b631' ?>" style="height:46px;padding:4px;cursor:pointer"></div>
            <div class="field"><label>Thứ tự</label><input name="sort" type="number" value="<?= $er?(int)$er['sort']:0 ?>"><div class="sub2" style="margin-top:4px;font-size:.78rem">Số càng nhỏ càng hiện <b>trước</b> trong trang Cửa hàng (sắp xếp <code>ORDER BY sort,id</code>). VD: VIP=1, VIP+=2, MVP=3. Bằng nhau thì rank tạo trước được hiện trước.</div></div>
            <div class="field"><label>Kích hoạt</label><select name="active"><option value="1"<?= !$er||$er['active']?' selected':'' ?>>Có</option><option value="0"<?= $er&&!$er['active']?' selected':'' ?>>Tạm ẩn</option></select></div>
          </div>
          <div class="field"><label>Mô tả / Quyền lợi (mỗi dòng 1 dòng hiển thị)</label><textarea name="description" rows="4" placeholder="Tiền tố [VIP] màu xanh&#10;/kit vip mỗi ngày&#10;+10% Dogecoin khi farm"><?= $er?h($er['description']):'' ?></textarea></div>
          <div class="field"><label>Lệnh chạy khi mua (mỗi dòng 1 lệnh) — biến: <code>{player}</code> <code>{uuid}</code> <code>{rank}</code></label>
            <textarea name="commands" rows="4" placeholder="lp user {player} parent addtemp vip 30d&#10;bc &amp;6{player} &amp;fvừa mua rank VIP&#10;give {player} minecraft:diamond 5"><?= $er?h($er['commands']):'' ?></textarea>
            <div class="sub2" style="margin-top:6px">Các lệnh sẽ được đưa vào hàng đợi <b>web_rcon_queue</b> để plugin/RCON chạy in-game.</div>
          </div>
          <div style="display:flex;gap:10px"><button class="btn btn-green" type="submit"><?= $er?'Cập nhật rank':'Thêm rank' ?></button><?php if($er) echo '<a class="btn btn-ghost" href="?p=admin&tab=ranks">Huỷ</a>'; ?></div>
        </form>
      </div>
      <?php $renderRanks=function($list,$title) use($CFG,$CSRF){
        echo '<div class="card" style="padding:0;overflow:hidden;margin-bottom:20px"><div class="ahd">'.$title.' ('.count($list).')</div>';
        echo '<div style="overflow-x:auto"><table class="tbl"><thead><tr><th>Rank</th><th>Giá</th><th>Lệnh</th><th>Trạng thái</th><th></th></tr></thead><tbody>';
        if(!$list) echo '<tr><td colspan="5" class="cmid">Chưa có rank.</td></tr>';
        foreach($list as $r){ $nc=count(array_filter(array_map('trim',preg_split('/\r\n|\r|\n/',(string)$r['commands']))));
          echo '<tr><td><b style="color:'.h($r['color']).'">'.h($r['name']).'</b></td><td style="color:#f7c948;font-weight:700">Ð'.number_format($r['price'],0,',','.').'</td>'
            .'<td class="sub2">'.$nc.' lệnh</td><td><span class="st '.($r['active']?'success':'rejected').'">'.($r['active']?'Hiện':'Ẩn').'</span></td>'
            .'<td class="tr" style="white-space:nowrap"><a class="btn btn-ghost btn-sm" href="?p=admin&tab=ranks&erank='.(int)$r['id'].'">Sửa</a> '
            .'<form method="post" action="?p=admin&tab=ranks" style="display:inline" onsubmit="return confirm(\'Xoá rank?\')"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="rank_delete"><input type="hidden" name="id" value="'.(int)$r['id'].'"><button class="btn btn-sm bdel">Xoá</button></form></td></tr>';
        }
        echo '</tbody></table></div></div>';
      };
      $renderRanks($all,'🌐 Rank — Tất cả server'); $renderRanks($sdo,'⚔️ Rank — Sword Dark Online'); ?>

    <?php } elseif($tab==='market'){
      $ms=[]; try{ $ms=db()->query("SELECT * FROM web_market ORDER BY (status='active') DESC, created DESC LIMIT 200")->fetchAll(); }catch(Exception $e){}
      $fp=(int)($CFG['market_fee_percent']??5); ?>
      <div class="card" style="padding:22px;margin-bottom:18px">
        <h3 class="ah">Chợ Trời — phí hiện tại: <?=$fp?>%</h3>
        <div class="sub2">Phí % chỉnh trong khối <code>$CFG['market_fee_percent']</code> ở đầu file. Admin có thể gỡ tin vi phạm (vật phẩm sẽ trả về kho người bán).</div>
      </div>
      <div class="card" style="padding:0;overflow:hidden">
        <div class="ahd">Tin đăng (<?=count($ms)?>)</div>
        <div style="overflow-x:auto"><table class="tbl"><thead><tr><th>Vật phẩm</th><th>Người bán</th><th>Giá</th><th>Trạng thái</th><th></th></tr></thead><tbody>
        <?php if(!$ms) echo '<tr><td colspan="5" class="cmid">Chưa có tin đăng.</td></tr>';
          foreach($ms as $m){ $stmap=['active'=>['success','Đang bán'],'sold'=>['success','Đã bán → '.h($m['buyer'])],'cancelled'=>['rejected','Đã gỡ']]; $stv=$stmap[$m['status']]??['','?'];
            echo '<tr><td><img class="iimg" style="width:30px;height:30px;vertical-align:middle;margin-right:8px;padding:3px" src="'.h(item_icon($m['item_key'])).'" onerror="this.style.display=\'none\'"><b>'.h($m['item_name']).'</b> <span class="sub2">×'.(int)$m['qty'].'</span></td>'
              .'<td class="sub2">'.h($m['seller']).'</td><td style="color:#f7c948;font-weight:700">Ð'.number_format($m['price'],0,',','.').'</td>'
              .'<td><span class="st '.$stv[0].'">'.$stv[1].'</span></td>'
              .'<td class="tr">'.($m['status']==='active'?'<form method="post" action="?p=admin&tab=market" style="display:inline" onsubmit="return confirm(\'Gỡ tin & trả đồ về kho?\')"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="market_cancel"><input type="hidden" name="admin" value="1"><input type="hidden" name="id" value="'.(int)$m['id'].'"><button class="btn btn-sm bdel">Gỡ tin</button></form>':'—').'</td></tr>';
          } ?>
        </tbody></table></div>
      </div>

    <?php } elseif($tab==='tickets'){
      $all=[]; try{ $all=db()->query("SELECT t.*, (SELECT COUNT(*) FROM web_ticket_replies r WHERE r.ticket_id=t.id) rc FROM web_tickets t ORDER BY (status='closed'), updated DESC")->fetchAll(); }catch(Exception $e){}
      $sl=['open'=>'Đang mở','in_progress'=>'Đang xử lý','closed'=>'Đã đóng']; ?>
      <div class="card" style="padding:0;overflow:hidden">
        <div class="ahd">Hộp ticket — <?=count($all)?> ticket (<?=open_tickets()?> chưa đóng)</div>
        <?php if(!$all) echo '<div class="cmid">Chưa có ticket nào.</div>';
          else foreach($all as $t){ $srv = !empty($t['server']) ? ' · '.h($t['server']) : '';
            echo '<a class="tk" href="?p=ticket&id='.(int)$t['id'].'"><span class="tno">'.h($t['code']?:ticket_code($t['id'])).'</span><div class="ti"><div class="ts">'.h($t['subject']).'</div><div class="tmeta">'.h($t['username']).' · '.h($t['category']).$srv.' · '.(int)$t['rc'].' phản hồi · '.date('d/m H:i',(int)($t['updated']/1000)).($t['assignee']?' · xử lý: '.h($t['assignee']):'').'</div></div><span class="tst '.h($t['status']).'">'.($sl[$t['status']]??$t['status']).'</span></a>';
          } ?>
      </div>

    <?php } elseif($tab==='announce'){
      $hist=[]; try{ $hist=db()->query("SELECT * FROM web_announce ORDER BY id DESC LIMIT 30")->fetchAll(); }catch(Exception $e){}
      $cur=active_announce(); $lvls=['info'=>'Thông tin (xanh)','warn'=>'Cảnh báo (vàng)','danger'=>'Khẩn cấp (đỏ)']; ?>
      <div class="admin-grid">
        <div class="card" style="padding:26px">
          <h3 class="ah">Đăng thông báo khẩn</h3>
          <?php if($cur) { ?>
            <div class="flash ok" style="margin-bottom:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:space-between">
              <span><b>Đang hiển thị:</b> "<?=h($cur['message'])?>"</span>
              <form method="post" action="?p=admin&tab=announce" style="display:inline" onsubmit="return confirm('Tắt thông báo đang hiện trên web?')">
                <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="announce_off"><input type="hidden" name="id" value="<?=(int)$cur['id']?>">
                <button class="btn btn-sm bdel" type="submit" style="background:var(--red);color:#fff;font-weight:700">✕ Tắt ngay</button>
              </form>
            </div>
          <?php } ?>
          <form method="post" action="?p=admin&tab=announce">
            <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="announce_save">
            <div class="field"><label>Mức độ</label><select name="level"><?php foreach($lvls as $k=>$v) echo '<option value="'.$k.'">'.$v.'</option>'; ?></select></div>
            <div class="field"><label>Nội dung (1 dòng, hiện trên mọi trang)</label><input name="message" maxlength="200" placeholder="VD: Server bảo trì 22:00 - 23:00 hôm nay" required></div>
            <div class="field"><label>Thời hạn (giờ — để 0 = không tự hết hạn)</label><input name="hours" type="number" step="0.5" value="2"></div>
            <button class="btn btn-green btn-block" type="submit">Đăng + gửi Discord</button>
          </form>
          <p class="sub2" style="margin-top:12px"><?= $CFG['discord_webhook']?'Sẽ tự gửi sang Discord.':'Chưa cấu hình Discord webhook trong $CFG (chỉ hiện trên web).' ?></p>
        </div>
        <div class="card" style="padding:0;overflow:hidden">
          <div class="ahd">Lịch sử thông báo</div>
          <div style="overflow-x:auto"><table class="tbl"><tbody>
          <?php if(!$hist) echo '<tr><td class="cmid">Chưa có thông báo.</td></tr>';
            else foreach($hist as $an){ $on=$an['active'] && (!$an['expires']||$an['expires']>ms());
              echo '<tr><td><span class="tst '.($an['level']==='danger'?'open':($an['level']==='info'?'in_progress':'open')).'" style="margin-bottom:6px">'.h($an['level']).'</span><div style="font-weight:600">'.h($an['message']).'</div><div class="sub2">'.h($an['author']).' · '.date('d/m H:i',(int)($an['created']/1000)).($an['expires']?' · hết hạn '.date('d/m H:i',(int)($an['expires']/1000)):'').'</div></td><td class="tr">'.($on?'<form method="post" action="?p=admin&tab=announce" style="display:inline"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="announce_off"><input type="hidden" name="id" value="'.(int)$an['id'].'"><button class="btn btn-sm bdel">Tắt</button></form>':'<span class="sub2">Đã tắt</span>').'</td></tr>';
            } ?>
          </tbody></table></div>
        </div>
      </div>

    <?php } elseif($tab==='console'){
      if(!can_console($user)){ echo '<div class="empty">Bạn không có quyền vào Console.</div>'; }
      else {
        $srvStatus = function_exists('get_servers_status') ? get_servers_status(false) : [];
        $recent = []; try{ $recent = db()->query("SELECT * FROM web_rcon_queue ORDER BY id DESC LIMIT 50")->fetchAll(); }catch(Exception $e){}
    ?>
      <?php
        // Build server list: merge config modes (named) + heartbeat-only servers (mới, chỉ ID)
        $consoleServers = []; // id => display label
        foreach ($srvStatus as $s) {
          if (!empty($s['home_show'])) {
            $consoleServers[$s['id']] = [
              'label' => $s['name'] . ($s['online'] ? '' : ' (offline)'),
              'online' => !empty($s['online']),
            ];
          }
        }
        // Thêm server từ heartbeat KHÔNG có trong config — chỉ hiện ID, đang online
        try {
          $hb = db()->query("SELECT server_id, last_beat FROM web_sync_heartbeat WHERE last_beat > " . (ms() - 30000))->fetchAll();
          foreach ($hb as $r) {
            $hbId = $r['server_id'];
            if (!isset($consoleServers[$hbId])) {
              $consoleServers[$hbId] = ['label' => $hbId . ' (chưa có trong config)', 'online' => true];
            }
          }
        } catch(Exception $e){}
      ?>
      <div class="ahd-row" style="margin-bottom:14px"><p style="color:var(--muted);margin:0">Live console + gõ lệnh trực tiếp xuống server. Lệnh chạy ngay, output stream live phía trên. ↑/↓ duyệt history.</p></div>

      <!-- LIVE CONSOLE + COMMAND INPUT INTEGRATED -->
      <div class="card" style="padding:0;overflow:hidden;margin-bottom:16px">
        <div class="ahd console-hd">
          <span>Live Console</span>
          <select id="csrvFilter" style="margin-left:12px;font-size:.85rem">
            <?php foreach($consoleServers as $sid => $info) echo '<option value="'.h($sid).'"'.(!$info['online']?' disabled':'').'>'.h($info['label']).'</option>'; ?>
          </select>
          <span id="cstatus" class="cstatus off" style="margin-left:auto">⬤ Đang kết nối...</span>
          <button type="button" class="btn btn-ghost btn-sm" onclick="consoleClear()" style="margin-left:8px">Clear</button>
          <label class="cscroll"><input type="checkbox" id="cautoscroll" checked> Auto-scroll</label>
        </div>
        <div class="console-term" id="cterm"><div class="cline cinfo">Đang kết nối SSE...</div></div>
        <div class="console-input">
          <span class="ci-prompt">$</span>
          <input id="cinput" type="text" maxlength="240" placeholder="gõ lệnh: say Hello · gamemode creative TheMouseRanger · give Player diamond 64 ..." autocomplete="off" spellcheck="false">
          <button type="button" id="csend" onclick="sendCmd()">▶ Send</button>
        </div>
      </div>

      <!-- Log lệnh gần đây (50) — có output -->
      <div class="card" style="padding:0;overflow:hidden;margin-bottom:16px">
        <div class="ahd">Lệnh gần đây (<?=count($recent)?>)</div>
        <div style="overflow-x:auto;max-height:340px;overflow-y:auto"><table class="tbl">
          <thead><tr><th style="width:140px">Khi</th><th style="width:110px">Server</th><th>Lệnh</th><th>Bởi</th><th style="width:100px">Trạng thái</th><th>Kết quả</th></tr></thead>
          <tbody>
          <?php if(!$recent) echo '<tr><td colspan="6" class="cmid">Chưa có lệnh nào.</td></tr>';
            else foreach($recent as $rq){
              $stCls = ['done'=>'success','failed'=>'pending','pending'=>'pending','processing'=>'pending'][$rq['status']] ?? 'pending';
              echo '<tr>'
                .'<td class="sub2">'.date('d/m H:i:s',(int)($rq['created']/1000)).'</td>'
                .'<td><code>'.h($rq['server_id']?:'—').'</code></td>'
                .'<td style="font-family:monospace;font-size:.86rem">'.h($rq['command']).'</td>'
                .'<td>'.h($rq['requested_by']).'</td>'
                .'<td><span class="st '.$stCls.'">'.h($rq['status']).'</span></td>'
                .'<td class="sub2" style="font-family:monospace;font-size:.82rem;max-width:300px;word-break:break-word">'.h($rq['output']?:'—').'</td>'
                .'</tr>';
            } ?>
          </tbody></table></div>
      </div>

      <script>
      (function(){
        const term   = document.getElementById('cterm');
        const status = document.getElementById('cstatus');
        const srvSel = document.getElementById('csrvFilter');
        const auto   = document.getElementById('cautoscroll');
        const cinput = document.getElementById('cinput');
        const csend  = document.getElementById('csend');
        const CSRF   = <?=json_encode($CSRF)?>;
        let es = null, sinceId = 0;
        const MAX_LINES = 500;
        const history = []; let histIdx = -1;
        function pad(n){return String(n).padStart(2,'0')}
        function fmtTime(ts){const d=new Date(+ts); return pad(d.getHours())+':'+pad(d.getMinutes())+':'+pad(d.getSeconds())}
        function levelClass(lv){lv=(lv||'').toUpperCase(); if(lv==='SEVERE'||lv==='ERROR'||lv==='FATAL') return 'cerr'; if(lv==='WARN'||lv==='WARNING') return 'cwarn'; if(lv==='CHAT') return 'cchat'; if(lv==='COMMAND') return 'ccmd'; return 'cinfo';}
        function esc(s){const d=document.createElement('div'); d.textContent=s==null?'':String(s); return d.innerHTML;}
        function appendLine(o){
          const cls = levelClass(o.lv);
          const srv = o.srv?'<span class="csrv">['+esc(o.srv)+']</span>':'';
          const src = o.src?' <span class="csrc">'+esc(o.src)+'</span>':'';
          const div = document.createElement('div');
          div.className = 'cline ' + cls;
          div.innerHTML = '<span class="ctime">['+fmtTime(o.ts)+']</span> <span class="clv">'+esc(o.lv)+'</span> '+srv+src+' '+esc(o.msg);
          term.appendChild(div);
          while (term.children.length > MAX_LINES) term.removeChild(term.firstChild);
          if (auto.checked) term.scrollTop = term.scrollHeight;
        }
        function localLine(msg, cls){
          const div = document.createElement('div'); div.className='cline '+(cls||'cinfo');
          div.innerHTML='<span class="ctime">['+fmtTime(Date.now())+']</span> <span class="clv">WEB</span> '+esc(msg);
          term.appendChild(div); if(auto.checked) term.scrollTop=term.scrollHeight;
        }
        window.consoleClear = function(){ term.innerHTML=''; };
        function connect(){
          if(es){try{es.close()}catch(e){} es=null;}
          const srv = srvSel.value;
          if(!srv){ localLine('Không có server online.','cwarn'); return; }
          status.className='cstatus connecting'; status.textContent='⬤ Đang kết nối...';
          es = new EventSource('?p=sse_console&srv='+encodeURIComponent(srv)+'&since='+sinceId);
          es.addEventListener('open',()=>{status.className='cstatus on';status.textContent='⬤ '+srv;});
          es.addEventListener('log',ev=>{try{const o=JSON.parse(ev.data); sinceId=Math.max(sinceId,o.id); appendLine(o);}catch(e){}});
          es.addEventListener('cursor',ev=>{try{const o=JSON.parse(ev.data); if(o.since) sinceId=Math.max(sinceId,o.since);}catch(e){}});
          es.addEventListener('bye',()=>{setTimeout(connect,300);});
          es.onerror=()=>{status.className='cstatus off';status.textContent='⬤ Mất kết nối';if(es){try{es.close()}catch(e){}} es=null;setTimeout(connect,2000);};
        }
        srvSel.addEventListener('change',()=>{sinceId=0;term.innerHTML='';connect();});
        window.sendCmd = function(){
          const cmd = cinput.value.trim(); if(!cmd) return;
          const srv = srvSel.value;
          if(!srv){ localLine('Chọn server trước.','cerr'); return; }
          localLine('$ ['+srv+'] '+cmd, 'ccmd');
          history.unshift(cmd); if(history.length>50) history.pop(); histIdx=-1;
          cinput.value=''; csend.disabled=true;
          const fd = new FormData();
          fd.append('csrf',CSRF); fd.append('act','rcon_exec');
          fd.append('server_id',srv); fd.append('command',cmd); fd.append('ajax','1');
          fetch('?p=admin&tab=console',{method:'POST',body:fd,headers:{'X-Requested-With':'fetch'}})
            .then(r=>r.json()).then(j=>{
              if(j && j.ok) localLine('✓ '+(j.msg||'Đã gửi'),'cinfo');
              else localLine('✗ '+(j&&j.msg?j.msg:'Lỗi'),'cerr');
            }).catch(e=>localLine('✗ Network error: '+e.message,'cerr'))
            .finally(()=>{csend.disabled=false;cinput.focus();});
        };
        cinput.addEventListener('keydown',ev=>{
          if(ev.key==='Enter'){ ev.preventDefault(); sendCmd(); }
          else if(ev.key==='ArrowUp'){ if(histIdx<history.length-1){histIdx++;cinput.value=history[histIdx];setTimeout(()=>cinput.selectionStart=cinput.value.length,0);} ev.preventDefault(); }
          else if(ev.key==='ArrowDown'){ if(histIdx>0){histIdx--;cinput.value=history[histIdx];} else {histIdx=-1;cinput.value='';} ev.preventDefault(); }
        });
        connect();
      })();
      </script>
    <?php } ?>

    <?php } elseif($tab==='logs'){
      $who=$_GET['admin']??''; $logs=[];
      try{ if($who!==''){ $st=db()->prepare("SELECT * FROM web_admin_log WHERE admin=? ORDER BY id DESC LIMIT 200"); $st->execute([$who]); $logs=$st->fetchAll(); } else $logs=db()->query("SELECT * FROM web_admin_log ORDER BY id DESC LIMIT 200")->fetchAll(); }catch(Exception $e){}
      $admins2=[]; try{ $admins2=db()->query("SELECT DISTINCT admin FROM web_admin_log ORDER BY admin")->fetchAll(PDO::FETCH_COLUMN); }catch(Exception $e){} ?>
      <div class="card" style="padding:0;overflow:hidden">
        <div class="ahd" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
          <span>Nhật ký hoạt động Admin (<?=count($logs)?>)</span>
          <form method="get" style="display:flex;gap:8px;align-items:center"><input type="hidden" name="p" value="admin"><input type="hidden" name="tab" value="logs">
            <select name="admin" onchange="this.form.submit()" style="padding:7px 11px;background:rgba(0,0,0,.35);border:1px solid var(--line-2);border-radius:9px;color:var(--ink);font-family:inherit"><option value="">— Tất cả admin —</option><?php foreach($admins2 as $a2) echo '<option value="'.h($a2).'"'.($who===$a2?' selected':'').'>'.h($a2).'</option>'; ?></select>
          </form>
        </div>
        <?php if(!$logs) echo '<div class="cmid">Chưa có hoạt động nào.</div>';
          else { echo '<div style="padding:6px 0">'; foreach($logs as $lg) echo '<div class="logrow"><img src="'.h($CFG['skin_api']).'/avatar/'.urlencode($lg['admin']).'/28" onerror="this.onerror=null;this.src=\'?img=doge\'" alt=""><div class="lgb"><span class="lga">'.h($lg['admin']).'</span> <span class="lgac">'.h($lg['action']).'</span><div class="lgd">'.h($lg['detail']).'</div></div><span class="lgt">'.date('d/m/Y H:i',(int)($lg['created']/1000)).'</span></div>'; echo '</div>'; } ?>
      </div>

    <?php } elseif($tab==='pricing'){
      $promo=dgl_promo();
      $epp=null; if(!empty($_GET['eppkg'])){ try{ $st=db()->prepare("SELECT * FROM web_packages WHERE id=?"); $st->execute([(int)$_GET['eppkg']]); $epp=$st->fetch(); }catch(Exception $e){} }
      $pkgs=[]; try{ $pkgs=db()->query("SELECT * FROM web_packages ORDER BY sort,id")->fetchAll(); }catch(Exception $e){} ?>
      <div class="card" style="padding:22px 24px;margin-bottom:20px">
        <h3 class="ah" style="margin-bottom:14px">Tỉ giá & Khuyến mãi</h3>
        <?php if($promo['active']) echo '<div class="flash ok" style="margin-bottom:14px">KM <b>+'.$promo['percent'].'%</b> '.h($CFG['doge_label']??'Dogecoin').' đang chạy'.($promo['until']?' · đến '.date('d/m/Y H:i',(int)($promo['until']/1000)):' · không thời hạn').'.</div>'; ?>
        <form method="post" action="?p=admin&tab=pricing">
          <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="price_save">
          <div class="pricegrid">
            <div class="field" style="margin:0"><label>Tỉ giá (đ = 1 <?=h($CFG['doge_symbol']??'Ð')?>)</label><input name="vnd_per_diamond" type="number" min="1" value="<?=(int)$CFG['vnd_per_diamond']?>"></div>
            <div class="field" style="margin:0"><label>Nạp tối thiểu (đ)</label><input name="custom_min" type="number" value="<?=(int)$CFG['custom_min']?>"></div>
            <div class="field" style="margin:0"><label>Nạp tối đa (đ)</label><input name="custom_max" type="number" value="<?=(int)$CFG['custom_max']?>"></div>
            <div class="field" style="margin:0"><label>Khuyến mãi (%)</label><input name="promo_percent" type="number" min="0" max="500" value="<?=(int)$promo['percent']?>"></div>
            <div class="field" style="margin:0"><label>KM hết hạn (trống = vĩnh viễn)</label><input name="promo_until" type="datetime-local" value="<?= $promo['until']?date('Y-m-d\TH:i',(int)($promo['until']/1000)):'' ?>"></div>
          </div>
          <div style="display:flex;align-items:center;gap:14px;margin-top:14px;flex-wrap:wrap"><button class="btn btn-green" type="submit">Lưu cấu hình</button><span class="sub2">% KM cộng vào <?=h($CFG['doge_label']??'Dogecoin')?> cho mọi giao dịch nạp. Đặt 0% để tắt.</span></div>
        </form>
      </div>
      <div class="admin-grid">
        <div class="card" style="padding:26px">
          <h3 class="ah"><?= $epp?'Sửa gói nạp':'Thêm gói nạp' ?></h3>
          <form method="post" action="?p=admin&tab=pricing">
            <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="pkg_save"><input type="hidden" name="id" value="<?= $epp?(int)$epp['id']:0 ?>">
            <div class="g2">
              <div class="field"><label>Số tiền (đ)</label><input name="amount" type="number" value="<?= $epp?(int)$epp['amount']:50000 ?>" required></div>
              <div class="field"><label><?=h($CFG['doge_label']??'Dogecoin')?> nhận</label><input name="dia" type="number" value="<?= $epp?(int)$epp['dia']:500 ?>" required></div>
              <input type="hidden" name="xu" value="0">
              <div class="field"><label>Nhãn thưởng (tuỳ chọn)</label><input name="bonus" value="<?= $epp?h($epp['bonus']):'' ?>" placeholder="VD: +10% thưởng"></div>
            </div>
            <label class="chk"><input type="checkbox" name="hot" value="1"<?= ($epp&&$epp['hot'])?' checked':'' ?>> Gắn nhãn HOT</label>
            <button class="btn btn-green btn-block" type="submit"><?= $epp?'Cập nhật':'Thêm gói' ?></button>
            <?php if($epp) echo '<a class="btn btn-ghost btn-block" href="?p=admin&tab=pricing" style="margin-top:10px">Huỷ</a>'; ?>
          </form>
        </div>
        <div class="card" style="padding:0;overflow:hidden">
          <div class="ahd">Các gói nạp (<?=count($pkgs)?>)</div>
          <div style="overflow-x:auto"><table class="tbl"><thead><tr><th>Mệnh giá</th><th><?=h($CFG['doge_label']??'Dogecoin')?></th><th></th></tr></thead><tbody>
          <?php if(!$pkgs) echo '<tr><td colspan="3" class="cmid">Chưa có gói nào.</td></tr>';
            else foreach($pkgs as $pk){ echo '<tr><td><b>'.number_format($pk['amount'],0,',','.').'đ</b>'.($pk['hot']?' <span class="st success">HOT</span>':'').($pk['bonus']?'<div class="sub2">'.h($pk['bonus']).'</div>':'').'</td><td style="color:#f7c948;font-weight:700">Ð'.number_format($pk['dia'],0,',','.').'</td><td class="tr"><a class="btn btn-ghost btn-sm" href="?p=admin&tab=pricing&eppkg='.(int)$pk['id'].'">Sửa</a> <form method="post" action="?p=admin&tab=pricing" style="display:inline" onsubmit="return confirm(\'Xoá gói nạp?\')"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="pkg_delete"><input type="hidden" name="id" value="'.(int)$pk['id'].'"><button class="btn btn-sm bdel">Xoá</button></form></td></tr>'; } ?>
          </tbody></table></div>
        </div>
      </div>

    <?php } elseif($tab==='gift'){
      $gifts=[]; try{ $gifts=db()->query("SELECT * FROM web_giftcodes ORDER BY id DESC LIMIT 200")->fetchAll(); }catch(Exception $e){} ?>
      <div class="admin-grid">
        <div class="card" style="padding:26px">
          <h3 class="ah">Tạo Gift Code</h3>
          <form method="post" action="?p=admin&tab=gift">
            <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="gift_create">
            <div class="field"><label>Mã code (để trống = tự tạo ngẫu nhiên)</label><input name="code" placeholder="VD: TET2026" style="text-transform:uppercase"></div>
            <div class="g2">
              <div class="field"><label>Thưởng <?=h($CFG['doge_label']??'Dogecoin')?></label><input name="doge" type="number" min="1" value="100"></div>
              <div class="field"><label>Số lượt dùng tối đa</label><input name="max_uses" type="number" min="1" value="100"></div>
            </div>
            <div class="field"><label>Hết hạn (trống = vĩnh viễn)</label><input name="expire_at" type="datetime-local"></div>
            <div class="field"><label>Ghi chú (nội bộ)</label><input name="note" placeholder="VD: Sự kiện Tết"></div>
            <button class="btn btn-green btn-block" type="submit">Tạo Gift Code</button>
          </form>
          <p class="sub2" style="margin-top:12px">Mỗi tài khoản chỉ dùng được 1 lần / code. Người chơi nhập code ngay trong <b>menu hồ sơ</b> hoặc trang Hồ sơ.</p>
        </div>
        <div class="card" style="padding:0;overflow:hidden">
          <div class="ahd">Danh sách Gift Code (<?=count($gifts)?>)</div>
          <div style="overflow-x:auto"><table class="tbl"><thead><tr><th>Code</th><th>Thưởng</th><th>Đã dùng</th><th>Hạn</th><th></th></tr></thead><tbody>
          <?php if(!$gifts) echo '<tr><td colspan="5" class="cmid">Chưa có gift code nào.</td></tr>';
            else foreach($gifts as $g){ $exp=$g['expires']?date('d/m H:i',(int)($g['expires']/1000)):'∞'; $expired=$g['expires']&&$g['expires']<ms(); $full=$g['used']>=$g['max_uses'];
              $reward=(int)($g['doge']??0); if($reward<=0) $reward=(int)$g['dia']+(int)$g['xu'];
              $on=$g['active'] && !$expired && !$full;
              echo '<tr><td><b style="font-family:monospace;font-size:.95rem;letter-spacing:.5px">'.h($g['code']).'</b>'.($g['note']?'<div class="sub2">'.h($g['note']).'</div>':'').'</td><td style="color:#f7c948;font-weight:700">Ð'.number_format($reward,0,',','.').'</td><td class="'.($full?'':'sub2').'">'.(int)$g['used'].'/'.(int)$g['max_uses'].'</td><td class="sub2'.($expired?' ':'').'">'.$exp.'</td><td class="tr"><span class="st '.($on?'success':'pending').'" style="margin-right:6px">'.($on?'Hoạt động':($expired?'Hết hạn':($full?'Hết lượt':'Đã khoá'))).'</span><form method="post" action="?p=admin&tab=gift" style="display:inline"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="gift_toggle"><input type="hidden" name="id" value="'.(int)$g['id'].'"><button class="btn btn-ghost btn-sm" type="submit">'.($g['active']?'Khoá':'Mở').'</button></form> <form method="post" action="?p=admin&tab=gift" style="display:inline" onsubmit="return confirm(\'Xoá code '.h($g['code']).'?\')"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="gift_delete"><input type="hidden" name="id" value="'.(int)$g['id'].'"><button class="btn btn-sm bdel">Xoá</button></form></td></tr>';
            } ?>
          </tbody></table></div>
        </div>
      </div>

    <?php } ?>
      </div>

      <aside class="amside">
        <a class="amback" href="?p=home">← Về trang chủ</a>
        <?php foreach($groups as $gname=>$items){ echo '<div class="amgroup">'.h($gname).'</div>';
          foreach($items as $it){ $on=$tab===$it[0]; $badge='';
            if($it[0]==='tickets' && $oc) $badge='<span class="ambadge">'.$oc.'</span>';
            echo '<a class="amlink'.($on?' on':'').'" href="?p=admin&tab='.$it[0].'"><span class="amico">'.$it[2].'</span><span>'.h($it[1]).'</span>'.$badge.'</a>';
          }
        } ?>
      </aside>
    </div></div>
  </div>
<?php } ?>
