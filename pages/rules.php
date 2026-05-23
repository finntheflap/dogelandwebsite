<?php
/* Page: rules — extracted from index.php lines 2716-2723 */
?>
  <div class="phead"><div class="k">Nội quy</div><h1>📜 Nội quy server</h1><p>Đọc kỹ trước khi chơi. Vi phạm có thể bị mute, kick hoặc ban tài khoản.</p>
    <?php if($IS_ADMIN) echo '<div style="margin-top:14px"><a class="btn btn-ghost btn-sm" href="?p=admin&tab=posts">➕ Cập nhật nội quy</a></div>'; ?>
  </div>
  <section style="padding-top:18px"><div class="wrap">
    <?php $posts=get_posts('rules'); if(!$posts) echo '<div class="empty">Chưa có nội quy nào được đăng. Admin có thể thêm từ trang Quản trị.</div>'; else { echo '<div class="feed">'; foreach($posts as $po) echo post_card($po); echo '</div>'; } ?>
  </div></section>
