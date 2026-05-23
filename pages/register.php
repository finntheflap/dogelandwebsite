<?php
/* Page: register — extracted from index.php lines 3962-3976 */
?>
  <div class="phead"><div class="k">Tài khoản</div><h1>Đăng ký</h1></div>
  <section style="padding-top:22px"><div class="authw"><div class="card authc">
    <img class="lg" src="?img=doge" alt=""><h2>Tạo tài khoản</h2><p class="s">Đăng ký để chơi game &amp; nạp thẻ — cần xác minh email</p>
    <form method="post" action="?p=register">
      <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="register">
      <div class="field"><label>Tên tài khoản (IGN)</label><input name="username" placeholder="3-16 ký tự, chữ/số/_" required></div>
      <div class="field"><label>Email</label><input name="email" type="email" placeholder="ban@email.com" required></div>
      <div class="field"><label>Mật khẩu</label><input name="password" type="password" placeholder="Tối thiểu 6 ký tự" required></div>
      <div class="field"><label>Nhập lại mật khẩu</label><input name="password2" type="password" placeholder="••••••••" required></div>
      <button class="btn btn-green btn-block" type="submit">Đăng ký</button>
    </form>
    <div class="afoot">Đã có tài khoản? <a href="?p=login">Đăng nhập</a></div>
  </div></div></section>
