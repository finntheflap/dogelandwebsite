<?php
/* Page: ticket — extracted from index.php lines 3499-3588 */
?>
<?php
  $tid=(int)($_GET['id']??0); $tk=null; $reps=[];
  try{ $st=db()->prepare("SELECT * FROM web_tickets WHERE id=?"); $st->execute([$tid]); $tk=$st->fetch();
    if($tk){ $rs=db()->prepare("SELECT * FROM web_ticket_replies WHERE ticket_id=? ORDER BY id"); $rs->execute([$tid]); $reps=$rs->fetchAll(); }
  }catch(Exception $e){}
  $can = $tk && ($IS_ADMIN || strtolower($tk['username'])===strtolower($user));
  $sl=['open'=>'Đang mở','in_progress'=>'Đang xử lý','closed'=>'Đã đóng']; $sapi=h($CFG['skin_api']);
?>
  <section style="padding-top:40px"><div class="wrap" style="max-width:760px">
    <?php if(!$can){ echo '<div class="empty">Không tìm thấy ticket hoặc bạn không có quyền xem.</div>'; }
      else { ?>
      <a href="?p=<?= $IS_ADMIN?'admin&tab=tickets':'tickets' ?>" class="sub2" style="text-decoration:none;display:inline-block;margin-bottom:14px">← Danh sách ticket</a>
      <div class="card tkhead">
        <div class="row1">
          <div><h1><?=h($tk['subject'])?></h1><div class="tno2">Ticket <?=h($tk['code']?:ticket_code($tk['id']))?></div></div>
          <span class="tst <?=h($tk['status'])?>"><?=$sl[$tk['status']]??$tk['status']?></span>
        </div>
        <div class="tkmeta">
          <div><div class="ml">Người gửi</div><div class="mv"><img src="<?=$sapi?>/avatar/<?=urlencode($tk['username'])?>/22" data-skin-user="<?=h($tk['username'])?>" data-skin-size="22" onerror="skinFallback(this)" alt=""><?=h($tk['username'])?></div></div>
          <div><div class="ml">Loại yêu cầu</div><div class="mv"><?=h($tk['category'])?></div></div>
          <div><div class="ml">Server</div><div class="mv"><?= !empty($tk['server']) ? h($tk['server']) : '<span style="color:var(--muted);font-weight:600">Không xác định</span>' ?></div></div>
          <div><div class="ml">Người xử lý</div><div class="mv"><?= $tk['assignee']?'<img src="'.$sapi.'/avatar/'.urlencode($tk['assignee']).'/22" data-skin-user="'.h($tk['assignee']).'" data-skin-size="22" onerror="skinFallback(this)" alt="">'.h($tk['assignee']):'<span style="color:var(--muted);font-weight:600">Chưa nhận</span>' ?></div></div>
          <div><div class="ml">Ngày tạo</div><div class="mv" style="font-weight:600"><?=date('d/m/Y H:i',(int)($tk['created']/1000))?></div></div>
        </div>
      </div>

      <div class="thread">
      <?php
        $roleBadgeColors = ['supervisor'=>'#ff8db0','admin'=>'#7dd47f','support'=>'#8fb4ff'];
        foreach($reps as $rp){
          $staff=$rp['is_staff']; $attHtml = tk_render_atts($rp['attachments'] ?? null);
          $badge = ''; $roleCls = '';
          if($staff){
            $rRole = user_role($rp['username']);
            $rLbl  = strtoupper(role_label($rRole));
            $rCol  = $roleBadgeColors[$rRole] ?? '#7dd47f';
            $badge = '<span class="adm-badge" style="background:'.$rCol.';color:#1a1d22">'.$rLbl.'</span>';
            $roleCls = ' role-'.($rRole ?: 'admin');
          }
          echo '<div class="msg'.($staff?' staff':'').$roleCls.'"><img src="'.$sapi.'/avatar/'.urlencode($rp['username']).'/40" data-skin-user="'.h($rp['username']).'" data-skin-size="40" onerror="skinFallback(this)" alt=""><div class="mb"><div class="mu">'.h($rp['username']).$badge.'<span class="mtime">'.date('d/m/Y H:i',(int)($rp['created']/1000)).'</span></div><div class="mc">'.nl2br(h($rp['message'])).'</div>'.$attHtml.'</div></div>';
        } ?>
      </div>

      <?php if($tk['status']!=='closed'){ ?>
      <div class="card composer">
        <h4>Phản hồi</h4>
        <form method="post" action="?p=ticket" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=$CSRF?>"><input type="hidden" name="act" value="ticket_reply"><input type="hidden" name="id" value="<?=$tk['id']?>">
          <textarea name="message" placeholder="Nhập nội dung phản hồi..." style="min-height:90px"></textarea>
          <label class="fileup" id="fup2" style="margin-top:10px">
            <input type="file" name="attachments[]" multiple accept="image/*,video/*" onchange="showFiles(this,'fup2')">
            <span class="fup-ic">📎</span>
            <span class="fup-text">Đính kèm ảnh / video (tối đa <?=(int)($CFG['upload_max_files']??8)?> file)</span>
          </label>
          <div style="display:flex;justify-content:flex-end;margin-top:12px"><button class="btn btn-green" type="submit">Gửi phản hồi</button></div>
        </form>
      </div>
      <?php } else { ?>
        <div class="tclosed-note">Ticket này đã được đóng. <?= $IS_ADMIN?'Bạn có thể mở lại bên dưới.':'Liên hệ admin nếu cần hỗ trợ thêm.' ?></div>
      <?php } ?>

      <?php if($IS_ADMIN){ $adminList = tk_admin_list(); ?>
      <div class="card tktoolbar" style="margin-top:14px">
        <span class="lbl">Trạng thái</span>
        <?php foreach(['in_progress'=>'Nhận xử lý','closed'=>'Đóng ticket','open'=>'Mở lại'] as $sv=>$lbl){ if($tk['status']===$sv) continue;
          $cls=$sv==='closed'?'bdel':($sv==='in_progress'?'btn-green':'btn-ghost');
          echo '<form method="post" action="?p=ticket" style="display:inline"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="ticket_set"><input type="hidden" name="id" value="'.$tk['id'].'"><input type="hidden" name="status" value="'.$sv.'"><button class="btn btn-sm '.$cls.'" type="submit">'.$lbl.'</button></form>';
        } ?>
      </div>

      <div class="card tkedit" style="margin-top:14px;padding:18px">
        <h4 style="font-weight:800;font-size:1rem;margin-bottom:14px;color:var(--gold)">⚙ Chỉnh sửa ticket</h4>
        <form method="post" action="?p=ticket">
          <input type="hidden" name="csrf" value="<?=$CSRF?>">
          <input type="hidden" name="act" value="ticket_admin_update">
          <input type="hidden" name="id" value="<?=$tk['id']?>">
          <div class="row3">
            <div class="field"><label>Loại ticket</label>
              <select name="category">
                <?php foreach(($CFG['ticket_categories']??[]) as $c){ $sel = ($c===$tk['category'])?' selected':''; echo '<option'.$sel.'>'.h($c).'</option>'; } ?>
              </select>
            </div>
            <div class="field"><label>Server</label>
              <select name="server">
                <option value="">— Không xác định —</option>
                <?php foreach(($CFG['ticket_servers']??[]) as $s){ $sel = ($s===$tk['server'])?' selected':''; echo '<option'.$sel.'>'.h($s).'</option>'; } ?>
              </select>
            </div>
            <div class="field"><label>Người xử lý</label>
              <select name="assignee">
                <option value="__unassign__">— Chưa nhận —</option>
                <?php foreach($adminList as $a){ $sel = (strcasecmp($a, (string)$tk['assignee'])===0)?' selected':''; echo '<option value="'.h($a).'"'.$sel.'>'.h($a).'</option>'; } ?>
              </select>
            </div>
          </div>
          <div style="display:flex;justify-content:flex-end;margin-top:14px"><button class="btn btn-sm btn-green" type="submit">Lưu thay đổi</button></div>
        </form>
      </div>
      <?php } ?>
    <?php } ?>
  </div></section>
