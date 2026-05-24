<?php
/* Page: forgot — nhập email/username để nhận mail reset password */
?>
  <div class="phead"><div class="k">Tài khoản</div><h1>Quên mật khẩu</h1></div>
  <section style="padding-top:22px"><div class="authw"><div class="card authc">
    <img class="lg" src="?img=doge" alt="">
    <h2>Khôi phục mật khẩu</h2>
    <p class="s">Nhập email hoặc tên tài khoản — link đặt lại mật khẩu sẽ được gửi tới email đăng ký.</p>
    <form method="post" action="?p=forgot">
      <input type="hidden" name="csrf" value="<?=$CSRF?>">
      <input type="hidden" name="act" value="forgot_password">
      <div class="field">
        <label>Email hoặc tên tài khoản (IGN)</label>
        <input name="identifier" placeholder="vd: ban@email.com hoặc DogeMaster99" required autofocus>
      </div>
      <button class="btn btn-green btn-block" type="submit">Gửi link reset</button>
    </form>
    <div class="aalt"><a href="?p=login">← Quay lại đăng nhập</a></div>
    <div class="afoot" style="margin-top:14px;font-size:.82rem;color:var(--muted);line-height:1.5">
      <b>Lưu ý:</b> link reset chỉ có hiệu lực trong 1 giờ và chỉ dùng được 1 lần.
      Nếu không nhận được mail, check cả thư mục <b>Spam</b>.
    </div>
  </div></div></section>
