<?php
/* Page: shop — extracted from index.php lines 3734-3745 */
?>
<?php
  $bal = $user ? doge_balance($user) : null;
?>
  <div class="phead"><div class="k">Cửa hàng</div><h1>Cửa hàng Dogeland</h1><p>Mọi giao dịch đều dùng <b style="color:#f7c948"><?=h($CFG['doge_label']??'Dogecoin')?></b>. Chọn một khu vực bên dưới.</p>
    <?php if($user) echo '<div class="balbar">Số dư: <b class="dogechip">'.number_format($bal,0,',','.').'</b> · <a href="?p=topup" style="color:var(--green);font-weight:700">Nạp thêm</a></div>'; ?>
  </div>
  <section style="padding-top:22px"><div class="wrap"><div class="shubgrid">
    <a class="shub au" href="?p=auction"><div class="em">🔨</div><h3>Đấu Giá</h3><p>Mở phiên đấu giá vật phẩm của bạn, hoặc trả giá để giành món đồ hiếm. Người trả cao nhất khi hết giờ sẽ thắng.</p><div class="go">Vào đấu giá →</div></a>
    <a class="shub rk" href="?p=ranks"><div class="em">🎖️</div><h3>Mua Rank</h3><p>Rank cho toàn server &amp; rank riêng cho <b>Sword Dark Online</b>. Nhận quyền lợi, lệnh áp dụng tự động in-game.</p><div class="go">Xem các rank →</div></a>
    <a class="shub mk" href="?p=market"><div class="em">🛒</div><h3>Chợ Trời</h3><p>Người chơi mua bán vật phẩm trực tiếp với nhau bằng <?=h($CFG['doge_label']??'Dogecoin')?>. Tìm món bạn cần, hoặc đăng bán đồ thừa.</p><div class="go">Vào chợ trời →</div></a>
  </div></div></section>
