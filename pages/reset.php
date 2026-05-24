<?php
/* Page: reset — form nhập password mới sau khi click link trong mail */
?>
<?php
  $token = preg_replace('/[^a-f0-9]/', '', $_GET['token'] ?? '');
  $valid = false; $errMsg = '';
  if ($token === '' || strlen($token) !== 64) {
    $errMsg = 'Link không hợp lệ.';
  } else {
    try {
      $st = db()->prepare("SELECT * FROM web_password_reset WHERE token=? LIMIT 1");
      $st->execute([$token]); $row = $st->fetch();
      if (!$row) $errMsg = 'Link không tồn tại hoặc đã hết hiệu lực.';
      elseif ($row['used']) $errMsg = 'Link reset đã được sử dụng rồi.';
      elseif ($row['expires'] < ms()) $errMsg = 'Link reset đã hết hạn (quá 1 giờ).';
      else $valid = true;
    } catch (Exception $e) { $errMsg = 'Lỗi xử lý.'; }
  }
?>
  <div class="phead"><div class="k">Tài khoản</div><h1>Đặt mật khẩu mới</h1></div>
  <section style="padding-top:22px"><div class="authw"><div class="card authc">
    <img class="lg" src="?img=doge" alt="">
    <?php if (!$valid) { ?>
      <h2>Link không hợp lệ</h2>
      <div class="flash error" style="margin-bottom:18px;justify-content:center"><?=h($errMsg)?></div>
      <a class="btn btn-green btn-block" href="?p=forgot">Yêu cầu link mới</a>
      <div class="aalt"><a href="?p=login">← Quay lại đăng nhập</a></div>
    <?php } else { ?>
      <h2>Đặt mật khẩu mới</h2>
      <p class="s">Cho tài khoản <b><?=h($row['username'])?></b></p>
      <form method="post" action="?p=reset">
        <input type="hidden" name="csrf" value="<?=$CSRF?>">
        <input type="hidden" name="act" value="reset_password">
        <input type="hidden" name="token" value="<?=h($token)?>">
        <div class="field">
          <label>Mật khẩu mới</label>
          <input name="password" type="password" placeholder="Tối thiểu 6 ký tự" required minlength="6" autofocus>
        </div>
        <div class="field">
          <label>Nhập lại mật khẩu mới</label>
          <input name="password2" type="password" placeholder="••••••••" required minlength="6">
        </div>
        <button class="btn btn-green btn-block" type="submit">Đổi mật khẩu</button>
      </form>
      <div class="afoot" style="margin-top:14px;font-size:.82rem;color:var(--muted);line-height:1.5">
        Link này chỉ dùng được 1 lần. Sau khi đổi pass, bạn sẽ được redirect về trang đăng nhập.
      </div>
    <?php } ?>
  </div></div></section>
