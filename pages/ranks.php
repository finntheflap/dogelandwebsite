<?php
/* Page: ranks — extracted from index.php lines 3846-3876 */
?>
<?php
  $scope=$_GET['scope']??'all'; if(!in_array($scope,['all','sdo'],true)) $scope='all';
  $bal=$user?doge_balance($user):null;
  $ranks=[]; try{ $st=db()->prepare("SELECT * FROM web_ranks WHERE scope=? AND active=1 ORDER BY sort,id"); $st->execute([$scope]); $ranks=$st->fetchAll(); }catch(Exception $e){}
?>
  <div class="phead"><div class="k">Cửa hàng</div><h1>🎖️ Mua Rank</h1><p>Mua bằng <b style="color:#f7c948"><?=h($CFG['doge_label']??'Dogecoin')?></b>. Lệnh quyền lợi sẽ tự động chạy in-game sau khi mua.</p>
    <?php if($user) echo '<div class="balbar">Số dư: <b class="dogechip">'.number_format($bal,0,',','.').'</b></div>'; ?>
  </div>
  <section style="padding-top:18px"><div class="wrap">
    <div class="shopnav">
      <a href="?p=auction">🔨 Đấu giá</a><a href="?p=ranks" class="on">🎖️ Mua rank</a><a href="?p=market">🛒 Chợ trời</a>
    </div>
    <div class="scopebar">
      <a href="?p=ranks&scope=all" class="<?=$scope==='all'?'on':''?>">🌐 Tất cả server</a>
      <a href="?p=ranks&scope=sdo" class="<?=$scope==='sdo'?'on sdo':''?>">⚔️ Sword Dark Online</a>
    </div>
    <?php if($IS_ADMIN) echo '<div style="text-align:center;margin-bottom:18px"><a class="btn btn-ghost btn-sm" href="?p=admin&tab=ranks">⚙️ Quản lý rank (admin)</a></div>'; ?>
    <div class="grid3">
      <?php if(!$ranks) echo '<div class="empty" style="grid-column:1/-1">Chưa có rank nào trong khu vực này.</div>';
        foreach($ranks as $r){ $perks=array_filter(array_map('trim',preg_split('/\r\n|\r|\n/',(string)$r['description'])));
          echo '<div class="card vip"><div class="rk" style="color:'.h($r['color']).'">'.h($r['name']).'</div>'
              .'<div class="vp" style="color:#f7c948">'.number_format($r['price'],0,',','.').' <small>'.h($CFG['doge_label']??'Dogecoin').'</small></div><ul>';
          foreach($perks as $pk) echo '<li><b>+</b> '.h($pk).'</li>';
          echo '</ul>';
          if(!$user) echo '<a class="btn btn-green btn-block btn-sm" href="?p=login">Đăng nhập để mua</a>';
          else echo '<form method="post" action="?p=ranks" onsubmit="return confirm(\'Mua rank '.h(addslashes($r['name'])).' với '.number_format($r['price'],0,',','.').' '.h($CFG['doge_label']??'Dogecoin').'?\')"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="rank_buy"><input type="hidden" name="id" value="'.(int)$r['id'].'"><button class="btn btn-green btn-block btn-sm" type="submit">Mua · Ð'.number_format($r['price'],0,',','.').'</button></form>';
          echo '</div>';
        } ?>
    </div>
  </div></section>
