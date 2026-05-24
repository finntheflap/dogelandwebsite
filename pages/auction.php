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
  // Inventory grouped by server từ DogelandSync — chỉ server có trong config + locked=0
  $myInvBySrv = [];
  if ($user) {
    $modes = $CFG['modes'] ?? [];
    $validMs = array_keys($modes);
    if ($validMs) {
      try {
        $ph = implode(',', array_fill(0, count($validMs), '?'));
        $is = db()->prepare("SELECT * FROM web_inventory WHERE username=? AND mode IN ($ph) AND locked=0 ORDER BY mode, section, slot, id");
        $is->execute(array_merge([$user], $validMs));
        foreach ($is->fetchAll() as $r) $myInvBySrv[$r['mode']][] = $r;
      } catch(Exception $e){}
    }
  }
  $totalItems = array_sum(array_map('count', $myInvBySrv));
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
      <h3>🆕 Mở phiên đấu giá <span class="sub2" style="font-weight:600;font-size:.85rem">(chọn item từ kho thật · phí <?=doge_short($fee)?>, hoàn nếu hết giờ không ai đấu)</span></h3>
      <?php if(!$totalItems){ ?>
        <div class="sub2" style="padding:18px;background:rgba(255,255,255,.02);border-radius:10px;text-align:center">Kho thật của bạn đang trống. Vào game chơi để có item, plugin sẽ tự sync inv lên web.</div>
      <?php } else { ?>

      <!-- Server tabs (chọn server lấy item) -->
      <div class="aucsrv-tabs">
        <?php $first=true; foreach($myInvBySrv as $sid=>$items){
          $sname = $modes[$sid] ?? $sid;
          echo '<button type="button" class="aucsrv-tab'.($first?' on':'').'" data-srv="'.h($sid).'">'.h($sname).' <span class="aucsrv-c">'.count($items).'</span></button>';
          $first=false;
        } ?>
      </div>

      <!-- Item grid per server -->
      <?php $first=true; foreach($myInvBySrv as $sid=>$items){ ?>
        <div class="aucinv-pane<?=$first?' on':''?>" data-srv-pane="<?=h($sid)?>">
          <div class="aucinv-grid">
            <?php foreach($items as $it){
              $img = item_img($it['image']??'', $it['item_key']??$it['material']??'');
              $qty = (int)($it['qty']??1);
              $displayName = $it['display_name'] ?? $it['item'] ?? $it['material'] ?? '?';
              $cleanName = preg_replace('/§[0-9a-frlokmn]/i','',$displayName);
              $enchants = $it['enchants'] ?? '';
              $cls = 'aucinv-it'.($enchants?' has-ench':'');
              echo '<button type="button" class="'.$cls.'" data-inv-id="'.(int)$it['id'].'" data-qty="'.$qty.'" data-name="'.h($cleanName).'" data-img="'.h($img).'" title="'.h($cleanName).' ×'.$qty.'">'
                  .'<img src="'.h($img).'" onerror="this.onerror=null;this.src=\'?img=doge\'" alt="">'
                  .($qty>1?'<span class="aucinv-qy">'.$qty.'</span>':'')
                  .'<span class="aucinv-nm">'.h(preg_replace('/_/',' ',strtolower($it['material']?:$cleanName))).'</span>'
                  .'</button>';
            } ?>
          </div>
        </div>
      <?php $first=false; } ?>

      <!-- Form mở phiên (item đã chọn) -->
      <form method="post" action="?p=auction" class="aucform" id="aucForm" onsubmit="return confirmAuc()">
        <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="auc_open">
        <input type="hidden" name="inv_id" id="aucInvId" value="">
        <div class="aucform-preview" id="aucPreview"><span class="sub2">Click vào item để chọn →</span></div>
        <div class="aucform-fields">
          <label class="field"><span>Số lượng</span><input name="qty" id="aucQty" type="number" min="1" value="1" required></label>
          <label class="field"><span>Giá khởi điểm (<?=h($CFG['doge_symbol']??'Ð')?>)</span><input name="start_price" type="number" min="1" value="100" required></label>
          <label class="field"><span>Thời lượng (giờ)</span><input name="hours" type="number" min="0.5" step="0.5" value="24" required></label>
          <button class="btn btn-gold" type="submit" id="aucSubmit" disabled>Mở phiên đấu giá</button>
        </div>
      </form>
      <p class="sub2" style="margin-top:10px">💡 Item có ✨ là có enchant. Lock đảm bảo item không bị mất khi bạn join game lại (DogelandSync handle escrow).</p>

      <script>
      (function(){
        const tabs = document.querySelectorAll('.aucsrv-tab');
        const panes = document.querySelectorAll('.aucinv-pane');
        const items = document.querySelectorAll('.aucinv-it');
        const preview = document.getElementById('aucPreview');
        const invId = document.getElementById('aucInvId');
        const qtyIn = document.getElementById('aucQty');
        const submitBtn = document.getElementById('aucSubmit');
        let selectedName = '', selectedMaxQty = 1;
        tabs.forEach(t=>t.addEventListener('click',()=>{
          tabs.forEach(x=>x.classList.remove('on')); t.classList.add('on');
          const sid = t.dataset.srv;
          panes.forEach(p=>p.classList.toggle('on', p.dataset.srvPane===sid));
          // Clear selection khi đổi server
          items.forEach(i=>i.classList.remove('selected'));
          invId.value=''; preview.innerHTML='<span class="sub2">Click vào item để chọn →</span>'; submitBtn.disabled=true;
        }));
        items.forEach(it=>it.addEventListener('click',()=>{
          items.forEach(x=>x.classList.remove('selected'));
          it.classList.add('selected');
          invId.value = it.dataset.invId;
          selectedName = it.dataset.name;
          selectedMaxQty = parseInt(it.dataset.qty,10) || 1;
          qtyIn.max = selectedMaxQty; qtyIn.value = Math.min(selectedMaxQty, parseInt(qtyIn.value,10)||1);
          preview.innerHTML = '<img src="'+it.dataset.img+'" onerror="this.src=\'?img=doge\'" alt=""><div><div class="nm">'+selectedName+'</div><div class="sub2">Có '+selectedMaxQty+' trong kho — chọn số lượng đấu giá ≤ '+selectedMaxQty+'</div></div>';
          submitBtn.disabled=false;
        }));
        window.confirmAuc = function(){
          if (!invId.value) { alert('Chưa chọn item.'); return false; }
          const q = parseInt(qtyIn.value,10);
          if (q < 1 || q > selectedMaxQty) { alert('Số lượng phải từ 1 đến '+selectedMaxQty); return false; }
          return confirm('Mở phiên đấu giá '+q+'× "'+selectedName+'"? Sẽ tạm trừ <?=$fee?> <?=h($CFG['doge_label']??'Dogecoin')?> và giữ item trong kho.');
        };
      })();
      </script>
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
