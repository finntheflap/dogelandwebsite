<?php
/* Page: topup — extracted from index.php lines 3591-3731 */
?>
<?php
  $hist=[]; try{ $st=db()->prepare("SELECT * FROM web_topups WHERE username=? ORDER BY id DESC LIMIT 10"); $st->execute([$user]); $hist=$st->fetchAll(); }catch(Exception $e){}
  $bal=doge_balance($user); $DL=$CFG['doge_label']??'Dogecoin';
?>
  <div class="phead"><div class="k">Nạp thẻ</div><h1>Nạp <?=h($DL)?></h1><p>Chọn gói có sẵn hoặc nhập số tiền tuỳ ý, rồi chọn hình thức thanh toán.</p>
    <div class="balbar">Số dư: <b class="dogechip"><?=number_format($bal,0,',','.')?></b></div>
  </div>
  <section style="padding-top:18px"><div class="wrap">
    <?php $promo=dgl_promo(); if($promo['active']){ ?>
      <div class="flash ok" style="max-width:none;margin-bottom:18px;text-align:center;font-weight:700">🎉 Khuyến mãi <b style="color:var(--gold)">+<?=$promo['percent']?>% <?=h($DL)?></b> cho mọi giao dịch nạp<?= $promo['until']?' — đến '.date('d/m H:i',(int)($promo['until']/1000)):'' ?>!</div>
    <?php } ?>
    <div class="card" style="padding:18px 20px;margin-bottom:18px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between">
      <div><b style="font-size:1.02rem">🎁 Có Gift Code?</b> <span class="sub2">Nhập mã để nhận <?=h($DL)?> miễn phí.</span></div>
      <form method="post" action="?p=topup" style="display:flex;gap:8px;flex-wrap:wrap">
        <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="gift_redeem"><input type="hidden" name="from" value="topup">
        <input name="code" placeholder="NHẬP GIFT CODE" required style="text-transform:uppercase;background:rgba(0,0,0,.35);border:1px solid var(--line-2);border-radius:10px;color:var(--ink);font:inherit;font-weight:700;letter-spacing:.5px;padding:10px 14px;min-width:180px">
        <button class="btn btn-gold" type="submit">Đổi quà</button>
      </form>
    </div>
    <form method="post" action="?p=topup" id="topForm">
      <input type="hidden" name="csrf" value="<?=$CSRF?>">
      <input type="hidden" name="act" id="tact" value="topup">
      <input type="hidden" name="amount" id="amt" value="100000">
      <input type="hidden" name="method" id="mth" value="bank">

      <div class="tugrid">
        <!-- CỘT TRÁI: chọn gói + tự chọn -->
        <div>
          <div class="tusec">
            <div class="tusec-h"><span class="tustep">1</span> Chọn mệnh giá</div>
            <div class="pkgrid">
              <?php foreach($PACKAGES as $i=>$pk){ $hot=!empty($pk['hot']);
                echo '<div class="pkg2'.($pk['amount']==100000?' on':'').'" data-amt="'.$pk['amount'].'" data-dia="'.$pk['dia'].'" data-xu="'.$pk['xu'].'" onclick="pickPkg(this)">'
                    .($hot?'<div class="rb2">HOT</div>':'')
                    .'<div class="pkg2-amt">'.number_format($pk['amount'],0,',','.').'<small>đ</small></div>'
                    .'<div class="pkg2-dia" style="color:#f7c948">Ð '.number_format($pk['dia'],0,',','.').'</div>'
                    .($pk['bonus']?'<div class="pkg2-bn">'.$pk['bonus'].'</div>':'')
                    .'</div>';
              } ?>
              <div class="pkg2 pkg2-custom" data-amt="custom" onclick="pickCustom(this)">
                <div class="pkg2-amt" style="font-size:1.05rem">Tự chọn</div>
                <div class="pkg2-bn" style="color:var(--muted)">Nhập số tiền</div>
              </div>
            </div>
            <div id="customBox" style="display:none;margin-top:14px">
              <div class="field" style="margin:0"><label>Nhập số tiền (từ <?=number_format($CFG['custom_min'],0,',','.')?>đ đến <?=number_format($CFG['custom_max'],0,',','.')?>đ)</label>
                <input id="camt" type="text" inputmode="numeric" placeholder="VD: 150.000" oninput="cfmt(this)" autocomplete="off"></div>
              <p class="sub2" id="cnote" style="margin-top:7px">Áp dụng cho <b style="color:#2a8f96">ATM</b>, <b style="color:#d63384">Momo</b> &amp; <b style="color:var(--diamond)">QR Code</b>. Tỉ giá: <?=number_format($CFG['vnd_per_doge'],0,',','.')?>đ = 1 <?=h($DL)?>.</p>
            </div>
          </div>

          <div class="tusec">
            <div class="tusec-h"><span class="tustep">2</span> Hình thức thanh toán</div>
            <div class="pmgrid">
              <div class="pm2 on" data-m="bank" onclick="pickPm(this)"><span class="pm2-ic" style="background:#2a8f96">🏦</span><span>ATM / Banking</span></div>
              <div class="pm2" data-m="qr" onclick="pickPm(this)"><span class="pm2-ic" style="background:#56cfd6">▦</span><span>QR Code Pay</span></div>
              <div class="pm2" data-m="momo" onclick="pickPm(this)"><span class="pm2-ic" style="background:#d63384">M</span><span>Momo</span></div>
            </div>
            <p class="sub2" style="margin-top:14px">Sau khi chuyển khoản ATM / quét QR / Momo theo nội dung, admin sẽ duyệt và cộng <?=h($DL)?> vào ví của bạn.</p>
          </div>
        </div>

        <!-- CỘT PHẢI: tóm tắt đơn -->
        <div class="tusum">
          <div class="tusum-card">
            <h3>Tóm tắt giao dịch</h3>
            <div class="tusum-row"><span>Mệnh giá</span><b id="smAmt">100.000đ</b></div>
            <div class="tusum-row"><span>Hình thức</span><b id="smPm">ATM / Banking</b></div>
            <div class="tusum-div"></div>
            <div class="tusum-big"><span>Nhận được</span><b class="dogechip" id="smDia">1.200</b></div>
            <div class="tusum-xu" id="smXuRow" style="display:none"><span>Kèm theo</span><b id="smXu"></b></div>
            <button class="btn btn-green btn-block" type="submit" style="margin-top:18px">Xác nhận nạp</button>
            <p class="sub2" style="text-align:center;margin-top:12px">Giao dịch sẽ được xử lý &amp; cộng <?=h($DL)?> sau khi admin duyệt.</p>
          </div>
        </div>
      </div>
    </form>

    <div class="card" style="padding:0;overflow:hidden;max-width:860px;margin:34px auto 0">
      <div class="ahd">Lịch sử nạp gần đây</div>
      <div style="overflow-x:auto"><table class="tbl"><thead><tr><th>Thời gian</th><th>Mệnh giá</th><th><?=h($DL)?></th><th>Hình thức</th><th>Trạng thái</th></tr></thead><tbody>
      <?php if(!$hist){ echo '<tr><td colspan="5" class="cmid">Chưa có giao dịch nào.</td></tr>'; }
        else foreach($hist as $hx){ $mlabel=['bank'=>'ATM','momo'=>'Momo','qr'=>'QR Code Pay','phonecard'=>'Thẻ điện thoại','gamecard'=>'Thẻ game','zalo'=>'ZaloPay','card'=>'Thẻ cào'][$hx['method']]??$hx['method'];
          $sc=$hx['status']==='success'?'success':'pending'; $stxt=['success'=>'Thành công','rejected'=>'Từ chối','pending'=>'Đang xử lý'][$hx['status']]??$hx['status'];
          echo '<tr><td class="sub2">'.date('d/m H:i',(int)($hx['created']/1000)).'</td><td>'.h($hx['package']).'</td><td class="cdia">'.number_format($hx['diamonds'],0,',','.').'</td><td>'.$mlabel.'</td><td><span class="st '.$sc.'">'.$stxt.'</span></td></tr>';
        } ?>
      </tbody></table></div>
    </div>
  </div></section>
  <script>
  var VND_PER=<?=(int)$CFG['vnd_per_doge']?>, CMIN=<?=(int)$CFG['custom_min']?>, CMAX=<?=(int)$CFG['custom_max']?>;
  var fmt=function(n){return n.toLocaleString('vi-VN')};
  function curMethod(){return document.getElementById('mth').value}
  function update(){
    var custom = document.getElementById('amt').value==='custom';
    var amt, dia;
    if(custom){
      var v=parseInt((document.getElementById('camt').value||'0').replace(/[^0-9]/g,''),10)||0;
      amt=v; dia=Math.floor(v/VND_PER);
      document.getElementById('tact').value='topup_custom';
    }else{
      var el=document.querySelector('.pkg2.on:not(.pkg2-custom)');
      amt=parseInt(el.dataset.amt,10); dia=parseInt(el.dataset.dia,10);
      document.getElementById('tact').value='topup';
    }
    document.getElementById('smAmt').textContent=fmt(amt)+'đ'+(custom?' (tự chọn)':'');
    document.getElementById('smDia').textContent='Ð'+fmt(dia);
  }
  function pickPkg(el){
    document.querySelectorAll('.pkg2').forEach(p=>p.classList.remove('on'));el.classList.add('on');
    document.getElementById('amt').value=el.dataset.amt;
    document.getElementById('customBox').style.display='none';
    update();
  }
  function pickCustom(el){
    document.querySelectorAll('.pkg2').forEach(p=>p.classList.remove('on'));el.classList.add('on');
    document.getElementById('amt').value='custom';
    document.getElementById('customBox').style.display='block';
    update();
  }
  var pmName={bank:'ATM / Banking',momo:'Momo',qr:'QR Code Pay'};
  function pickPm(el){
    var m=el.dataset.m;
    document.querySelectorAll('.pm2').forEach(p=>p.classList.remove('on'));el.classList.add('on');
    document.getElementById('mth').value=m;
    document.getElementById('smPm').textContent=pmName[m]||m;
    update();
  }
  function cfmt(i){var v=i.value.replace(/[^0-9]/g,'');i.value=v?parseInt(v,10).toLocaleString('vi-VN'):'';update()}
  // chặn submit nếu tự chọn ngoài khoảng
  document.getElementById('topForm').addEventListener('submit',function(e){
    if(document.getElementById('amt').value==='custom'){
      var v=parseInt((document.getElementById('camt').value||'0').replace(/[^0-9]/g,''),10)||0;
      if(v<CMIN||v>CMAX){e.preventDefault();alert('Số tiền phải từ '+fmt(CMIN)+'đ đến '+fmt(CMAX)+'đ.');return}
      document.getElementById('amt').value=v; // gửi số thật
    }
  });
  update();
  </script>

