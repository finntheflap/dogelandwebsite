<?php
/* Page: login — extracted from index.php lines 3945-3959 */
?>
  <div class="phead"><div class="k">Tài khoản</div><h1>Đăng nhập</h1></div>
  <section style="padding-top:22px"><div class="authw"><div class="card authc">
    <img class="lg" src="?img=doge" alt=""><h2>Đăng nhập</h2><p class="s">Dùng tài khoản game (AuthMe) để đăng nhập</p>
    <?php if(!empty($CFG['dev_mode'])) echo '<div class="flash ok" style="font-size:.83rem;margin-bottom:18px">Demo (DEV): <b>DogeAdmin</b> / <b>admin123</b> (admin) · <b>Player</b> / <b>123456</b></div>'; ?>
    <form method="post" action="?p=login">
      <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="login">
      <div class="field"><label>Tên tài khoản (IGN)</label><input name="username" placeholder="VD: DogeMaster99" required></div>
      <div class="field"><label>Mật khẩu</label><input name="password" type="password" placeholder="••••••••" required></div>
      <label class="rmbline"><input type="checkbox" name="remember" value="1"> <span>Ghi nhớ tài khoản này trong 60 ngày</span></label>
      <button class="btn btn-green btn-block" type="submit">Đăng nhập</button>
    </form>
    <div class="aalt"><a href="?p=forgot">Quên mật khẩu?</a></div>
    <div class="afoot">Chưa có tài khoản? <a href="?p=register">Đăng ký ngay</a></div>
  </div></div></section>
