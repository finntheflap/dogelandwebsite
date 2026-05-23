<?php
/* Page: verify — extracted from index.php lines 3979-3986 */
?>
  <div class="phead"><div class="k">Xác minh email</div><h1><?= ($verify_msg[0]??'')==='ok'?'Thành công':'Xác minh'?></h1></div>
  <section style="padding-top:22px"><div class="authw"><div class="card authc" style="text-align:center">
    <img class="lg" src="?img=doge" alt="">
    <?php if(isset($verify_msg)) echo '<div class="flash '.$verify_msg[0].'" style="justify-content:center">'.h($verify_msg[1]).'</div>'; ?>
    <a class="btn btn-green btn-block" href="?p=home" style="margin-top:8px">Về trang chủ</a>
  </div></div></section>
