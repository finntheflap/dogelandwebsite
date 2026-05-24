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
  // Inventory grouped by server từ DogelandSync — chỉ server trong config + locked=0
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
  <div class="phead"><div class="k">Cửa hàng</div><h1>🛒 Chợ Trời</h1><p>Người chơi mua bán trực tiếp bằng <b style="color:#f7c948"><?=h($CFG['doge_label']??'Dogecoin')?></b>. Server thu phí <b><?=$fp?>%</b> mỗi giao dịch.</p>
    <?php if($user) echo '<div class="balbar">Số dư: <b class="dogechip">'.number_format($bal,0,',','.').'</b></div>'; ?>
  </div>
  <section style="padding-top:18px"><div class="wrap">
    <div class="shopnav">
      <a href="?p=auction">🔨 Đấu giá</a><a href="?p=ranks">🎖️ Mua rank</a><a href="?p=market" class="on">🛒 Chợ trời</a>
    </div>

    <?php if($user){ ?>
    <div class="sellbox">
      <h3>🏷️ Đăng bán vật phẩm <span class="sub2" style="font-weight:600;font-size:.85rem">(chọn item từ kho thật · phí <?=$fp?>% khi bán được)</span></h3>
      <?php if(!$totalItems){ ?>
        <div class="sub2" style="padding:18px;background:rgba(255,255,255,.02);border-radius:10px;text-align:center">Kho thật của bạn đang trống. Vào game chơi để có item, plugin sẽ tự sync inv lên web.</div>
      <?php } else { ?>

      <!-- Server tabs -->
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

      <!-- Form đăng bán -->
      <form method="post" action="?p=market" class="aucform" id="mkForm" onsubmit="return confirmMk()">
        <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="market_list">
        <input type="hidden" name="inv_id" id="mkInvId" value="">
        <div class="aucform-preview" id="mkPreview"><span class="sub2">Click vào item để chọn →</span></div>
        <div class="aucform-fields" style="grid-template-columns:1fr 1fr 2fr auto">
          <label class="field"><span>Số lượng</span><input name="qty" id="mkQty" type="number" min="1" value="1" required></label>
          <label class="field"><span>Giá (<?=h($CFG['doge_symbol']??'Ð')?>)</span><input name="price" type="number" min="1" value="100" required></label>
          <label class="field"><span>Mô tả (optional)</span><input name="description" maxlength="255" placeholder="vd: enchant Sắc bén V…"></label>
          <button class="btn btn-gold" type="submit" id="mkSubmit" disabled>Đăng bán</button>
        </div>
      </form>
      <p class="sub2" style="margin-top:10px">💡 Item ✨ có enchant. Plugin lock item trong kho để tránh mất khi bạn join game (escrow). Khi có người mua, doge tự về ví bạn (-<?=$fp?>% phí sàn).</p>

      <script>
      (function(){
        const tabs=document.querySelectorAll('.aucsrv-tab');
        const panes=document.querySelectorAll('.aucinv-pane');
        const items=document.querySelectorAll('.aucinv-it');
        const preview=document.getElementById('mkPreview');
        const invId=document.getElementById('mkInvId');
        const qtyIn=document.getElementById('mkQty');
        const submitBtn=document.getElementById('mkSubmit');
        let selectedName='', selectedMaxQty=1;
        tabs.forEach(t=>t.addEventListener('click',()=>{
          tabs.forEach(x=>x.classList.remove('on')); t.classList.add('on');
          const sid=t.dataset.srv;
          panes.forEach(p=>p.classList.toggle('on', p.dataset.srvPane===sid));
          items.forEach(i=>i.classList.remove('selected'));
          invId.value=''; preview.innerHTML='<span class="sub2">Click vào item để chọn →</span>'; submitBtn.disabled=true;
        }));
        items.forEach(it=>it.addEventListener('click',()=>{
          items.forEach(x=>x.classList.remove('selected'));
          it.classList.add('selected');
          invId.value=it.dataset.invId;
          selectedName=it.dataset.name;
          selectedMaxQty=parseInt(it.dataset.qty,10)||1;
          qtyIn.max=selectedMaxQty; qtyIn.value=Math.min(selectedMaxQty, parseInt(qtyIn.value,10)||1);
          preview.innerHTML='<img src="'+it.dataset.img+'" onerror="this.src=\'?img=doge\'" alt=""><div><div class="nm">'+selectedName+'</div><div class="sub2">Có '+selectedMaxQty+' trong kho — chọn số lượng ≤ '+selectedMaxQty+'</div></div>';
          submitBtn.disabled=false;
        }));
        window.confirmMk=function(){
          if(!invId.value){alert('Chưa chọn item.');return false;}
          const q=parseInt(qtyIn.value,10);
          if(q<1||q>selectedMaxQty){alert('Số lượng phải 1-'+selectedMaxQty);return false;}
          return confirm('Đăng bán '+q+'× "'+selectedName+'"?');
        };
      })();
      </script>
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
