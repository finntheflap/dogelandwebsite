<?php
/* Page: tickets — extracted from index.php lines 3456-3496 */
?>
<?php
  $mine=[]; try{ $st=db()->prepare("SELECT * FROM web_tickets WHERE username=? ORDER BY updated DESC"); $st->execute([$user]); $mine=$st->fetchAll(); }catch(Exception $e){}
  $sl=['open'=>'Đang mở','in_progress'=>'Đang xử lý','closed'=>'Đã đóng'];
?>
  <div class="phead"><div class="k">Hỗ trợ</div><h1>Ticket của tôi</h1><p>Gửi yêu cầu hỗ trợ — admin sẽ phản hồi sớm nhất.</p></div>
  <section style="padding-top:14px"><div class="wrap" style="max-width:820px"><div class="admin-grid">
    <div class="card" style="padding:26px">
      <h3 class="ah">Tạo ticket mới</h3>
      <form method="post" action="?p=tickets" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="ticket_create">
        <div class="row2">
          <div class="field"><label>Chủ đề hỗ trợ</label><select name="category">
            <?php foreach(($CFG['ticket_categories']??['Khác']) as $c) echo '<option>'.h($c).'</option>'; ?>
          </select></div>
          <div class="field"><label>Server đang gặp vấn đề</label><select name="server">
            <option value="">— Chọn server —</option>
            <?php foreach(($CFG['ticket_servers']??[]) as $s) echo '<option>'.h($s).'</option>'; ?>
          </select></div>
        </div>
        <div class="field"><label>Tiêu đề</label><input name="subject" required placeholder="Mô tả ngắn gọn vấn đề..."></div>
        <div class="field"><label>Nội dung chi tiết</label><textarea name="message" required placeholder="Mô tả chi tiết: thời gian xảy ra, các bước, thông tin liên quan..."></textarea></div>
        <div class="field"><label>Đính kèm ảnh / video <span class="sub2" style="font-weight:500">(tối đa <?=(int)($CFG['upload_max_files']??8)?> file, <?=(int)($CFG['upload_max_mb']??25)?>MB / file)</span></label>
          <label class="fileup" id="fup1">
            <input type="file" name="attachments[]" multiple accept="image/*,video/*" onchange="showFiles(this,'fup1')">
            <span class="fup-ic">📎</span>
            <span class="fup-text">Chọn ảnh hoặc video để tải lên...</span>
          </label>
          <div class="sub2" style="margin-top:6px;font-size:12px">Hỗ trợ: ảnh (jpg, png, gif, webp, avif) và video (mp4, webm, mov). File được lưu trực tiếp lên server.</div>
        </div>
        <button class="btn btn-green btn-block" type="submit">Gửi ticket</button>
      </form>
    </div>
    <div class="card" style="padding:0;overflow:hidden">
      <div class="ahd">Ticket của tôi (<?=count($mine)?>)</div>
      <?php if(!$mine) echo '<div class="cmid">Bạn chưa gửi ticket nào.</div>';
        else foreach($mine as $t){ $srv = !empty($t['server']) ? ' · '.h($t['server']) : '';
          echo '<a class="tk" href="?p=ticket&id='.(int)$t['id'].'"><span class="tno">'.h($t['code']?:ticket_code($t['id'])).'</span><div class="ti"><div class="ts">'.h($t['subject']).'</div><div class="tmeta">'.h($t['category']).$srv.' · '.date('d/m/Y H:i',(int)($t['updated']/1000)).'</div></div><span class="tst '.h($t['status']).'">'.($sl[$t['status']]??$t['status']).'</span></a>';
        } ?>
    </div>
  </div></div></section>
