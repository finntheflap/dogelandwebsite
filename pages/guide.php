<?php
/* Page: guide — extracted from index.php lines 2685-2713 */
?>
  <div class="phead"><div class="k">Cẩm nang</div><h1>📖 Cẩm nang Dogeland</h1><p>Cách chơi, mẹo và mọi thứ bạn cần biết để bắt đầu hành trình.</p>
    <?php if($IS_ADMIN) echo '<div style="margin-top:14px"><a class="btn btn-ghost btn-sm" href="?p=admin&tab=posts">➕ Đăng bài cẩm nang</a></div>'; ?>
  </div>
  <section style="padding-top:18px"><div class="wrap">
    <div class="postfilter">
      <input type="search" class="postsearch" placeholder="🔍 Tìm trong cẩm nang…" oninput="filterPosts()">
    </div>
    <?php $posts=get_posts('guide',80); if(!$posts) echo '<div class="empty">Chưa có cẩm nang nào. Admin có thể đăng bài đầu tiên từ trang Quản trị.</div>';
      else { echo '<div class="feed" id="postFeed">'; foreach($posts as $po) echo post_card($po); echo '</div>'; } ?>
    <div class="empty" id="postNoMatch" style="display:none;margin-top:18px">Không tìm thấy bài viết phù hợp.</div>
  </div></section>
  <script>
  let postFilter = '';
  function filterPosts(){
    const q = (document.querySelector('.postsearch')?.value||'').toLowerCase().trim();
    let shown = 0, total = 0;
    document.querySelectorAll('#postFeed > .post').forEach(p=>{
      total++;
      const text = p.dataset.search||'';
      const show = !q || text.includes(q);
      p.style.display = show ? '' : 'none';
      if(show) shown++;
    });
    const nm = document.getElementById('postNoMatch');
    if(nm) nm.style.display = (total>0 && shown===0) ? '' : 'none';
  }
  </script>
