<?php
/* Page: auction — extracted from index.php lines 3748-3843 */
?>
<?php
  auc_settle_due();
  $sapi=$CFG['skin_api']; $bal=$user?doge_balance($user):null; $fee=(int)($CFG['auction_open_fee']??2);
  $q=trim($_GET['q']??''); $f=$_GET['f']??'active'; if(!in_array($f,['active','all','sold','expired'],true)) $f='active';
  $where=[]; $args=[];
  if($f==='active'){ $where[]="status='active' AND end_at>".ms(); }
  elseif($f==='sold'){ $where[]="status='sold'"; }
  elseif($f==='expired'){ $where[]="status IN('expired','cancelled')"; }
  if($q!==''){ $where[]="(item LIKE ? OR seller LIKE ?)"; $args[]="%$q%"; $args[]="%$q%"; }
  $sql="SELECT * FROM web_auctions".($where?(' WHERE '.implode(' AND ',$where)):'')." ORDER BY (status='active') DESC, end_at ASC LIMIT 120";
  $aucs=[]; try{ $st=db()->prepare($sql); $st->execute($args); $aucs=$st->fetchAll(); }catch(Exception $e){}
  $myInv=[]; if($user){ try{ $is=db()->prepare("SELECT * FROM web_inventory WHERE username=? ORDER BY mode,item"); $is->execute([$user]); $myInv=$is->fetchAll(); }catch(Exception $e){} }
?>
  <div class="phead"><div class="k">Cửa hàng</div><h1>🔨 Đấu Giá</h1><p>Trả giá bằng <b style="color:#f7c948"><?=h($CFG['doge_label']??'Dogecoin')?></b> — người cao nhất khi hết giờ thắng. Mở phiên tốn <b><?=doge_short($fee)?></b> (hoàn lại nếu không ai đấu).</p>
    <?php if($user) echo '<div class="balbar">Số dư: <b class="dogechip">'.number_format($bal,0,',','.').'</b></div>'; ?>
  </div>
  <section style="padding-top:18px"><div class="wrap">
    <div class="shopnav">
      <a href="?p=auction" class="on">🔨 Đấu giá</a><a href="?p=ranks">🎖️ Mua rank</a><a href="?p=market">🛒 Chợ trời</a>
    </div>

    <?php if($user){ ?>
    <div class="sellbox">
      <h3>🆕 Mở phiên đấu giá <span class="sub2" style="font-weight:600;font-size:.85rem">(lấy vật phẩm từ kho · phí <?=doge_short($fee)?>, hoàn nếu hết giờ không ai đấu)</span></h3>
      <?php if(!$myInv) echo '<div class="sub2">Kho của bạn đang trống — không có gì để mở đấu giá.</div>';
      else { ?>
      <form method="post" action="?p=auction" class="sellgrid" onsubmit="return confirm('Mở phiên đấu giá? Sẽ tạm trừ <?=$fee?> <?=h($CFG['doge_label']??'Dogecoin')?> và giữ vật phẩm từ kho.')">
        <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="auc_open">
        <div class="field" style="grid-column:span 2"><label>Vật phẩm trong kho</label>
          <select name="inv_id" required><?php foreach($myInv as $iv) echo '<option value="'.(int)$iv['id'].'">'.h($iv['item']).' ×'.(int)$iv['qty'].' ['.h($iv['mode']).']</option>'; ?></select>
        </div>
        <div class="field"><label>Số lượng</label><input name="qty" type="number" min="1" value="1" required></div>
        <div class="field"><label>Giá khởi điểm</label><input name="start_price" type="number" min="1" value="100" required></div>
        <div class="field"><label>Thời lượng (giờ)</label><input name="hours" type="number" min="0.5" step="0.5" value="24" required></div>
        <div class="field"><button class="btn btn-gold btn-block" type="submit">Mở phiên</button></div>
      </form>
      <p class="sub2" style="margin-top:10px">💡 Ảnh & icon vật phẩm lấy theo món bạn chọn trong kho (admin có thể gán ảnh riêng cho từng vật phẩm).</p>
      <?php } ?>
    </div>
    <?php } ?>

    <form method="get" action="" class="srchbar">
      <input type="hidden" name="p" value="auction">
      <div class="sin"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input name="q" value="<?=h($q)?>" placeholder="Tìm theo tên vật phẩm hoặc người bán…"></div>
      <select name="f" onchange="this.form.submit()">
        <option value="active"<?=$f==='active'?' selected':''?>>Đang diễn ra</option>
        <option value="all"<?=$f==='all'?' selected':''?>>Tất cả</option>
        <option value="sold"<?=$f==='sold'?' selected':''?>>Đã bán</option>
        <option value="expired"<?=$f==='expired'?' selected':''?>>Hết hạn / huỷ</option>
      </select>
      <button class="btn btn-ghost" type="submit">Tìm</button>
    </form>
    <div class="srchcount"><?=count($aucs)?> phiên<?= $q!==''?(' khớp "'.h($q).'"'):'' ?></div>

    <div class="grid3">
      <?php if(!$aucs) echo '<div class="empty" style="grid-column:1/-1">Không có phiên đấu giá nào.</div>';
        foreach($aucs as $a){
          $ended = $a['status']!=='active' || (int)$a['end_at']<=ms();
          $isSeller = $user && strtolower($a['seller'])===strtolower($user);
          $topBidder=$a['top_bidder']; $hasBid=$topBidder!=='';
          echo '<div class="card acard'.($ended?' ended':'').'">';
          echo '<div class="top"><img class="iimg" src="'.h(item_img($a['image']??'',$a['item_key'])).'" onerror="this.onerror=null;this.src=\'?img=doge\'" alt="">'
              .'<div style="flex:1"><div class="nm">'.h($a['item']).((int)($a['qty']??1)>1?' <span class="sub2">×'.(int)$a['qty'].'</span>':'').'</div>'
              .'<div class="sl"><img src="'.h($sapi).'/avatar/'.urlencode($a['seller']).'/18" onerror="this.style.display=\'none\'">Người bán: '.h($a['seller']).'</div></div>';
          if($a['status']==='sold') echo '<span class="statetag sold">Đã bán</span>';
          elseif($a['status']==='expired') echo '<span class="statetag expired">Hết hạn</span>';
          elseif($a['status']==='cancelled') echo '<span class="statetag cancelled">Đã huỷ</span>';
          echo '</div>';
          echo '<div class="grid"><div><div class="lbl">'.($hasBid?'Giá cao nhất':'Giá khởi điểm').'</div><div class="big"><span class="dsym">Ð</span>'.number_format($a['price'],0,',','.').'</div></div>';
          echo '<div><div class="lbl">Người giữ giá</div><div class="topb">';
          if($hasBid) echo '<img src="'.h($sapi).'/avatar/'.urlencode($topBidder).'/18" onerror="this.style.display=\'none\'">'.h($topBidder);
          else echo '<span class="none">Chưa có ai</span>';
          echo '</div></div></div>';
          if(!$ended){
            echo '<div style="display:flex;justify-content:space-between;align-items:center"><span class="lbl">Còn lại</span><span class="tm" data-end="'.(int)$a['end_at'].'">…</span></div>';
            if($user && !$isSeller){
              $min = $hasBid ? ((int)$a['price']+1) : (int)$a['price'];
              echo '<form method="post" action="?p=auction" class="bidrow"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="auc_bid"><input type="hidden" name="id" value="'.(int)$a['id'].'">'
                  .'<input name="amount" type="number" min="'.$min.'" value="'.$min.'" required><button class="btn btn-green btn-sm" type="submit">Đặt giá</button></form>';
            } elseif($isSeller){
              echo '<div class="sub2" style="text-align:center;font-size:.82rem">'.($hasBid?'Phiên của bạn · đang có người giữ giá Ð'.number_format($a['price'],0,',','.'):'Phiên của bạn · chưa có ai đấu').'</div>';
              if(!$hasBid) echo '<form method="post" action="?p=auction" onsubmit="return confirm(\'Huỷ phiên & hoàn phí mở?\')"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="auc_cancel"><input type="hidden" name="id" value="'.(int)$a['id'].'"><button class="btn btn-ghost btn-block btn-sm" type="submit">Huỷ phiên (hoàn '.doge_short((int)$a['listing_fee']).')</button></form>';
            } else {
              echo '<a class="btn btn-green btn-block btn-sm" href="?p=login">Đăng nhập để đấu giá</a>';
            }
          } else {
            $info = $a['status']==='sold' ? ('Bán cho '.h($topBidder).' với Ð'.number_format($a['price'],0,',','.')) : 'Phiên đã kết thúc';
            echo '<div class="sub2" style="text-align:center;font-size:.84rem">'.$info.'</div>';
          }
          echo '</div>';
        } ?>
    </div>
  </div></section>
  <datalist id="itemkeys"><?php foreach($ITEM_KEYS as $k) echo '<option value="'.h($k).'">'; ?></datalist>
