<?php
/* Page: events — extracted from index.php lines 2634-2682 */
?>
<?php
  $posts = get_posts(['event','news','update'], 80);
  // Tập hợp danh sách server xuất hiện trong các bài cập nhật để render chip lọc.
  $srvs=[]; foreach($posts as $po){ if($po['type']==='update' && !empty($po['server']) && !in_array($po['server'],$srvs,true)) $srvs[]=$po['server']; }
?>
  <div class="phead"><div class="k">Bảng tin</div><h1>📰 Tin tức</h1><p>Sự kiện, thông báo và bản cập nhật mới nhất trên server.</p>
    <?php if($IS_ADMIN) echo '<div style="margin-top:14px"><a class="btn btn-ghost btn-sm" href="?p=admin&tab=posts">➕ Đăng bài mới</a></div>'; ?>
  </div>
  <section style="padding-top:18px"><div class="wrap">
    <div class="postfilter">
      <input type="search" class="postsearch" placeholder="🔍 Tìm bài viết (tiêu đề, nội dung, server)…" oninput="filterPosts()">
      <div class="postchips">
        <a href="#" class="chip on" data-f="" onclick="setPostFilter(event,'')">🌐 Tất cả</a>
        <a href="#" class="chip" data-f="t:event" onclick="setPostFilter(event,'t:event')">🎉 Sự kiện</a>
        <a href="#" class="chip" data-f="t:news" onclick="setPostFilter(event,'t:news')">📢 Thông báo</a>
        <a href="#" class="chip" data-f="t:update" onclick="setPostFilter(event,'t:update')">🔧 Cập nhật</a>
        <?php foreach($srvs as $s) echo '<a href="#" class="chip" data-f="s:'.h(strtolower($s)).'" onclick="setPostFilter(event,\'s:'.h(strtolower($s)).'\')">🖥️ '.h($s).'</a>'; ?>
      </div>
    </div>
    <?php if(!$posts) echo '<div class="empty">Chưa có bài viết nào.</div>';
      else { echo '<div class="feed" id="postFeed">'; foreach($posts as $po) echo post_card($po); echo '</div>'; } ?>
    <div class="empty" id="postNoMatch" style="display:none;margin-top:18px">Không tìm thấy bài viết phù hợp với bộ lọc.</div>
  </div></section>
  <script>
  let postFilter = '';
  function setPostFilter(ev, f){ ev.preventDefault();
    postFilter = f;
    document.querySelectorAll('.postchips .chip').forEach(c=>c.classList.toggle('on', c.dataset.f===f));
    filterPosts();
  }
  function filterPosts(){
    const q = (document.querySelector('.postsearch')?.value||'').toLowerCase().trim();
    let shown = 0, total = 0;
    document.querySelectorAll('#postFeed > .post').forEach(p=>{
      total++;
      const t = p.dataset.type||'', s = p.dataset.server||'', text = p.dataset.search||'';
      let fOk = true;
      if(postFilter.startsWith('t:')) fOk = (t === postFilter.slice(2));
      else if(postFilter.startsWith('s:')) fOk = (s === postFilter.slice(2));
      const qOk = !q || text.includes(q);
      const show = fOk && qOk;
      p.style.display = show ? '' : 'none';
      if(show) shown++;
    });
    const nm = document.getElementById('postNoMatch');
    if(nm) nm.style.display = (total>0 && shown===0) ? '' : 'none';
  }
  </script>
