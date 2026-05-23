<?php
/* Page: info — extracted from index.php lines 2726-2752 */
?>
  <div class="phead"><div class="k">Thông tin</div><h1>Thông tin Server</h1></div>
  <section style="padding-top:18px"><div class="wrap">
    <div class="about">
      <div class="pic"><img src="?img=world" alt=""></div>
      <div>
        <div class="shead" style="text-align:left;margin:0 0 8px"><div class="k">Chào mừng đến Dogeland</div></div>
        <h3>Vùng đất sinh tồn của cộng đồng Việt</h3>
        <p>Dogeland là thế giới Minecraft xây dựng riêng cho người chơi Việt — nơi bạn xây nhà, đi mỏ, làm trang trại, buôn bán và phiêu lưu cùng bạn bè. Bản đồ custom rộng lớn, nhiều khu vực bí ẩn chờ khám phá.</p>
        <p>Server tối ưu chống lag, online ổn định 24/7, đội ngũ Admin hỗ trợ tận tình qua Discord.</p>
        <div class="chips"><span class="chip">Bản đồ custom</span><span class="chip">Không lag</span><span class="chip">Online 24/7</span><span class="chip">Java &amp; Bedrock</span></div>
      </div>
    </div>
    <div class="shead"><div class="k">Tính năng nổi bật</div><h2>Có gì trong Dogeland?</h2></div>
    <div class="grid3">
      <?php
      $feats=[['#67c96a','Sinh tồn','Bản đồ rộng, claim đất chống griefer, bảo vệ tài sản 24/7.'],
              ['#c98a4a','Pet &amp; Mount','Thu phục thú cưng, nâng cấp kỹ năng và chiến đấu cùng bạn.'],
              ['#56cfd6','Kinh tế','Mua bán qua chợ &amp; đấu giá, mở shop riêng, làm giàu.'],
              ['#e0584a','Minigame &amp; PvP','BedWars, SkyWars, Survival Games — đua top mỗi tuần.'],
              ['#9aa0a6','Anti-cheat','Hệ thống chống hack chuyên nghiệp, đảm bảo công bằng.'],
              ['#f2b631','Cộng đồng','Discord sôi động, Admin thân thiện, sự kiện liên tục.']];
      foreach($feats as $f) echo '<div class="card feat"><div class="ic"><span style="background:'.$f[0].'"></span></div><h3>'.$f[1].'</h3><p>'.$f[2].'</p></div>';
      ?>
    </div>
  </div></section>
