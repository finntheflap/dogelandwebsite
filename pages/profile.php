<?php
/* Page: profile — extracted from index.php lines 3375-3453 */
?>
<?php
  $w=wallet($user); $vr=verify_row($user); $email=''; $av=$CFG['skin_api'].'/avatar/'.urlencode($user).'/';
  $bal=doge_balance($user); $spent=(int)($w['doge_spent']??0);
  try{ $myRank=db()->prepare("SELECT COUNT(*) FROM web_wallet WHERE doge_spent>?"); $myRank->execute([$spent]); $myRank=$spent>0?((int)$myRank->fetchColumn()+1):0; }catch(Exception $e){ $myRank=0; }
  $regdate=0; $lastlogin=0;
  try{ $st=db()->prepare("SELECT email,regdate,lastlogin FROM `".$CFG['authme_table']."` WHERE LOWER(username)=?"); $st->execute([strtolower($user)]); $r=$st->fetch(); $email=$r['email']??''; $regdate=(int)($r['regdate']??0); $lastlogin=(int)($r['lastlogin']??0); }catch(Exception $e){}
  $rank=trim((string)($w['rank_name']??'')); $suffix=trim((string)($w['suffix']??'')); $rankColor=trim((string)($w['rank_color']??''));
  $ageDays = $regdate>0 ? max(1,(int)floor((ms()-$regdate)/86400000)) : 0;
  $inv=[]; try{ $is=db()->prepare("SELECT * FROM web_inventory WHERE username=? ORDER BY mode,id"); $is->execute([$user]); foreach($is->fetchAll() as $row) $inv[$row['mode']][]=$row; }catch(Exception $e){}
  $modes=$CFG['modes']; $first=array_key_first($modes);
?>
  <div class="phead"><div class="k">Tài khoản</div><h1>Hồ sơ của tôi</h1></div>
  <section style="padding-top:14px"><div class="wrap">
    <div class="admin-grid" style="margin-bottom:20px">
      <div class="card" style="padding:26px">
        <div class="pskin"><img src="<?=h($CFG['skin_api'])?>/body/<?=urlencode($user)?>/right" data-skin-user="<?=h($user)?>" data-skin-size="120" onerror="skinFallback(this)" alt="skin"><div style="font-weight:800;font-size:1.3rem;margin-top:10px"><?=h($user)?></div><div class="sub2"><?= is_owner($user)?'Chủ sở hữu':($IS_ADMIN?'Quản trị viên':'Người chơi') ?></div></div>
        <div style="margin-top:16px">
          <div class="pstat"><span><?=h($CFG['doge_label']??'Dogecoin')?></span><b class="dogechip"><?=number_format($bal,0,',','.')?></b></div>
          <div class="pstat"><span>Đã tiêu</span><b style="color:#f7c948"><?=number_format($spent,0,',','.')?></b></div>
          <div class="pstat"><span>Hạng tiêu xài</span><b><?= $myRank>0?('#'.$myRank):'—' ?></b></div>
          <div class="pstat"><span>Rank in-game</span><b<?= $rank!=='' && $rankColor!=='' ? ' style="color:'.h($rankColor).'"' : '' ?>><?= $rank!=='' ? h($rank).($suffix!==''?' <span class="sub2" style="font-weight:600;color:var(--muted)">'.h($suffix).'</span>':'') : '<span class="sub2" style="font-weight:600">Chưa có</span>' ?></b></div>
          <div class="pstat"><span>Ngày tham gia</span><b><?= $regdate>0 ? date('d/m/Y',(int)($regdate/1000)).' <span class="sub2" style="font-weight:600">('.$ageDays.' ngày)</span>' : '—' ?></b></div>
          <div class="pstat"><span>Đăng nhập gần nhất</span><b><?= $lastlogin>0 ? date('d/m/Y H:i',(int)($lastlogin/1000)) : '—' ?></b></div>
          <div class="pstat"><span>Số lần đăng nhập</span><b><?=(int)$w['logins']?></b></div>
        </div>
        <form method="post" action="?p=profile" style="display:flex;gap:8px;margin-top:16px">
          <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="gift_redeem"><input type="hidden" name="from" value="profile">
          <input name="code" placeholder="Nhập Gift Code" autocomplete="off" style="flex:1;min-width:0;padding:11px 13px;text-transform:uppercase;letter-spacing:.5px">
          <button class="btn btn-gold btn-sm" type="submit">🎁 Đổi</button>
        </form>
        <a class="btn btn-green btn-block" href="?p=topup" style="margin-top:12px">Nạp <?=h($CFG['doge_label']??'Dogecoin')?></a>
        <a class="btn btn-ghost btn-block" href="?p=tickets" style="margin-top:10px">Ticket hỗ trợ của tôi</a>
      </div>
      <div>
        <div class="card" style="padding:24px;margin-bottom:20px">
          <h3 class="ah">Xác thực tài khoản</h3>
          <div class="vrow"><div><div style="font-weight:700">Email</div><div class="sub2"><?= $email?h($email):'Chưa có' ?></div></div>
            <form method="post" action="?p=profile" style="display:flex;gap:8px"><input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="profile_email"><input name="email" type="email" placeholder="email mới" value="<?=h($email)?>" style="width:170px;padding:8px 11px"><button class="btn btn-ghost btn-sm" type="submit">Lưu</button></form>
          </div>
          <div class="vrow"><div><div style="font-weight:700">Số điện thoại <?= $vr['phone_verified']?'<span class="vbadge ok">Đã xác thực</span>':'<span class="vbadge no">Chưa</span>' ?></div><div class="sub2"><?= $vr['phone']?h($vr['phone']):'Chưa liên kết' ?></div></div></div>
          <?php if(!$vr['phone_verified']){ ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin:4px 0 10px">
            <form method="post" action="?p=profile" style="display:flex;gap:8px"><input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="phone_start"><input name="phone" placeholder="Số điện thoại" value="<?=h($vr['phone'])?>" style="width:150px;padding:8px 11px"><button class="btn btn-ghost btn-sm" type="submit">Gửi mã</button></form>
            <form method="post" action="?p=profile" style="display:flex;gap:8px"><input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="phone_verify"><input name="code" placeholder="Nhập mã" style="width:110px;padding:8px 11px"><button class="btn btn-green btn-sm" type="submit">Xác thực</button></form>
          </div>
          <?php } ?>
          <div class="vrow"><div><div style="font-weight:700">Discord <?= $vr['discord_verified']?'<span class="vbadge ok">Đã liên kết</span>':'<span class="vbadge no">Chưa</span>' ?></div><div class="sub2"><?= $vr['discord_name']?h($vr['discord_name']):'Chưa liên kết' ?></div></div>
            <?php if(!$vr['discord_verified']){
              if($CFG['discord_client_id']){ $durl='https://discord.com/oauth2/authorize?client_id='.urlencode($CFG['discord_client_id']).'&response_type=code&scope=identify&redirect_uri='.urlencode($CFG['site_url'].'/?p=discord_cb'); echo '<a class="btn btn-ghost btn-sm" href="'.h($durl).'">Liên kết Discord</a>'; }
              elseif(!empty($CFG['dev_mode'])) echo '<form method="post" action="?p=profile"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="discord_demo"><button class="btn btn-ghost btn-sm" type="submit">Liên kết (demo)</button></form>';
              else echo '<span class="sub2">Chưa cấu hình OAuth</span>';
            } ?>
          </div>
        </div>
        <div class="card" style="padding:24px" id="pw">
          <h3 class="ah">Đổi mật khẩu</h3>
          <form method="post" action="?p=profile">
            <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="profile_pw">
            <div class="g2"><div class="field"><label>Mật khẩu hiện tại</label><input name="current" type="password" required></div><div class="field"><label>Mật khẩu mới</label><input name="new" type="password" required></div></div>
            <div class="field"><label>Nhập lại mật khẩu mới</label><input name="new2" type="password" required></div>
            <button class="btn btn-green" type="submit">Đổi mật khẩu</button>
          </form>
        </div>
      </div>
    </div>

    <div class="card" style="padding:26px">
      <h3 class="ah">Kho đồ theo chế độ</h3>
      <div class="invtabs"><?php $fi=true; foreach($modes as $mk=>$mn){ echo '<button class="invtab'.($fi?' on':'').'" data-m="'.$mk.'" onclick="invTab(this)">'.h($mn).'</button>'; $fi=false; } ?></div>
      <?php $fi=true; foreach($modes as $mk=>$mn){ $items=$inv[$mk]??[];
        echo '<div class="invpane'.($fi?' on':'').'" id="inv-'.$mk.'">';
        if(!$items) echo '<div class="empty">Chưa có vật phẩm nào trong '.h($mn).'.</div>';
        else { echo '<div class="invgrid">'; foreach($items as $it){ $img=item_img($it['image']??'',$it['item_key']??''); echo '<div class="slot" title="'.h($it['item']).'"><img class="ico" src="'.h($img).'" onerror="this.onerror=null;this.src=\'?img=doge\'" alt=""><span class="qy">'.($it['qty']>1?(int)$it['qty']:'').'</span><span class="inm">'.h($it['item']).'</span></div>'; } echo '</div>'; }
        echo '</div>'; $fi=false;
      } ?>
      <p class="sub2" style="margin-top:14px">* Kho đồ được đồng bộ từ server qua plugin/RCON (đang dùng dữ liệu mẫu).</p>
    </div>
  </div></section>
