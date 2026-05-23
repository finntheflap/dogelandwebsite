<?php
/* ============================================================================
   ITEMS — chuẩn hoá mã item + icon + upload ảnh tuỳ chỉnh
   ========================================================================== */

/* Chuẩn hoá mã item: "Diamond Sword" -> "diamond_sword" */
function ikey_norm($s){ $s=strtolower(trim((string)$s)); $s=preg_replace('/[\s\-]+/','_',$s); return preg_replace('/[^a-z0-9_]/','', $s); }
/* URL icon vật phẩm từ API (mã item dạng diamond_sword) */
function item_icon($key){
  global $CFG; $key=strtolower(trim((string)$key));
  $key=preg_replace('/[\s\-]+/','_',$key);          // "Diamond Sword" -> "diamond_sword"
  $key=preg_replace('/[^a-z0-9_]/','', $key);
  if($key==='') return '?img=doge';
  return sprintf($CFG['item_icon_api'], rawurlencode($key));
}
/* Ảnh hiển thị cho 1 vật phẩm: ưu tiên ảnh tuỳ chỉnh, nếu trống thì lấy icon từ API theo mã item */
function item_img($image,$key=''){ $image=trim((string)$image); return $image!=='' ? $image : item_icon($key); }
/* Tải ảnh vật phẩm lên (field=image_file). Trả URL tương đối, hoặc '' nếu không có/không hợp lệ. */
function item_upload($field='image_file'){
  global $CFG;
  if(empty($_FILES[$field]) || ($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) return '';
  $f=$_FILES[$field]; $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
  $ok=$CFG['upload_image_ext']??['jpg','jpeg','png','gif','webp','avif','bmp'];
  if(!in_array($ext,$ok,true)) return '';
  if($f['size'] > (int)($CFG['upload_max_mb']??25)*1048576) return '';
  $dir=__DIR__.'/uploads/items'; $url='uploads/items';
  if(!is_dir($dir)) @mkdir($dir,0775,true);
  $name='it_'.bin2hex(random_bytes(8)).'.'.$ext;
  if(@move_uploaded_file($f['tmp_name'],$dir.'/'.$name)) return $url.'/'.$name;
  return '';
}
