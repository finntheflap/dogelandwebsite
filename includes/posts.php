<?php
/* ============================================================================
   POSTS — query bài viết + render card
   ========================================================================== */

function get_posts($type=null,$limit=30){
  try{
    if(is_array($type) && $type){
      $ph = implode(',', array_fill(0, count($type), '?'));
      $st=db()->prepare("SELECT * FROM web_posts WHERE type IN ($ph) ORDER BY pinned DESC,id DESC LIMIT $limit");
      $st->execute(array_values($type));
    } elseif($type){
      $st=db()->prepare("SELECT * FROM web_posts WHERE type=? ORDER BY pinned DESC,id DESC LIMIT $limit");
      $st->execute([$type]);
    } else {
      $st=db()->query("SELECT * FROM web_posts ORDER BY pinned DESC,id DESC LIMIT $limit");
    }
    return $st->fetchAll();
  }catch(Exception $e){ return []; }
}
/* Các loại bài viết hợp lệ — dùng cho cả validate (post_save) và label hiển thị. */
function post_type_label($t){
  $m=['event'=>'Sự kiện','news'=>'Thông báo','guide'=>'Cẩm nang','rules'=>'Nội quy','update'=>'Cập nhật'];
  return $m[$t]??'Thông báo';
}
function post_types_allowed(){ return ['event','news','guide','rules','update']; }
function post_card($po){
  $tl = post_type_label($po['type']);
  $img = $po['image'] ? '<div class="img" style="background-image:url('.h($po['image']).')"></div>' : '';
  $clean = trim(preg_replace('/\s+/',' ',$po['content']));
  $ex = function_exists('mb_substr') ? mb_substr($clean,0,140,'UTF-8') : substr($clean,0,140);
  if((function_exists('mb_strlen')?mb_strlen($po['content'],'UTF-8'):strlen($po['content']))>140) $ex.='…';
  $date = $po['event_at'] ? 'Diễn ra '.date('d/m/Y',(int)($po['event_at']/1000)) : 'Đăng '.date('d/m/Y',(int)($po['created']/1000));
  $srv = !empty($po['server']) ? '<span class="ptag-srv">🖥️ '.h($po['server']).'</span>' : '';
  /* Chuỗi search gộp title + content + server để JS lọc nhanh không cần round-trip. */
  $searchStr = mb_strtolower(trim(($po['title']??'').' '.preg_replace('/\s+/',' ',$po['content']??'').' '.($po['server']??'')),'UTF-8');
  return '<div class="card post" data-type="'.h($po['type']).'" data-server="'.h(strtolower((string)($po['server']??''))).'" data-search="'.h($searchStr).'">'.$img.'<div class="pb">'
       .'<div class="ptag-row"><span class="ptag '.h($po['type']).'">'.$tl.'</span>'.$srv.'</div>'
       .'<h3>'.h($po['title']).'</h3>'
       .'<div class="date">'.$date.'</div>'
       .'<p>'.h($ex).'</p>'
       .'<a class="more" href="?p=post&id='.(int)$po['id'].'">Xem thêm →</a></div></div>';
}
