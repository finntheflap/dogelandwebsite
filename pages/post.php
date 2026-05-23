<?php
/* Page: post — extracted from index.php lines 2755-2800 */
?>
<?php
  $po=null; try{ $st=db()->prepare("SELECT * FROM web_posts WHERE id=? LIMIT 1"); $st->execute([(int)($_GET['id']??0)]); $po=$st->fetch(); }catch(Exception $e){}
?>
  <section style="padding-top:46px"><div class="wrap"><div class="article">
    <?php if(!$po){ echo '<div class="empty">Bài viết không tồn tại.</div>'; }
      else {
        $tl=post_type_label($po['type']);
        if($po['image']) echo '<div class="cover" style="background-image:url('.h($po['image']).')"></div>';
        echo '<span class="ptag '.h($po['type']).'">'.$tl.'</span>';
        if(!empty($po['server'])) echo ' <span class="ptag-srv">🖥️ '.h($po['server']).'</span>';
        echo '<h1>'.h($po['title']).'</h1>';
        echo '<div class="date">'.($po['event_at']?'Diễn ra: '.date('d/m/Y',(int)($po['event_at']/1000)).' · ':'').'Đăng '.date('d/m/Y',(int)($po['created']/1000)).' bởi '.h($po['author']).'</div>';
        echo '<div class="body">'.nl2br(h($po['content'])).'</div>';
        // bình luận (hỗ trợ trả lời lồng 1 cấp + tag @user)
        $cms=[]; try{ $cs=db()->prepare("SELECT * FROM web_comments WHERE post_id=? ORDER BY id ASC"); $cs->execute([(int)$po['id']]); $cms=$cs->fetchAll(); }catch(Exception $e){}
        $total=count($cms); $parents=[]; $childrenMap=[];
        foreach($cms as $cm){ if(!empty($cm['parent_id'])) $childrenMap[(int)$cm['parent_id']][]=$cm; else $parents[]=$cm; }
        echo '<div class="usep" style="margin:30px 0"></div><h3 style="font-weight:800;font-size:1.2rem;margin-bottom:6px">Bình luận ('.$total.')</h3>';
        if($user) echo '<form method="post" action="?p=post" style="margin:14px 0 8px"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="comment_add"><input type="hidden" name="post_id" value="'.(int)$po['id'].'"><textarea name="content" placeholder="Viết bình luận... (gõ @tên để nhắc người khác)" required style="min-height:80px"></textarea><button class="btn btn-green" type="submit" style="margin-top:10px">Gửi bình luận</button></form>';
        else echo '<p style="color:var(--muted)"><a href="?p=login" style="color:var(--green);font-weight:700">Đăng nhập</a> để bình luận.</p>';
        $sapi=h($CFG['skin_api']);
        $mfmt=function($t){ return preg_replace('/@([A-Za-z0-9_]{3,16})/u','<span class="mention">@$1</span>', h($t)); };
        $cmtRow=function($cm,$isReply,$replyParentId) use($sapi,$IS_ADMIN,$user,$po,$CSRF,$mfmt){
          $own = $IS_ADMIN || strtolower($cm['username'])===strtolower((string)$user);
          $o='<div class="cmt'.($isReply?' reply':'').'"><img src="'.$sapi.'/avatar/'.urlencode($cm['username']).'/38" data-skin-user="'.h($cm['username']).'" data-skin-size="38" onerror="skinFallback(this)" alt=""><div class="cb"><div><span class="cu">'.h($cm['username']).'</span><span class="ct">'.date('d/m/Y H:i',(int)($cm['created']/1000)).'</span></div><div class="cc">'.$mfmt($cm['content']).'</div>';
          if($user){
            $o.='<div class="cact"><button type="button" class="crep" onclick="toggleReply('.(int)$cm['id'].')">↩ Trả lời</button></div>';
            $o.='<form class="replybox" id="rb'.(int)$cm['id'].'" method="post" action="?p=post" style="display:none;margin-left:0">'
              .'<input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="comment_add"><input type="hidden" name="post_id" value="'.(int)$po['id'].'"><input type="hidden" name="parent_id" value="'.(int)$replyParentId.'">'
              .'<textarea name="content" data-at="@'.h($cm['username']).' " placeholder="Trả lời '.h($cm['username']).'..." required></textarea>'
              .'<div style="display:flex;gap:8px;margin-top:8px"><button class="btn btn-green btn-sm" type="submit">Gửi trả lời</button><button class="btn btn-ghost btn-sm" type="button" onclick="toggleReply('.(int)$cm['id'].')">Huỷ</button></div></form>';
          }
          $o.='</div>';
          if($own) $o.='<form method="post" action="?p=post" onsubmit="return confirm(\'Xoá bình luận?\')"><input type="hidden" name="csrf" value="'.$CSRF.'"><input type="hidden" name="act" value="comment_delete"><input type="hidden" name="id" value="'.(int)$cm['id'].'"><input type="hidden" name="post_id" value="'.(int)$po['id'].'"><button class="btn btn-sm bdel" type="submit" style="padding:5px 10px">Xoá</button></form>';
          $o.='</div>';
          return $o;
        };
        foreach($parents as $cm){
          echo $cmtRow($cm,false,(int)$cm['id']);
          foreach(($childrenMap[(int)$cm['id']]??[]) as $rc) echo $cmtRow($rc,true,(int)$cm['id']);
        }
        if($user) echo '<script>function toggleReply(id){var f=document.getElementById("rb"+id);if(!f)return;var open=f.style.display==="none"||!f.style.display;f.style.display=open?"block":"none";if(open){var t=f.querySelector("textarea");if(t){if(!t.value)t.value=t.getAttribute("data-at")||"";t.focus();t.selectionStart=t.selectionEnd=t.value.length;}}}</script>';
      } ?>
    <a class="btn btn-ghost" href="?p=home" style="margin-top:28px">← Về bảng tin</a>
  </div></div></section>
