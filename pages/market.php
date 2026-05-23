<?php
/* Page: market — extracted from index.php lines 3879-3942 */
?>
<?php
  $sapi=$CFG['skin_api']; $bal=$user?doge_balance($user):null; $fp=(int)($CFG['market_fee_percent']??5);
  $q=trim($_GET['q']??'');
  $where=["status='active'"]; $args=[];
  if($q!==''){ $where[]="(item_name LIKE ? OR seller LIKE ? OR description LIKE ?)"; $args[]="%$q%"; $args[]="%$q%"; $args[]="%$q%"; }
  $sql="SELECT * FROM web_market WHERE ".implode(' AND ',$where)." ORDER BY created DESC LIMIT 120";
  $listings=[]; try{ $st=db()->prepare($sql); $st->execute($args); $listings=$st->fetchAll(); }catch(Exception $e){}
  $myInv=[]; if($user){ try{ $is=db()->prepare("SELECT * FROM web_inventory WHERE username=? ORDER BY mode,item"); $is->execute([$user]); $myInv=$is->fetchAll(); }catch(Exception $e){} }
?>
  <div class="phead"><div class="k">Cửa hàng</div><h1>🛒 Chợ Trời</h1><p>Người chơi mua bán trực tiếp bằng <b style="color:#f7c948"><?=h($CFG['doge_label']??'Dogecoin')?></b>. Server thu phí <b><?=$fp?>%</b> mỗi giao dịch.</p>
    <?php if($user) echo '<div class="balbar">Số dư: <b class="dogechip">'.number_format($bal,0,',','.').'</b></div>'; ?>
  </div>
  <section style="padding-top:18px"><div class="wrap">
    <div class="shopnav">
      <a href="?p=auction">🔨 Đấu giá</a><a href="?p=ranks">🎖️ Mua rank</a><a href="?p=market" class="on">🛒 Chợ trời</a>
    </div>

    <?php if($user){ ?>
    <div class="sellbox">
      <h3>🏷️ Đăng bán vật phẩm <span class="sub2" style="font-weight:600;font-size:.85rem">(lấy từ kho của bạn · phí <?=$fp?>% khi bán được)</span></h3>
      <?php if(!$myInv) echo '<div class="sub2">Kho của bạn đang trống — không có gì để bán.</div>';
      else { ?>
      <form method="post" action="?p=market" class="sellgrid">
        <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="market_list">
        <div class="field" style="grid-column:span 2"><label>Vật phẩm trong kho</label>
          <select name="inv_id" required><?php foreach($myInv as $iv) echo '<option value="'.(int)$iv['id'].'">'.h($iv['item']).' ×'.(int)$iv['qty'].' ['.h($iv['mode']).']</option>'; ?></select>
        </div>
        <div class="field"><label>Số lượng bán</label><input name="qty" type="number" min="1" value="1" required></div>
        <div class="field"><label>Icon (mã item)</label><input name="item_key" list="itemkeys" value="diamond" placeholder="diamond"></div>
        <div class="field"><label>Giá (<?=h($CFG['doge_label']??'Dogecoin')?>)</label><input name="price" type="number" min="1" value="100" required></div>
        <div class="field" style="grid-column:span 2"><label>Mô tả (tuỳ chọn)</label><input name="description" maxlength="255" placeholder="VD: enchant Sắc bén V, độ bền cao…"></div>
        <div class="field"><button class="btn btn-gold btn-block" type="submit">Đăng bán</button></div>
      </form>
      <?php } ?>
    </div>
    <?php } ?>

    <form method="get" action="" class="srchbar">
      <input type="hidden" name="p" value="market">
      <div class="sin"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input name="q" value="<?=h($q)?>" placeholder="Tìm vật phẩm, người bán, mô tả…"></div>
      <button class="btn btn-ghost" type="submit">Tìm</button>
    </form>
    <div class="srchcount"><?=count($listings)?> mặt hàng<?= $q!==''?(' khớp "'.h($q).'"'):'' ?></div>

    <div class="grid3">
      <?php if(!$listings) echo '<div class="empty" style="grid-column:1/-1">Chưa có mặt hàng nào đang bán.</div>';
        foreach($listings as $m){
          $isSeller = $user && strtolower($m['seller'])===strtolower($user);
          echo '<div class="card mcard"><div class="top"><img class="iimg" src="'.h(item_img($m['image']??'',$m['item_key'])).'" onerror="this.onerror=null;this.src=\'?img=doge\'" alt="">'
              .'<div style="flex:1"><div class="nm">'.h($m['item_name']).'</div><div class="qy">Số lượng: '.(int)$m['qty'].($m['mode']?(' · '.h($m['mode'])):'').'</div></div></div>';
          echo '<div class="desc">'.($m['description']!==''?h($m['description']):'<span style="opacity:.5">Không có mô tả</span>').'</div>';
          echo '<div class="pr"><div class="big"><span class="dsym">Ð</span>'.number_format($m['price'],0,',','.').'</div>'
              .'<div class="sl"><img src="'.h($sapi).'/avatar/'.urlencode($m['seller']).'/18" onerror="this.style.display=\'none\'">'.h($m['seller']).'</div></div>';
          if(!$user) echo '<a class="btn btn-green btn-block btn-sm" href="?p=login">Đăng nhập để mua</a>';
          elseif($isSeller) echo '<form method="post" action="?p=market" onsubmit="return confirm(\'Gỡ tin & trả vật phẩm về kho?\')"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="market_cancel"><input type="hidden" name="id" value="'.(int)$m['id'].'"><button class="btn btn-ghost btn-block btn-sm" type="submit">Gỡ tin của tôi</button></form>';
          else echo '<form method="post" action="?p=market" onsubmit="return confirm(\'Mua '.h(addslashes($m['item_name'])).' với '.number_format($m['price'],0,',','.').' '.h($CFG['doge_label']??'Dogecoin').'?\')"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="market_buy"><input type="hidden" name="id" value="'.(int)$m['id'].'"><button class="btn btn-green btn-block btn-sm" type="submit">Mua · Ð'.number_format($m['price'],0,',','.').'</button></form>';
          echo '</div>';
        } ?>
    </div>
    <div class="feeline">💡 Khi bán được, người bán nhận giá đã trừ phí <?=$fp?>%. Vật phẩm chuyển thẳng vào kho người mua.</div>
  </div></section>
  <datalist id="itemkeys"><?php foreach($ITEM_KEYS as $k) echo '<option value="'.h($k).'">'; ?></datalist>
