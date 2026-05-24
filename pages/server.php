<?php
/* Page: server — trang riêng từng server với tabs nav + tin tức */
?>
<?php
  $sid = preg_replace('/[^a-z0-9_-]/i', '', $_GET['id'] ?? '');
  $modes = $CFG['modes'] ?? [];
  if ($sid === '' || !isset($modes[$sid])) {
    echo '<div class="phead"><h1>Server không tồn tại</h1><p><a href="?p=home" style="color:var(--green)">← Về trang chủ</a></p></div>';
    return;
  }
  $all = get_servers_status(false);
  $s = $all[$sid] ?? null;
  if (!$s) { echo '<div class="phead"><h1>Server không có data</h1></div>'; return; }

  $ip = $s['ip'] ?: ($CFG['server_ip'] ?? 'play.dogeland.vn');
  $features = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$s['features'])), fn($x)=>$x!==''));

  // Top 5 player của server theo playtime
  $top5 = function_exists('get_server_top') ? get_server_top($sid, 'playtime_sec', 5) : [];

  // Posts tagged với server (theo tên hoặc id) — admin chọn ở form đăng bài
  $serverPosts = [];
  try {
    $ps = db()->prepare("SELECT * FROM web_posts WHERE LOWER(server)=? OR LOWER(server)=? ORDER BY pinned DESC, id DESC LIMIT 20");
    $ps->execute([strtolower($s['name']), strtolower($sid)]);
    $serverPosts = $ps->fetchAll();
  } catch(Exception $e){}

  // Gallery
  $galleryUrls = function_exists('srv_gallery_arr')
    ? srv_gallery_arr(db()->query("SELECT gallery_json FROM web_servers WHERE server_id=" . db()->quote($sid))->fetchColumn() ?: '')
    : [];

  // Active tab
  $section = $_GET['s'] ?? 'about';
  $allowed = ['about','news','top','gallery'];
  if (!in_array($section, $allowed, true)) $section = 'about';

  // Hero banner image
  $heroImg = $s['banner'] ?: $s['image'];
  $accent = $s['accent'] ?: '#f2b631';
?>
<style>.srvpage{--srv-accent:<?=h($accent)?>}</style>

<div class="srvpage">
  <!-- HERO compact với stats inline -->
  <section class="srvhero2" <?= $heroImg ? 'style="background-image:linear-gradient(135deg,rgba(15,17,20,.6) 0%,rgba(15,17,20,.95) 100%),url('.h($heroImg).')"' : '' ?>>
    <div class="wrap">
      <a class="srvhero-back" href="?p=home">← Về trang chủ</a>
      <div class="srvhero2-grid">
        <div class="srvhero2-info">
          <div class="srvhero-status">
            <span class="srvdot <?=$s['online']?'on':'off'?>"></span>
            <?= $s['online'] ? 'ONLINE · '.(int)$s['count'].' đang chơi' : 'OFFLINE' ?>
            <?= $s['mc_version'] ? ' · MC '.h($s['mc_version']) : '' ?>
          </div>
          <h1><?=h($s['name'])?></h1>
          <?php if ($s['tagline']) echo '<p class="srvhero-tag">'.h($s['tagline']).'</p>'; ?>
          <div class="srvhero-cta">
            <div class="ipbar" style="margin:0;flex:1;min-width:260px;max-width:460px">
              <div class="info"><div class="l">IP Server</div><div class="v"><?=h($ip)?></div></div>
              <button class="copy" id="cpsrv" onclick="copyServerIp()">Sao chép</button>
            </div>
            <?php if ($s['discord_url']) echo '<a class="btn btn-ghost" href="'.h($s['discord_url']).'" target="_blank" rel="noopener" style="border-color:var(--srv-accent);color:var(--srv-accent)">💬 Discord</a>'; ?>
            <?php if ($IS_ADMIN) echo '<a class="btn btn-ghost" href="?p=admin&tab=servers&edit='.h($sid).'">✎ Sửa</a>'; ?>
          </div>
        </div>
        <!-- Stats card overlay -->
        <div class="srvhero2-stats">
          <div class="srvstat"><div class="srvstat-v"><?=(int)$s['count']?></div><div class="srvstat-l">Đang chơi</div></div>
          <div class="srvstat"><div class="srvstat-v"><?=count($top5)>0?count($top5):'—'?></div><div class="srvstat-l">Top player</div></div>
          <div class="srvstat"><div class="srvstat-v"><?=count($serverPosts)?></div><div class="srvstat-l">Tin tức</div></div>
          <div class="srvstat"><div class="srvstat-v"><?=count($galleryUrls)?></div><div class="srvstat-l">Gallery</div></div>
        </div>
      </div>
    </div>
  </section>

  <!-- Tabs nav -->
  <section class="srvtabs"><div class="wrap">
    <div class="srvtabs-bar">
      <a class="srvtab<?= $section==='about'?' on':'' ?>" href="?p=server&id=<?=h($sid)?>&s=about">📖 Giới thiệu</a>
      <a class="srvtab<?= $section==='news'?' on':'' ?>" href="?p=server&id=<?=h($sid)?>&s=news">📰 Tin tức <?php if($serverPosts) echo '<span class="srvtab-c">'.count($serverPosts).'</span>'; ?></a>
      <a class="srvtab<?= $section==='top'?' on':'' ?>" href="?p=server&id=<?=h($sid)?>&s=top">🏆 Top player</a>
      <a class="srvtab<?= $section==='gallery'?' on':'' ?>" href="?p=server&id=<?=h($sid)?>&s=gallery">🖼 Gallery <?php if($galleryUrls) echo '<span class="srvtab-c">'.count($galleryUrls).'</span>'; ?></a>
    </div>
  </div></section>

  <!-- BODY: nội dung theo tab -->
  <section style="padding:30px 0 60px"><div class="wrap srvbody3">
    <div class="srv-main3">
    <?php if ($section === 'about') { ?>
      <?php if ($s['description']) { ?>
        <div class="card srv-block">
          <h2>📖 Giới thiệu</h2>
          <div class="srv-desc"><?=h($s['description'])?></div>
        </div>
      <?php } else if($IS_ADMIN) { ?>
        <div class="card srv-block" style="border-style:dashed">
          <p style="color:var(--muted);margin:0">Chưa có mô tả. <a href="?p=admin&tab=servers&edit=<?=h($sid)?>" style="color:var(--green);font-weight:700">Thêm mô tả tại Admin →</a></p>
        </div>
      <?php } ?>

      <?php if ($features) { ?>
        <div class="card srv-block">
          <h2>✓ Đặc trưng server</h2>
          <ul class="srv-features">
            <?php foreach ($features as $f) echo '<li>'.h($f).'</li>'; ?>
          </ul>
        </div>
      <?php } ?>

      <?php if (!$s['description'] && !$features) { ?>
        <div class="empty">Server này chưa có nội dung giới thiệu.</div>
      <?php } ?>

    <?php } elseif ($section === 'news') { ?>
      <div class="srv-newshead">
        <h2>📰 Tin tức về <?=h($s['name'])?></h2>
        <?php if ($IS_ADMIN) echo '<a class="btn btn-green btn-sm" href="?p=admin&tab=posts">➕ Đăng bài cho server này</a>'; ?>
      </div>
      <?php if (!$serverPosts) { ?>
        <div class="card srv-block" style="text-align:center;border-style:dashed">
          <p style="color:var(--muted);margin:0 0 12px">Chưa có bài viết nào về server này.</p>
          <?php if ($IS_ADMIN) { ?>
            <a class="btn btn-ghost btn-sm" href="?p=admin&tab=posts">Đăng bài đầu tiên</a>
            <p class="sub2" style="margin-top:12px">Trong form admin, field <b>"Server"</b> nhập: <code><?=h($s['name'])?></code> hoặc <code><?=h($sid)?></code></p>
          <?php } else { ?>
            <p class="sub2">Admin sẽ đăng tin tức về sự kiện, cập nhật, hướng dẫn cho server này tại đây.</p>
          <?php } ?>
        </div>
      <?php } else { ?>
        <div class="feed">
          <?php foreach ($serverPosts as $po) echo post_card($po); ?>
        </div>
      <?php } ?>

    <?php } elseif ($section === 'top') { ?>
      <div class="srv-newshead">
        <h2>🏆 Top player <?=h($s['name'])?></h2>
        <a class="btn btn-green btn-sm" href="?p=top&srv=<?=h($sid)?>">Xem bảng xếp hạng đầy đủ →</a>
      </div>
      <?php if (!$top5) { ?>
        <div class="card srv-block" style="text-align:center;border-style:dashed">
          <p style="color:var(--muted);margin:0">Server chưa có data stats. Đợi player chơi + plugin sync.</p>
        </div>
      <?php } else { ?>
        <div class="card" style="padding:0;overflow:hidden">
          <?php $rk=0; foreach($top5 as $r){ $rk++; $medal=$rk<=3?'m'.$rk:'';
            echo '<div class="srv-toprow-big"><span class="lbrk '.$medal.'">'.$rk.'</span>'
                .'<img src="'.h($CFG['skin_api']).'/avatar/'.urlencode($r['name']).'/44" data-skin-user="'.h($r['name']).'" data-skin-size="44" onerror="skinFallback(this)" alt="">'
                .'<div class="srv-topbody"><div class="srv-topname">'.h($r['name']).'</div><div class="srv-topmeta">'.h(fmt_playtime((int)$r['val'])).' chơi</div></div>'
                .'</div>';
          } ?>
        </div>
      <?php } ?>

    <?php } elseif ($section === 'gallery') { ?>
      <div class="srv-newshead">
        <h2>🖼 Gallery <?=h($s['name'])?></h2>
        <?php if ($IS_ADMIN) echo '<a class="btn btn-green btn-sm" href="?p=admin&tab=servers&edit='.h($sid).'">+ Thêm ảnh</a>'; ?>
      </div>
      <?php if (!$galleryUrls) { ?>
        <div class="card srv-block" style="text-align:center;border-style:dashed">
          <p style="color:var(--muted);margin:0">Chưa có ảnh nào.<?php if($IS_ADMIN) echo ' Admin upload ảnh tại trang Quản lý Server.'; ?></p>
        </div>
      <?php } else { ?>
        <div class="srv-gallery">
          <?php foreach ($galleryUrls as $gurl) {
            echo '<a class="srv-galimg" href="'.h($gurl).'" target="_blank" rel="noopener" style="background-image:url('.h($gurl).')"></a>';
          } ?>
        </div>
      <?php } ?>
    <?php } ?>
    </div>

    <!-- Online players sidebar (luôn hiện nếu có) -->
    <?php if (!empty($s['players'])) { ?>
    <aside class="srv-onlbar">
      <h3>⚡ Đang online (<?=count($s['players'])?>)</h3>
      <div class="srv-onl">
        <?php foreach (array_slice($s['players'], 0, 16) as $pn) {
          echo '<div class="srv-onlrow"><img src="'.h($CFG['skin_api']).'/avatar/'.urlencode($pn).'/26" data-skin-user="'.h($pn).'" data-skin-size="26" onerror="skinFallback(this)" alt=""><span>'.h($pn).'</span></div>';
        } ?>
      </div>
      <?php if (count($s['players']) > 16) echo '<p class="sub2" style="margin:10px 0 0;text-align:center">... và '.(count($s['players'])-16).' người khác</p>'; ?>
    </aside>
    <?php } ?>
  </div></section>
</div>

<script>
const SRV_IP_<?=h(preg_replace('/[^a-z0-9]/i','_',$sid))?>=<?=json_encode($ip)?>;
function copyServerIp(){
  const ip=SRV_IP_<?=h(preg_replace('/[^a-z0-9]/i','_',$sid))?>;
  const b=document.getElementById('cpsrv');
  const d=()=>{if(b){b.classList.add('done');b.textContent='Đã chép';setTimeout(()=>{b.classList.remove('done');b.textContent='Sao chép'},1800)}toast('Đã sao chép IP: '+ip,'ok')};
  navigator.clipboard?navigator.clipboard.writeText(ip).then(d).catch(d):d();
}
</script>
