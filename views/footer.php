<?php
/* ============================================================================
   VIEW FOOTER — <footer> + inline scripts
   Một số script nhúng biến PHP (json_encode) nên giữ inline.
   ========================================================================== */
?>
<footer><div class="wrap">
  <div class="fgrid">
    <div class="fbrand">
      <img src="?img=logo" alt="Dogeland Network">
      <p>Server Minecraft sinh tồn — minigame hàng đầu Việt Nam. Vui chơi văn minh, tôn trọng cộng đồng.</p>
      <div class="soc">
        <a href="<?=h($CFG['socials']['discord'])?>" target="_blank" rel="noopener" title="Discord"><?=ic('discord')?></a>
        <a href="<?=h($CFG['socials']['facebook'])?>" target="_blank" rel="noopener" title="Facebook"><?=ic('facebook')?></a>
        <a href="<?=h($CFG['socials']['youtube'])?>" target="_blank" rel="noopener" title="YouTube"><?=ic('youtube')?></a>
        <a href="<?=h($CFG['socials']['tiktok'])?>" target="_blank" rel="noopener" title="TikTok"><?=ic('tiktok')?></a>
      </div>
    </div>
    <div class="fcol"><h4>Khám phá</h4><a href="?p=info">Thông tin</a><a href="?p=events">Sự kiện</a><a href="?p=topup">Nạp thẻ</a><a href="?p=shop">Cửa hàng</a></div>
    <div class="fcol"><h4>Hỗ trợ</h4><a href="<?=h($CFG['socials']['discord'])?>" target="_blank" rel="noopener">Liên hệ Admin</a><a href="#" onclick="toast('Trang luật server');return false">Luật server</a><a href="#" onclick="toast('FAQ');return false">FAQ</a></div>
    <div class="fcol"><h4>Tài khoản</h4><?php if($user){ echo '<a href="?p=topup">Nạp thẻ</a><a href="?p=logout">Đăng xuất</a>'; } else { echo '<a href="?p=login">Đăng nhập</a><a href="?p=register">Đăng ký</a>'; } ?></div>
  </div>
  <div class="fbot">© <?=date('Y')?> DOGELAND.VN — Không liên kết với Mojang / Microsoft.</div>
</div></footer>

<div class="toast" id="toast"></div>

<script>
const SERVER_IP=<?=json_encode($CFG['server_ip'])?>;
addEventListener('scroll',()=>document.getElementById('hdr').classList.toggle('sc',scrollY>20));
/* Skin avatar fallback chain. Một số mạng / vị trí địa lý chặn mc-heads.net, hoặc
   người chơi không có tài khoản Mojang (offline-mode) — thử lần lượt các service
   khác trước khi chịu hiển thị icon doge. */
function skinFallback(img){
  try{
    const tries = parseInt(img.dataset.skinT||'0',10);
    // Đoán username từ URL hiện tại — các service đều dùng /avatar/<name>/ hoặc /helm/<name>/.
    let user = img.dataset.skinUser||'';
    if(!user){ const m = (img.src||'').match(/\/(?:avatar|helm|face|head)s?\/([^\/?&]+)/); if(m) user = decodeURIComponent(m[1]); }
    const size = img.dataset.skinSize || (img.width|0) || 40;
    const chain = [
      'https://minotar.net/helm/'+encodeURIComponent(user)+'/'+size+'.png',
      'https://crafatar.com/avatars/'+encodeURIComponent(user)+'?size='+size+'&overlay',
      '?img=doge'
    ];
    if(tries < chain.length){ img.dataset.skinT = tries+1; img.src = chain[tries]; }
    else { img.onerror = null; img.src = '?img=doge'; }
  }catch(e){ img.onerror = null; img.src = '?img=doge'; }
}
let tT;function toast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');clearTimeout(tT);tT=setTimeout(()=>t.classList.remove('show'),2600)}
function copyIp(n){const b=document.getElementById('cp'+(n||''));const d=()=>{if(b){b.classList.add('done');b.textContent='Đã chép';setTimeout(()=>{b.classList.remove('done');b.textContent='Sao chép'},1800)}toast('Đã sao chép IP: '+SERVER_IP)};navigator.clipboard?navigator.clipboard.writeText(SERVER_IP).then(d).catch(d):d()}
function showFiles(input, wrapId){
  const w = document.getElementById(wrapId); if(!w) return;
  const txt = w.querySelector('.fup-text'); if(!txt) return;
  const files = input.files;
  if(!files || files.length === 0){ w.classList.remove('has-files'); txt.textContent = txt.dataset.def || txt.textContent; return; }
  if(!txt.dataset.def) txt.dataset.def = txt.textContent;
  w.classList.add('has-files');
  const names = []; let total = 0;
  for(let i=0; i<files.length; i++){ names.push(files[i].name); total += files[i].size; }
  const mb = (total / 1024 / 1024).toFixed(1);
  txt.textContent = '✓ ' + files.length + ' file (' + mb + ' MB): ' + names.join(', ');
}

/* counters (home) */
document.querySelectorAll('.stat .n[data-c]').forEach(el=>{const t=+el.dataset.c,s=el.dataset.s||'';let c=0,st=t/55;const iv=setInterval(()=>{c+=st;if(c>=t){c=t;clearInterval(iv)}el.textContent=Math.floor(c).toLocaleString('vi-VN')+s},18)});
const live=document.getElementById('live');
if(live){let c=0;const iv=setInterval(()=>{c+=15;if(c>=847){c=847;clearInterval(iv);setInterval(()=>{let n=parseInt(live.textContent.replace(/\D/g,''))||847;n=Math.max(800,Math.min(950,n+(Math.random()<.5?-1:1)*Math.floor(Math.random()*5)));live.textContent=n.toLocaleString('vi-VN')},2500)}live.textContent=Math.floor(c).toLocaleString('vi-VN')},18)}

/* shop tabs */
function shopTab(id){document.querySelectorAll('.tab').forEach(t=>t.classList.toggle('on',t.dataset.t===id));document.querySelectorAll('.pane').forEach(p=>p.classList.toggle('on',p.id==='pane-'+id))}
/* user dropdown: đóng khi bấm ra ngoài */
document.addEventListener('click',e=>{const dd=document.getElementById('udd');if(dd&&!e.target.closest('.udrop'))dd.classList.remove('on')});
/* inventory tabs */
function invTab(el){document.querySelectorAll('.invtab').forEach(t=>t.classList.remove('on'));el.classList.add('on');document.querySelectorAll('.invpane').forEach(p=>p.classList.toggle('on',p.id==='inv-'+el.dataset.m))}
/* auction countdown */
function fmtCD(ms){let s=Math.floor(ms/1000);if(s<=0)return null;const d=Math.floor(s/86400);s-=d*86400;const h=Math.floor(s/3600);s-=h*3600;const m=Math.floor(s/60);const ss=s-m*60;const p=n=>String(n).padStart(2,'0');return (d>0?d+'n ':'')+p(h)+':'+p(m)+':'+p(ss)}
function tickCD(){document.querySelectorAll('.tm[data-end]').forEach(el=>{const t=fmtCD((+el.dataset.end)-Date.now());if(t===null){el.textContent='Đã kết thúc';el.classList.add('ended');const b=el.closest('.auc')?.querySelector('button');if(b){b.disabled=true;b.style.opacity=.5;b.textContent='Đã kết thúc'}}else el.textContent='⏱ '+t})}
if(document.querySelector('.tm[data-end]')){tickCD();setInterval(tickCD,1000)}
</script>

<?php if($user){ ?>
<?php if($IS_ADMIN){ ?>
<div class="achat" id="achat">
  <button class="achat-fab" id="achatFab" type="button" onclick="toggleChat()" title="Chat nội bộ Admin">
    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9.9 9.9 0 0 1-4-.8L3 21l1.9-4.5A8.4 8.4 0 1 1 21 11.5z"/></svg>
    <span class="achat-dot" id="achatDot" style="display:none"></span>
  </button>
  <div class="achat-box" id="achatBox">
    <div class="achat-hd"><span>💬 Chat Quản trị <small>(chỉ admin)</small></span><button type="button" class="achat-x" onclick="toggleChat()">×</button></div>
    <div class="achat-msgs" id="achatMsgs"><div class="achat-sys">Đang tải…</div></div>
    <div class="achat-in">
      <input id="achatInput" type="text" maxlength="800" placeholder="Nhắn cho team admin…" autocomplete="off">
      <button type="button" onclick="sendChat()">Gửi</button>
    </div>
  </div>
</div>
<?php } ?>
<script>
window.DGL={csrf:<?=json_encode($CSRF)?>,user:<?=json_encode($user)?>,admin:<?=$IS_ADMIN?1:0?>,skin:<?=json_encode($CFG['skin_api'])?>};
(function(){
  var D=window.DGL, esrc=null, curN=0, curC=0, failCount=0, pollTimer=null;
  function esc(s){return (s==null?'':String(s)).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
  function avatar(name,sz){return (D.skin||'')+'/avatar/'+encodeURIComponent(name||'doge')+'/'+(sz||32)}
  function ago(ms){var s=Math.floor((Date.now()-ms)/1000);if(s<60)return 'vừa xong';if(s<3600)return Math.floor(s/60)+' phút';if(s<86400)return Math.floor(s/3600)+' giờ';return Math.floor(s/86400)+' ngày'}
  var ICON={topup:'💳',ticket:'🎫',tag:'@',reply:'↩',gift:'🎁',info:'🔔'};

  /* ---------- CHUÔNG THÔNG BÁO ---------- */
  var nlist=document.getElementById('nlist'), nbadge=document.getElementById('nbadge'), npanel=document.getElementById('npanel');
  var notifCache=[];
  function setUnread(n){ if(!nbadge)return; if(n>0){nbadge.textContent=n>99?'99+':n; nbadge.style.display='';} else nbadge.style.display='none'; }
  function renderNotif(){
    if(!nlist)return;
    if(!notifCache.length){ nlist.innerHTML='<div class="nempty">Chưa có thông báo nào.</div>'; return; }
    nlist.innerHTML=notifCache.slice(0,30).map(function(x){
      var ic=ICON[x.type]||ICON.info;
      return '<a class="nitem'+(x.is_read?'':' unread')+'" href="#" data-id="'+x.id+'" data-link="'+esc(x.link||'')+'">'
        +'<span class="nic t-'+esc(x.type)+'">'+ic+'</span>'
        +'<span class="nbody"><span class="ntitle">'+esc(x.title)+'</span>'
        +(x.body?'<span class="ntext">'+esc(x.body)+'</span>':'')
        +'<span class="ntime">'+ago(+x.created)+'</span></span></a>';
    }).join('');
    nlist.querySelectorAll('.nitem').forEach(function(el){
      el.addEventListener('click',function(e){ e.preventDefault(); var id=el.dataset.id, link=el.dataset.link;
        fetch('?p=api&a=notif_read_one',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'csrf='+encodeURIComponent(D.csrf)+'&id='+id})
          .then(r=>r.json()).then(function(j){ if(j&&j.ok) setUnread(j.unread); }).catch(function(){});
        if(link){ location.href=link; }
      });
    });
  }
  function pushNotif(x){ x.is_read=0; notifCache=notifCache.filter(function(o){return o.id!=x.id}); notifCache.unshift(x); if(notifCache.length>60)notifCache.pop(); renderNotif(); }
  window.toggleNotif=function(){ if(!npanel)return; var open=npanel.classList.toggle('on'); if(open) renderNotif(); };
  window.markAllRead=function(){
    fetch('?p=api&a=notif_read',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'csrf='+encodeURIComponent(D.csrf)})
      .then(r=>r.json()).then(function(j){ if(j&&j.ok){ setUnread(0); notifCache.forEach(function(o){o.is_read=1}); renderNotif(); } }).catch(function(){});
  };
  document.addEventListener('click',function(e){ if(npanel&&!e.target.closest('#ndrop')) npanel.classList.remove('on'); });

  /* ---------- CHAT ADMIN ---------- */
  var amsgs=document.getElementById('achatMsgs'), abox=document.getElementById('achatBox'), adot=document.getElementById('achatDot'), ainput=document.getElementById('achatInput');
  var chatOpen=false, chatCache=[], seenChat={};
  function renderChat(){
    if(!amsgs)return;
    amsgs.innerHTML=chatCache.map(function(m){
      if(m.kind==='system') return '<div class="achat-sys">'+esc(m.message)+'</div>';
      var mine=(m.username||'').toLowerCase()===(D.user||'').toLowerCase();
      return '<div class="achat-row'+(mine?' me':'')+'"><img src="'+avatar(m.username,30)+'" data-skin-user="'+esc(m.username)+'" data-skin-size="30" onerror="skinFallback(this)"><div class="achat-bub"><b>'+esc(m.username)+'</b><span>'+esc(m.message)+'</span></div></div>';
    }).join('');
    amsgs.scrollTop=amsgs.scrollHeight;
  }
  function pushChat(m){ if(seenChat[m.id])return; seenChat[m.id]=1; chatCache.push(m); if(chatCache.length>200)chatCache.shift(); if(chatOpen)renderChat(); else if(adot)adot.style.display=''; }
  window.toggleChat=function(){ if(!abox)return; chatOpen=abox.classList.toggle('on'); if(chatOpen){ if(adot)adot.style.display='none'; renderChat(); if(ainput)ainput.focus(); } };
  window.sendChat=function(){
    if(!ainput)return; var v=ainput.value.trim(); if(!v)return; ainput.value='';
    fetch('?p=api&a=chat_send',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'csrf='+encodeURIComponent(D.csrf)+'&message='+encodeURIComponent(v)})
      .then(r=>r.json()).then(function(j){ if(!j||!j.ok){ ainput.value=v; } }).catch(function(){ ainput.value=v; });
  };
  if(ainput) ainput.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); sendChat(); } });

  /* ---------- KẾT NỐI REALTIME (SSE) + fallback polling ---------- */
  function loadSnapshot(){
    fetch('?p=api&a=snapshot').then(r=>r.json()).then(function(j){
      if(!j||!j.ok)return; setUnread(j.unread||0); notifCache=j.items||[]; curN=j.n||0; curC=j.c||0; renderNotif();
      if(D.admin){ fetch('?p=api&a=chat_list&since=0').then(r=>r.json()).then(function(c){ if(c&&c.ok){ (c.items||[]).forEach(pushChat); curC=c.c||curC; if(!chatOpen&&(c.items||[]).some(function(m){return m.kind!=='system'})===false){} } }); }
    }).catch(function(){});
  }
  function connectSSE(){
    if(!window.EventSource){ startPolling(); return; }
    try{ esrc=new EventSource('?p=sse&n='+curN+'&c='+curC); }catch(e){ startPolling(); return; }
    esrc.addEventListener('notif',function(ev){ try{ pushNotif(JSON.parse(ev.data)); }catch(e){} });
    esrc.addEventListener('count',function(ev){ try{ var d=JSON.parse(ev.data); setUnread(d.unread); if(d.n)curN=d.n; failCount=0; }catch(e){} });
    esrc.addEventListener('chat',function(ev){ try{ pushChat(JSON.parse(ev.data)); }catch(e){} });
    esrc.addEventListener('chatcur',function(ev){ try{ var d=JSON.parse(ev.data); if(d.c)curC=d.c; }catch(e){} });
    esrc.addEventListener('bye',function(){ if(esrc){esrc.close();esrc=null;} setTimeout(connectSSE,300); });
    esrc.onerror=function(){ if(esrc){esrc.close();esrc=null;} failCount++; if(failCount>=4){ startPolling(); } else { setTimeout(connectSSE,1500); } };
  }
  function startPolling(){
    if(pollTimer)return;
    pollTimer=setInterval(function(){
      fetch('?p=api&a=snapshot').then(r=>r.json()).then(function(j){ if(j&&j.ok){ setUnread(j.unread||0);
        (j.items||[]).slice().reverse().forEach(function(x){ if(x.id>curN){ pushNotif(x); curN=x.id; } }); }});
      if(D.admin){ fetch('?p=api&a=chat_list&since='+curC).then(r=>r.json()).then(function(c){ if(c&&c.ok){ (c.items||[]).forEach(pushChat); curC=c.c||curC; } }); }
    },5000);
  }
  loadSnapshot();
  setTimeout(connectSSE,600);
})();
</script>
<?php } ?>
</body>
</html>
