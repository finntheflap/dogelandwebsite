<?php
/* Page: top — extracted from index.php lines 3281-3372 */
?>
<?php
  $cat=$_GET['cat']??'doge_spent'; $T=$CFG['authme_table']; $sapi=$CFG['skin_api'];
  $cats=[['doge_spent','Top Tiêu '.($CFG['doge_label']??'Dogecoin')],['topup','Top Nạp tiền'],['dogecoin','Top Số dư'],['logins','Top Đăng nhập']];
  if(!in_array($cat,['doge_spent','topup','dogecoin','logins'],true)) $cat='doge_spent';
  $rows=[];
  try{
    if($cat==='topup') $rows=db()->query("SELECT username AS name, SUM(amount) AS val FROM web_topups WHERE status='success' GROUP BY username ORDER BY val DESC LIMIT 50")->fetchAll();
    elseif($cat==='dogecoin') $rows=db()->query("SELECT username AS name, dogecoin AS val FROM web_wallet WHERE dogecoin>0 ORDER BY val DESC LIMIT 50")->fetchAll();
    elseif($cat==='logins') $rows=db()->query("SELECT username AS name, logins AS val FROM web_wallet WHERE logins>0 ORDER BY val DESC LIMIT 50")->fetchAll();
    else $rows=db()->query("SELECT username AS name, doge_spent AS val FROM web_wallet WHERE doge_spent>0 ORDER BY val DESC LIMIT 50")->fetchAll();
  }catch(Exception $e){}
  $isDoge = in_array($cat,['doge_spent','dogecoin'],true);
  $unit=['topup'=>'đ','dogecoin'=>($CFG['doge_label']??'Dogecoin'),'doge_spent'=>($CFG['doge_label']??'Dogecoin'),'logins'=>'lần'][$cat];
  // Hạng của người đang xem
  $myRank=0; $myVal=0;
  if($user){
    try{
      if($cat==='topup'){ $mv=db()->prepare("SELECT COALESCE(SUM(amount),0) FROM web_topups WHERE status='success' AND username=?"); $mv->execute([$user]); $myVal=(int)$mv->fetchColumn();
        $hr=db()->query("SELECT COUNT(*) FROM (SELECT username,SUM(amount) s FROM web_topups WHERE status='success' GROUP BY username HAVING s>$myVal) t"); $myRank=$myVal>0?((int)$hr->fetchColumn()+1):0;
      } else { $col=$cat;
        $mv=db()->prepare("SELECT COALESCE($col,0) FROM web_wallet WHERE username=?"); $mv->execute([$user]); $myVal=(int)$mv->fetchColumn();
        $hr=db()->prepare("SELECT COUNT(*) FROM web_wallet WHERE $col>?"); $hr->execute([$myVal]); $myRank=$myVal>0?((int)$hr->fetchColumn()+1):0;
      }
    }catch(Exception $e){}
  }
  // AJAX endpoint cho real-time refresh: trả về JSON thô để JS dựng lại bảng.
  if(!empty($_GET['ajax'])){
    json_out(['cat'=>$cat,'unit'=>$unit,'isDoge'=>$isDoge,'rows'=>$rows,'myRank'=>$myRank,'myVal'=>$myVal,'user'=>$user,'doge_symbol'=>$CFG['doge_symbol']??'Ð']);
  }
?>
  <div class="phead"><div class="k">Bảng xếp hạng</div><h1>Top người chơi</h1><p>Vinh danh những người chơi tiêu nhiều <?=h($CFG['doge_label']??'Dogecoin')?> nhất Dogeland Network. <span class="lblive">● đang cập nhật trực tiếp</span></p></div>
  <section style="padding-top:14px"><div class="wrap" style="max-width:780px">
    <div class="tabs"><?php foreach($cats as $c) echo '<a class="tab'.($cat===$c[0]?' on':'').'" href="?p=top&cat='.$c[0].'">'.h($c[1]).'</a>'; ?></div>
    <?php if($user){
      if($myRank>0) echo '<div class="myrank"><span class="badge">#'.$myRank.'</span><div class="who"><b>'.h($user).'</b><div>Bạn đang ở hạng #'.$myRank.' với '.($isDoge?doge_short($myVal):number_format($myVal,0,',','.').' '.$unit).'</div></div><img class="lbav" src="'.h($sapi).'/avatar/'.urlencode($user).'/40" data-skin-user="'.h($user).'" data-skin-size="40" onerror="skinFallback(this)" alt=""></div>';
      else echo '<div class="myrank"><span class="badge">—</span><div class="who"><b>'.h($user).'</b><div>Bạn chưa có mặt trong bảng này. Hãy tham gia để ghi tên mình!</div></div></div>';
    } ?>
    <div class="card" id="lbcard" style="padding:0;overflow:hidden" data-cat="<?=h($cat)?>">
      <?php if(!$rows) echo '<div class="empty" id="lbbody" style="border:0">Chưa có dữ liệu.</div>';
        else { echo '<div class="lb" id="lbbody">'; $rk=0; foreach($rows as $r){ $rk++; $medal=$rk<=3?'m'.$rk:'';
          $mine = $user && strtolower($r['name'])===strtolower($user);
          $val = $isDoge ? ('<span class="dsym">Ð</span>'.number_format($r['val'],0,',','.')) : (number_format($r['val'],0,',','.').' <small>'.$unit.'</small>');
          echo '<div class="lbrow"'.($mine?' style="background:rgba(242,182,49,.07)"':'').'><span class="lbrk '.$medal.'">'.$rk.'</span>'
              .'<img class="lbav" src="'.h($sapi).'/avatar/'.urlencode($r['name']).'/34" data-skin-user="'.h($r['name']).'" data-skin-size="34" onerror="skinFallback(this)" alt="">'
              .'<span class="lbn">'.h($r['name']).($mine?' <small style="color:var(--gold)">(bạn)</small>':'').'</span>'
              .'<span class="lbv">'.$val.'</span></div>';
        } echo '</div>'; } ?>
    </div>
  </div></section>
  <script>
  (function(){
    const SAPI=<?=json_encode($sapi)?>, ME=<?=json_encode($user)?>;
    function esc(s){ const d=document.createElement('div'); d.textContent=s==null?'':String(s); return d.innerHTML; }
    function render(d){
      const card=document.getElementById('lbcard'); if(!card) return;
      const rows=d.rows||[];
      if(!rows.length){ card.innerHTML='<div class="empty" id="lbbody" style="border:0">Chưa có dữ liệu.</div>'; return; }
      let h='<div class="lb" id="lbbody">';
      rows.forEach((r,i)=>{
        const rk=i+1, medal=rk<=3?('m'+rk):'';
        const mine = ME && String(r.name).toLowerCase()===String(ME).toLowerCase();
        const val = d.isDoge
          ? ('<span class="dsym">'+esc(d.doge_symbol||'Ð')+'</span>'+Number(r.val).toLocaleString('vi-VN'))
          : (Number(r.val).toLocaleString('vi-VN')+' <small>'+esc(d.unit)+'</small>');
        h+='<div class="lbrow"'+(mine?' style="background:rgba(242,182,49,.07)"':'')+
          '><span class="lbrk '+medal+'">'+rk+'</span>'+
          '<img class="lbav" src="'+SAPI+'/avatar/'+encodeURIComponent(r.name)+'/34" data-skin-user="'+esc(r.name)+'" data-skin-size="34" onerror="skinFallback(this)" alt="">'+
          '<span class="lbn">'+esc(r.name)+(mine?' <small style="color:var(--gold)">(bạn)</small>':'')+'</span>'+
          '<span class="lbv">'+val+'</span></div>';
      });
      h+='</div>'; card.innerHTML=h;
      // Cập nhật "myrank" pill bên trên
      const mr=document.querySelector('.myrank');
      if(mr && ME){
        if(d.myRank>0){
          const valStr=d.isDoge ? (esc(d.doge_symbol||'Ð')+Number(d.myVal).toLocaleString('vi-VN'))
                                : (Number(d.myVal).toLocaleString('vi-VN')+' '+esc(d.unit));
          mr.querySelector('.badge').textContent='#'+d.myRank;
          const who=mr.querySelector('.who div'); if(who) who.textContent='Bạn đang ở hạng #'+d.myRank+' với '+valStr.replace(/<[^>]+>/g,'');
        }
      }
    }
    function tick(){
      const cat=document.getElementById('lbcard')?.dataset.cat||'doge_spent';
      fetch('?p=top&ajax=1&cat='+encodeURIComponent(cat),{credentials:'same-origin'})
        .then(r=>r.json()).then(render).catch(()=>{});
    }
    // Bắt đầu polling. 12 giây = đủ nhanh, không quá tốn DB.
    setInterval(tick, 12000);
  })();
  </script>
