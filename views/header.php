<?php
/* ============================================================================
   VIEW HEADER — VIEW init + DOCTYPE + <head> + nav + <header>
   ========================================================================== */

$CSRF=csrf_token();
$nav=[['home','Trang chủ'],['events','Tin tức'],['guide','Cẩm nang'],['rules','Nội quy'],['shop','Cửa hàng'],['top','Xếp hạng'],['topup','Nạp thẻ']];
/* Các trang con thuộc nhóm "Cửa hàng" (để highlight nav + sub-tab dùng chung) */
$SHOP_PAGES=['shop','auction','ranks','market'];
/* Gợi ý mã item cho ô icon (đấu giá & chợ trời) */
$ITEM_KEYS=['diamond','diamond_sword','diamond_pickaxe','diamond_helmet','diamond_chestplate','diamond_leggings','diamond_boots',
  'netherite_ingot','netherite_sword','netherite_pickaxe','netherite_helmet','netherite_chestplate',
  'golden_apple','enchanted_golden_apple','golden_carrot','emerald','emerald_block','gold_ingot','iron_ingot','iron_sword',
  'elytra','totem_of_undying','nether_star','dragon_egg','dragon_breath','ender_pearl','ender_eye','experience_bottle',
  'enchanted_book','book','name_tag','spawner','beacon','shield','bow','crossbow','trident','fishing_rod',
  'cooked_beef','bread','cake','apple','wheat','carrot','potato','sugar','honey_bottle',
  'oak_log','oak_planks','stone','cobblestone','obsidian','tnt','redstone','lapis_lazuli','coal','torch',
  'potion','splash_potion','lingering_potion','bucket','water_bucket','lava_bucket','milk_bucket','saddle','lead'];
if($IS_ADMIN) $nav[]=['admin','Quản trị'];
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DOGELAND NETWORK — Server Minecraft Việt Nam</title>
<link rel="icon" type="image/png" href="?img=doge">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap&subset=vietnamese,latin" rel="stylesheet">
<link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
<div class="bgfx"></div><div class="vig"></div>

<header id="hdr">
  <div class="wrap"><nav class="nav">
    <a class="brand" href="?p=home"><img src="?img=doge" alt=""><b>DOGELAND<span> NETWORK</span></b></a>
    <div class="links">
      <?php foreach($nav as $n){ $act_on = ($p===$n[0]) || ($n[0]==='shop' && in_array($p,$SHOP_PAGES,true)); $cls=($act_on?' class="on"':''); echo '<a href="?p='.$n[0].'"'.$cls.'>'.$n[1].'</a>'; } ?>
    </div>
    <div class="ncta">
      <?php if($user){ $w=wallet($user); $av=$CFG['skin_api'].'/avatar/'.urlencode($user).'/'; ?>
        <div class="ndrop" id="ndrop">
          <button class="nbell" id="nbell" type="button" aria-label="Thông báo" onclick="toggleNotif()">
            <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
            <span class="nbadge" id="nbadge" style="display:none">0</span>
          </button>
          <div class="npanel" id="npanel">
            <div class="nph"><span>Thông báo</span><button type="button" class="nread" onclick="markAllRead()">Đánh dấu đã đọc</button></div>
            <div class="nlist" id="nlist"><div class="nempty">Đang tải…</div></div>
          </div>
        </div>
        <div class="udrop">
          <button class="uchip" onclick="document.getElementById('udd').classList.toggle('on')"><img src="<?=h($av)?>30" data-skin-user="<?=h($user)?>" data-skin-size="30" onerror="skinFallback(this)" alt=""><span class="un"><?=h($user)?></span><span class="car">▾</span></button>
          <div class="udd <?= $IS_ADMIN?'isadmin':'' ?>" id="udd">
            <div class="uhead"><img src="<?=h($av)?>48" data-skin-user="<?=h($user)?>" data-skin-size="48" onerror="skinFallback(this)" alt=""><div><div class="un2"><?=h($user)?></div><div class="urole <?= is_owner($user)?'owner':($IS_ADMIN?'adm':'') ?>"><?= is_owner($user)?'Chủ sở hữu':($IS_ADMIN?'Quản trị viên':'Người chơi') ?></div></div></div>
            <div class="ubal"><span class="dgc"><span class="wd"></span> <?=doge_short(doge_balance($user))?></span><a href="?p=topup" class="ubal-top">+ Nạp</a></div>
            <form class="giftmini" method="post" action="?p=profile">
              <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="gift_redeem"><input type="hidden" name="from" value="<?=h($p)?>">
              <input name="code" placeholder="Nhập Gift Code…" autocomplete="off" maxlength="48">
              <button type="submit" title="Đổi quà">🎁</button>
            </form>
            <div class="usep"></div>
            <a href="?p=profile">Hồ sơ của tôi</a>
            <a href="?p=tickets">Ticket hỗ trợ</a>
            <a href="?p=topup">Nạp thẻ</a>
            <?php if($IS_ADMIN){ $oc=open_tickets(); ?>
            <div class="usep"></div><div class="ulabel">Khu quản trị</div>
            <a href="?p=admin&tab=dash">Admin Mode</a>
            <a href="?p=admin&tab=tickets">Hộp ticket<?php if($oc) echo '<span class="badge2">'.$oc.'</span>'; ?></a>
            <a href="?p=admin&tab=announce">Thông báo khẩn</a>
            <?php if(is_owner($user)) echo '<a href="?p=admin&tab=staff">Phân quyền admin</a>'; ?>
            <?php } ?>
            <div class="usep"></div>
            <a href="?p=logout" class="lo">Đăng xuất</a>
          </div>
        </div>
      <?php } else { ?>
        <a class="btn btn-ghost btn-sm" href="?p=login">Đăng nhập</a>
        <a class="btn btn-green btn-sm" href="?p=register">Đăng ký</a>
      <?php } ?>
      <button class="burger" onclick="document.getElementById('mm').classList.toggle('on')">☰</button>
    </div>
  </nav></div>
  <div class="mm" id="mm">
    <?php foreach($nav as $n) echo '<a href="?p='.$n[0].'">'.$n[1].'</a>'; ?>
    <a href="?p=info">Thông tin</a>
    <?php if($user){ echo '<a href="?p=profile">Hồ sơ ('.h($user).')</a><a href="?p=logout">Đăng xuất</a>'; } else { echo '<a href="?p=login">Đăng nhập</a><a href="?p=register">Đăng ký</a>'; } ?>
    <div class="mm-soc">
      <?php foreach(($CFG['socials']??[]) as $k=>$u){ if(!$u) continue; echo '<a class="nsoc-a '.h($k).'" href="'.h($u).'" target="_blank" rel="noopener" aria-label="'.h($k).'">'.ic($k).'</a>'; } ?>
    </div>
  </div>
</header>
