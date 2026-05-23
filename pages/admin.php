<?php
/* Page: admin — extracted from index.php lines 2803-3278 */
?>
<?php
  if(!$IS_ADMIN){ echo '<div class="phead"><div class="k">Quản trị</div><h1>Không có quyền</h1><p>Trang này chỉ dành cho Admin. '.($user?'':'<a href="?p=login" style="color:var(--green)">Đăng nhập</a>').'</p></div>'; }
  else {
    $tab=$_GET['tab']??'dash'; $T=$CFG['authme_table'];
    // nhóm điều hướng (icon đơn giản bằng ký tự)
    $groups=[
      'Tổng quan'=>[['dash','Dashboard','▦']],
      'Nội dung'=>[['posts','Bài viết & Tin tức','✎'],['announce','Thông báo khẩn','📢']],
      'Người dùng'=>[['users','Quản lý người dùng','👤']],
      'Kinh tế'=>[['topups','Nạp thẻ','💳'],['pricing','Giá nạp & Khuyến mãi','💱'],['gift','Gift Code','🎁'],['ranks','Mua Rank','🎖'],['auc','Đấu giá','🔨'],['market','Chợ Trời','🛒']],
      'Hỗ trợ'=>[['tickets','Ticket hỗ trợ','🎫']],
      'Hệ thống'=>[['logs','Nhật ký Admin','📜']],
    ];
    $groups['Hệ thống'][]=['staff','Phân quyền','🔑'];
    $titles=[]; foreach($groups as $gs) foreach($gs as $it) $titles[$it[0]]=$it[1];
    $oc=open_tickets();
?>
  <div class="adminmode">
    <div class="wrap"><div class="amshell">
      <div class="ammain">
        <div class="amhead"><div><div class="k" style="margin:0">Admin Mode</div><h1 style="font-size:1.8rem;font-weight:800;line-height:1.1;margin-top:4px"><?=h($titles[$tab]??'Dashboard')?></h1></div><span class="amwho"><img src="<?=h($CFG['skin_api'])?>/avatar/<?=urlencode($user)?>/30" data-skin-user="<?=h($user)?>" data-skin-size="30" onerror="skinFallback(this)" alt=""><?=h($user)?> · <?= is_owner($user)?'Owner':'Admin' ?></span></div>

    <?php if($tab==='dash'){
      $stat=function($q,$d=0){ try{ return (int)db()->query($q)->fetchColumn(); }catch(Exception $e){ return $d; } };
      $users=$stat("SELECT COUNT(*) FROM `$T`"); $posts=$stat("SELECT COUNT(*) FROM web_posts");
      $pend=$stat("SELECT COUNT(*) FROM web_topups WHERE status='pending'"); $rev=$stat("SELECT COALESCE(SUM(amount),0) FROM web_topups WHERE status='success'");
      $opent=$stat("SELECT COUNT(*) FROM web_tickets WHERE status<>'closed'"); $adm=$stat("SELECT COUNT(*) FROM web_admins")+1;
      $banned=$stat("SELECT COUNT(*) FROM web_wallet WHERE banned=1");
      $promo=dgl_promo(); $now0=ms();
      $activeGifts=$stat("SELECT COUNT(*) FROM web_giftcodes WHERE active=1 AND used<max_uses AND (expires IS NULL OR expires>$now0)");
      $promoCount=$activeGifts+($promo['active']?1:0);
      $recentT=[]; try{ $recentT=db()->query("SELECT * FROM web_tickets ORDER BY updated DESC LIMIT 5")->fetchAll(); }catch(Exception $e){}
      $recentL=[]; try{ $recentL=db()->query("SELECT * FROM web_admin_log ORDER BY id DESC LIMIT 6")->fetchAll(); }catch(Exception $e){}
      $slt=['open'=>'Đang mở','in_progress'=>'Đang xử lý','closed'=>'Đã đóng']; ?>
      <div class="amstats">
        <div class="amstat"><div class="amsi" style="background:rgba(86,207,214,.15)">👥</div><div><div class="amsv"><?=number_format($users)?></div><div class="amsl">Người dùng</div></div></div>
        <div class="amstat"><div class="amsi" style="background:rgba(242,182,49,.15)">💰</div><div><div class="amsv"><?=number_format($rev,0,',','.')?>đ</div><div class="amsl">Tổng nạp (đã duyệt)</div></div></div>
        <div class="amstat"><a href="?p=admin&tab=topups" style="text-decoration:none;display:flex;gap:14px;align-items:center"><div class="amsi" style="background:rgba(224,88,74,.15)">⏳</div><div><div class="amsv"><?=$pend?></div><div class="amsl">Giao dịch chờ duyệt</div></div></a></div>
        <div class="amstat"><a href="?p=admin&tab=tickets" style="text-decoration:none;display:flex;gap:14px;align-items:center"><div class="amsi" style="background:rgba(91,141,239,.15)">🎫</div><div><div class="amsv"><?=$opent?></div><div class="amsl">Ticket chưa đóng</div></div></a></div>
        <div class="amstat"><div class="amsi" style="background:rgba(179,156,232,.15)">📰</div><div><div class="amsv"><?=number_format($posts)?></div><div class="amsl">Bài viết</div></div></div>
        <div class="amstat"><div class="amsi" style="background:rgba(255,141,176,.15)">🔑</div><div><div class="amsv"><?=$adm?></div><div class="amsl">Quản trị viên</div></div></div>
        <div class="amstat"><a href="?p=admin&tab=users" style="text-decoration:none;display:flex;gap:14px;align-items:center"><div class="amsi" style="background:rgba(224,88,74,.15)">🚫</div><div><div class="amsv"><?=number_format($banned)?></div><div class="amsl">Người dùng bị ban</div></div></a></div>
        <div class="amstat"><a href="?p=admin&tab=gift" style="text-decoration:none;display:flex;gap:14px;align-items:center"><div class="amsi" style="background:rgba(242,182,49,.15)">🎁</div><div><div class="amsv"><?=number_format($promoCount)?></div><div class="amsl">Khuyến mãi đang chạy<?= $promo['active']?' (+'.$promo['percent'].'% nạp)':'' ?></div></div></a></div>
      </div>
      <div class="admin-grid" style="margin-top:8px">
        <div class="card" style="padding:0;overflow:hidden">
          <div class="ahd">Ticket gần đây</div>
          <?php if(!$recentT) echo '<div class="cmid">Chưa có ticket.</div>';
            else foreach($recentT as $t) echo '<a class="tk" href="?p=ticket&id='.(int)$t['id'].'"><span class="tno">'.h($t['code']?:ticket_code($t['id'])).'</span><div class="ti"><div class="ts">'.h($t['subject']).'</div><div class="tmeta">'.h($t['username']).' · '.date('d/m H:i',(int)($t['updated']/1000)).'</div></div><span class="tst '.h($t['status']).'">'.($slt[$t['status']]??$t['status']).'</span></a>'; ?>
        </div>
        <div class="card" style="padding:0;overflow:hidden">
          <div class="ahd">Hoạt động Admin gần đây</div>
          <?php if(!$recentL) echo '<div class="cmid">Chưa có hoạt động.</div>';
            else { echo '<div style="padding:6px 0">'; foreach($recentL as $lg) echo '<div class="logrow"><img src="'.h($CFG['skin_api']).'/avatar/'.urlencode($lg['admin']).'/28" data-skin-user="'.h($lg['admin']).'" data-skin-size="28" onerror="skinFallback(this)" alt=""><div class="lgb"><span class="lga">'.h($lg['admin']).'</span> <span class="lgac">'.h($lg['action']).'</span><div class="lgd">'.h($lg['detail']).'</div></div><span class="lgt">'.date('d/m H:i',(int)($lg['created']/1000)).'</span></div>'; echo '</div>'; } ?>
        </div>
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
              <div class="field"><label>Server (Cập nhật / Hướng dẫn)</label>
                <input name="server" value="<?= $edit?h($edit['server']??''):'' ?>" placeholder="VD: Survival, SDO, Lobby — để trống nếu áp dụng toàn server" list="srv-suggest">
                <datalist id="srv-suggest"><?php foreach(($CFG['ticket_servers']??[]) as $s) echo '<option value="'.h($s).'">'; ?></datalist>
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
      if($eu){ try{ $st=db()->prepare("SELECT realname,email FROM `$T` WHERE LOWER(username)=?"); $st->execute([strtolower($eu)]); $eue=$st->fetch(); $euw=wallet($eu); }catch(Exception $e){} }
      $users=[]; try{ $users=db()->query("SELECT a.realname uname, a.email, COALESCE(w.dogecoin,0) dogecoin, COALESCE(w.doge_spent,0) doge_spent, COALESCE(w.verified,0) verified, COALESCE(w.logins,0) logins, COALESCE(w.banned,0) banned, COALESCE(w.ban_reason,'') ban_reason FROM `$T` a LEFT JOIN web_wallet w ON w.username=a.realname ORDER BY a.id DESC LIMIT 300")->fetchAll(); }catch(Exception $e){} ?>
      <?php if($eue){ $einv=[]; try{ $is=db()->prepare("SELECT * FROM web_inventory WHERE username=? ORDER BY mode,id"); $is->execute([$eue['realname']]); foreach($is->fetchAll() as $row) $einv[$row['mode']][]=$row; }catch(Exception $e){} $modes=$CFG['modes']; ?>
        <div class="card" style="padding:24px;margin-bottom:20px">
          <h3 class="ah">Sửa tài khoản: <?=h($eue['realname'])?></h3>
          <form method="post" action="?p=admin&tab=users">
            <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="user_save"><input type="hidden" name="username" value="<?=h($eue['realname'])?>">
            <div class="g2">
              <div class="field"><label>Email</label><input name="email" value="<?=h($eue['email'])?>"></div>
              <div class="field"><label>Đặt lại mật khẩu (để trống nếu giữ nguyên)</label><input name="newpw" type="text" placeholder="Mật khẩu mới"></div>
              <div class="field"><label><?=h($CFG['doge_label']??'Dogecoin')?> (số dư)</label><input name="dogecoin" type="number" value="<?=doge_balance($eue['realname'])?>"></div>
              <div class="field"><label>Đã tiêu (chỉ xem)</label><input type="number" value="<?=(int)($euw['doge_spent']??0)?>" disabled></div>
              <div class="field"><label>Rank in-game</label><input name="rank_name" value="<?=h($euw['rank_name']??'')?>" placeholder="VD: vip, mvp, default"></div>
              <div class="field"><label>Suffix in-game</label><input name="suffix" value="<?=h($euw['suffix']??'')?>" placeholder="VD: &c[Huyền Thoại]"></div>
            </div>
            <label class="chk"><input type="checkbox" name="verified" value="1"<?= $euw['verified']?' checked':'' ?>> Đã xác minh (verified)</label>
            <p class="sub2" style="margin:-4px 0 14px">Thay đổi <b>Rank/Suffix</b> sẽ tạo lệnh LuckPerms đưa vào hàng đợi RCON áp dụng in-game.</p>
            <div style="display:flex;gap:10px"><button class="btn btn-green" type="submit">Lưu thay đổi</button><a class="btn btn-ghost" href="?p=admin&tab=users">Huỷ</a></div>
          </form>
        </div>
        <?php $isBanE=!empty($euw['banned']); $ownerE=is_owner($eue['realname']); ?>
        <div class="card" style="padding:24px;margin-bottom:20px;border-color:<?= $isBanE?'rgba(224,88,74,.4)':'var(--line)' ?>">
          <h3 class="ah">Khoá tài khoản (Ban)</h3>
          <?php if($ownerE){ echo '<p class="sub2">Không thể ban chủ sở hữu.</p>'; }
            elseif($isBanE){ ?>
            <div class="flash error" style="margin-bottom:14px">Đang bị ban<?= !empty($euw['ban_reason'])?' — Lý do: <b>'.h($euw['ban_reason']).'</b>':'' ?><?= !empty($euw['banned_by'])?' · bởi '.h($euw['banned_by']):'' ?></div>
            <form method="post" action="?p=admin&tab=users">
              <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="user_unban"><input type="hidden" name="username" value="<?=h($eue['realname'])?>"><input type="hidden" name="back_edit" value="1">
              <button class="btn btn-green" type="submit">Gỡ ban</button>
            </form>
          <?php } else { ?>
            <form method="post" action="?p=admin&tab=users" onsubmit="return confirm('Ban tài khoản <?=h($eue['realname'])?>?')">
              <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="user_ban"><input type="hidden" name="username" value="<?=h($eue['realname'])?>"><input type="hidden" name="back_edit" value="1">
              <div class="field"><label>Lý do (tuỳ chọn)</label><input name="reason" placeholder="VD: Gian lận / phá hoại server"></div>
              <button class="btn bdel" type="submit" style="background:var(--red);color:#fff">Ban tài khoản</button>
            </form>
            <p class="sub2" style="margin-top:10px">Người bị ban không thể đăng nhập web và sẽ nhận lệnh <code>/ban</code> qua hàng đợi RCON.</p>
          <?php } ?>
        </div>
        <div class="card" style="padding:24px;margin-bottom:20px">
          <h3 class="ah">Kho đồ của <?=h($eue['realname'])?></h3>
          <form method="post" action="?p=admin&tab=users" enctype="multipart/form-data" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:18px">
            <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="inv_add"><input type="hidden" name="username" value="<?=h($eue['realname'])?>">
            <div class="field" style="margin:0;flex:1;min-width:110px"><label>Chế độ</label><select name="mode"><?php foreach($modes as $mk=>$mn) echo '<option value="'.$mk.'">'.h($mn).'</option>'; ?></select></div>
            <div class="field" style="margin:0;flex:2;min-width:130px"><label>Vật phẩm</label><input name="item" placeholder="VD: Kiếm Kim Cương" required></div>
            <div class="field" style="margin:0;width:70px"><label>SL</label><input name="qty" type="number" value="1" min="1"></div>
            <div class="field" style="margin:0;width:110px"><label>Mã icon</label><input name="item_key" list="itemkeys" placeholder="diamond_sword"></div>
            <div class="field" style="margin:0;flex:1;min-width:150px"><label>Ảnh (URL)</label><input name="image" placeholder="https://… (tuỳ chọn)"></div>
            <div class="field" style="margin:0;flex:1;min-width:150px"><label>hoặc tải ảnh lên</label><input name="image_file" type="file" accept="image/*"></div>
            <div class="field" style="margin:0;width:80px"><label>Màu</label><input name="color" value="#56cfd6"></div>
            <button class="btn btn-green" type="submit">Thêm</button>
          </form>
          <p class="sub2" style="margin:-10px 0 14px">💡 Gán <b>ảnh riêng</b> (URL hoặc tải lên) cho từng vật phẩm; nếu để trống sẽ tự lấy icon theo <b>mã icon</b> (vd <code>diamond_sword</code>). Đấu giá & chợ trời sẽ dùng ảnh này.</p>
          <?php foreach($modes as $mk=>$mn){ $items=$einv[$mk]??[]; if(!$items) continue;
            echo '<div style="font-weight:700;color:var(--gold);font-size:.82rem;margin:10px 0 8px;text-transform:uppercase;letter-spacing:.5px">'.h($mn).'</div><div class="invgrid">';
            foreach($items as $it){ $img=item_img($it['image']??'',$it['item_key']??'');
              echo '<div class="slot" title="'.h($it['item']).'"><img class="ico" src="'.h($img).'" onerror="this.onerror=null;this.src=\'?img=doge\'" alt=""><span class="qy">'.($it['qty']>1?(int)$it['qty']:'').'</span><span class="inm">'.h($it['item']).'</span><form method="post" action="?p=admin&tab=users" onsubmit="return confirm(\'Xoá?\')" style="position:absolute;top:3px;left:3px"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="inv_delete"><input type="hidden" name="id" value="'.(int)$it['id'].'"><input type="hidden" name="username" value="'.h($eue['realname']).'"><button type="submit" title="Xoá" style="background:var(--red);color:#fff;border:none;width:18px;height:18px;border-radius:5px;cursor:pointer;font-size:.7rem;line-height:1;padding:0">×</button></form></div>';
            }
            echo '</div>';
          }
          if(!$einv) echo '<p class="sub2">Kho đồ trống.</p>'; ?>
        </div>
      <?php } ?>
      <div class="card" style="padding:0;overflow:hidden">
        <div class="ahd">Tài khoản người chơi (<?=count($users)?>)</div>
        <div style="overflow-x:auto"><table class="tbl"><thead><tr><th>Tài khoản</th><th>Email</th><th><?=h($CFG['doge_label']??'Dogecoin')?></th><th>Đã tiêu</th><th>Trạng thái</th><th>Login</th><th></th></tr></thead><tbody>
        <?php if(!$users) echo '<tr><td colspan="7" class="cmid">Chưa có tài khoản.</td></tr>';
          else foreach($users as $u2){ $isBan=!empty($u2['banned']); $owner=is_owner($u2['uname']);
            $stt = $isBan ? '<span class="st rejected">Bị ban</span>' : ($u2['verified']?'<span class="st success">Verified</span>':'<span class="st pending">Chưa</span>');
            $banForm = $owner ? '<span class="sub2">—</span>' : ($isBan
                ? '<form method="post" action="?p=admin&tab=users" style="display:inline"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="user_unban"><input type="hidden" name="username" value="'.h($u2['uname']).'"><button class="btn btn-green btn-sm" type="submit">Gỡ ban</button></form>'
                : '<form method="post" action="?p=admin&tab=users" style="display:inline" onsubmit="this.querySelector(\'[name=reason]\').value=prompt(\'Lý do ban '.h($u2['uname']).' (tuỳ chọn):\',\'\')||\'\';return confirm(\'Ban '.h($u2['uname']).'?\')"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="user_ban"><input type="hidden" name="username" value="'.h($u2['uname']).'"><input type="hidden" name="reason" value=""><button class="btn btn-sm bdel" type="submit">Ban</button></form>');
            echo '<tr><td style="font-weight:700">'.h($u2['uname']).($owner?' <span class="st" style="background:rgba(255,141,176,.18);color:#ff8db0">OWNER</span>':'').'</td><td class="sub2">'.h($u2['email']).'</td><td style="color:#f7c948;font-weight:700">Ð'.number_format($u2['dogecoin'],0,',','.').'</td><td class="sub2">'.number_format($u2['doge_spent'],0,',','.').'</td><td>'.$stt.'</td><td class="sub2">'.(int)$u2['logins'].'</td><td class="tr" style="white-space:nowrap"><a class="btn btn-ghost btn-sm" href="?p=admin&tab=users&euser='.urlencode($u2['uname']).'">Sửa</a> '.$banForm.' <form method="post" action="?p=admin&tab=users" style="display:inline" onsubmit="return confirm(\'Xoá tài khoản '.h($u2['uname']).'?\')"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="user_delete"><input type="hidden" name="username" value="'.h($u2['uname']).'"><button class="btn btn-sm bdel">Xoá</button></form></td></tr>';
          } ?>
        </tbody></table></div>
      </div>

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

    <?php } elseif($tab==='staff'){
      $admins=[]; try{ $admins=db()->query("SELECT * FROM web_admins ORDER BY created DESC")->fetchAll(); }catch(Exception $e){} ?>
      <div class="admin-grid">
        <div class="card" style="padding:26px">
          <h3 class="ah">Cấp quyền Admin</h3>
          <form method="post" action="?p=admin&tab=staff">
            <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="admin_grant">
            <div class="field"><label>Tên tài khoản (IGN)</label><input name="username" placeholder="Nhập IGN cần cấp quyền" required></div>
            <button class="btn btn-green btn-block" type="submit">Cấp quyền admin</button>
          </form>
          <p class="sub2" style="margin-top:14px">Mọi admin đều có thể cấp quyền cho người khác. Chỉ <b style="color:#ff8db0"><?=h($CFG['owner'])?></b> (chủ sở hữu) là không thể bị thu quyền.</p>
        </div>
        <div class="card" style="padding:0;overflow:hidden">
          <div class="ahd">Quản trị viên (<?=count($admins)+1?>)</div>
          <div style="overflow-x:auto"><table class="tbl"><tbody>
          <tr><td><b style="color:#ff8db0"><?=h($CFG['owner'])?></b> <span class="vbadge ok" style="margin-left:4px">OWNER</span><div class="sub2">Chủ sở hữu · toàn quyền</div></td><td class="tr"><span class="sub2">Không thể thu</span></td></tr>
          <?php foreach($admins as $ad) echo '<tr><td><b>'.h($ad['username']).'</b><div class="sub2">Cấp bởi '.h($ad['granted_by']).' · '.date('d/m/Y',(int)($ad['created']/1000)).'</div></td><td class="tr"><form method="post" action="?p=admin&tab=staff" onsubmit="return confirm(\'Thu quyền của '.h($ad['username']).'?\')"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="admin_revoke"><input type="hidden" name="username" value="'.h($ad['username']).'"><button class="btn btn-sm bdel">Thu quyền</button></form></td></tr>'; ?>
          </tbody></table></div>
        </div>
      </div>

    <?php } elseif($tab==='announce'){
      $hist=[]; try{ $hist=db()->query("SELECT * FROM web_announce ORDER BY id DESC LIMIT 30")->fetchAll(); }catch(Exception $e){}
      $cur=active_announce(); $lvls=['info'=>'Thông tin (xanh)','warn'=>'Cảnh báo (vàng)','danger'=>'Khẩn cấp (đỏ)']; ?>
      <div class="admin-grid">
        <div class="card" style="padding:26px">
          <h3 class="ah">Đăng thông báo khẩn</h3>
          <?php if($cur) echo '<div class="flash ok" style="margin-bottom:14px">Đang hiển thị: "'.h($cur['message']).'"</div>'; ?>
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
