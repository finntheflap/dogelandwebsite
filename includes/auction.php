<?php
/* ============================================================================
   AUCTION — kết toán phiên đấu giá hết hạn (lazy settle)
   ========================================================================== */

/* Kết toán các phiên đấu giá đã hết giờ (gọi lười khi tải trang Đấu Giá / khi đặt giá).
   - Có người đấu  → bán: người bán nhận giá chốt, vật phẩm vào kho người thắng (+RCON nếu cần).
   - Không ai đấu  → hết hạn: HOÀN phí mở; nếu lấy từ kho thì TRẢ vật phẩm về kho người bán. */
function auc_settle_due(){
  try{
    $now=ms();
    $due=db()->query("SELECT * FROM web_auctions WHERE status='active' AND end_at<=$now")->fetchAll();
    foreach($due as $a){
      $qty=max(1,(int)($a['qty']??1));
      $sold = $a['top_bidder']!=='';
      // Claim trước: chỉ request nào thực sự lật status='active'→'sold|expired'
      // mới được trả tiền / phát vật phẩm. Hai page-load song song sẽ chỉ có 1
      // rowCount==1, tránh trả thưởng & spawn item 2 lần.
      $claim = db()->prepare("UPDATE web_auctions SET status=? WHERE id=? AND status='active'");
      $claim->execute([$sold?'sold':'expired', $a['id']]);
      if($claim->rowCount()!==1) continue;
      if($sold){
        doge_add($a['seller'],(int)$a['price']);
        db()->prepare("INSERT INTO web_inventory(username,mode,item,item_key,qty,color,image) VALUES(?,?,?,?,?, '#f2b631', ?)")
            ->execute([$a['top_bidder'],$a['mode']??'',$a['item'],$a['item_key'],$qty,$a['image']??'']);
        $gw=rcon_arg($a['top_bidder'],32); $gk=rcon_arg($a['item_key'],48);
        if($gw!=='' && $gk!=='') rcon_queue('give '.$gw.' minecraft:'.$gk.' '.(int)$qty, 'AUCTION');
        notify($a['top_bidder'],'gift','Bạn đã thắng đấu giá 🏆','"'.$a['item'].'" với '.doge_short((int)$a['price']).' — đã vào kho của bạn.','?p=auction','SYSTEM');
        notify($a['seller'],'gift','Phiên đấu giá đã bán 💰','"'.$a['item'].'" bán cho '.$a['top_bidder'].' (+'.doge_short((int)$a['price']).').','?p=auction','SYSTEM');
      } else {
        doge_add($a['seller'],(int)$a['listing_fee']); // hoàn phí mở
        if(!empty($a['from_inv'])){ // trả vật phẩm về kho người bán
          db()->prepare("INSERT INTO web_inventory(username,mode,item,item_key,qty,color,image) VALUES(?,?,?,?,?, '#f2b631', ?)")
              ->execute([$a['seller'],$a['mode']??'',$a['item'],$a['item_key'],$qty,$a['image']??'']);
        }
        notify($a['seller'],'info','Phiên đấu giá hết hạn','"'.$a['item'].'" không có ai đấu — đã hoàn phí mở'.(!empty($a['from_inv'])?' và trả vật phẩm về kho':'').'.','?p=auction','SYSTEM');
      }
    }
  }catch(Exception $e){}
}
