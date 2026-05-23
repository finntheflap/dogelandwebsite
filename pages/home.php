<?php
/* Page: home — extracted from index.php lines 2600-2631 */
?>
  <section class="hero">
    <div class="wrap"><div class="hgrid">
      <div>
        <span class="badge"><span class="dot"></span> Server đang mở · Mùa 5</span>
        <h1>Bước vào thế giới <span class="g">DOGELAND</span></h1>
        <div class="ipbar"><div class="info"><div class="l">IP Server (Java &amp; Bedrock)</div><div class="v"><?=h($CFG['server_ip'])?></div></div>
          <button class="copy" id="cp" onclick="copyIp()">Sao chép</button></div>
        <p class="lead">Server sinh tồn — minigame Việt Nam. Tham gia cùng hơn 50.000 người chơi, hệ thống pet, kinh tế &amp; hàng trăm sự kiện mỗi tháng.</p>
        <div class="hact"><a class="btn btn-green" href="?p=topup">Nạp thẻ ngay</a><a class="btn btn-ghost" href="?p=shop">Vào cửa hàng</a></div>
      </div>
      <div class="hart"><div class="orb"></div><div class="pic"><img src="?img=world" alt="Dogeland"></div></div>
    </div>
    <div class="stats">
      <div class="stat"><div class="n" id="live">0</div><div class="l">Đang online</div></div>
      <div class="stat"><div class="n" data-c="52340" data-s="+">0</div><div class="l">Thành viên</div></div>
      <div class="stat"><div class="n" data-c="99" data-s="%">0</div><div class="l">Uptime</div></div>
      <div class="stat"><div class="n" data-c="312" data-s="+">0</div><div class="l">Sự kiện</div></div>
    </div></div>
  </section>

  <section><div class="wrap">
    <div class="shead"><div class="k">Bảng tin</div><h2>Tin tức</h2><p>Sự kiện, thông báo và bản cập nhật mới nhất từ Dogeland Network.</p></div>
    <?php $posts=get_posts(['event','update','news'],12); if(!$posts) echo '<div class="empty">Chưa có bài viết nào.</div>'; else { echo '<div class="feed">'; foreach($posts as $po) echo post_card($po); echo '</div>'; echo '<div style="text-align:center;margin-top:22px"><a class="btn btn-ghost" href="?p=events">Xem tất cả →</a></div>'; } ?>
  </div></section>

  <div class="band">
    <h2>Sẵn sàng phiêu lưu chưa?</h2>
    <p>Sao chép IP, mở Minecraft và gia nhập Dogeland Network ngay hôm nay.</p>
    <div class="ipbar"><div class="info"><div class="l">IP Server</div><div class="v"><?=h($CFG['server_ip'])?></div></div><button class="copy" id="cp2" onclick="copyIp(2)">Sao chép</button></div>
  </div>
