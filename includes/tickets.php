<?php
/* ============================================================================
   TICKETS — upload attachments + render + admin list
   ========================================================================== */

/* Upload các file từ $_FILES['attachments'] (multiple). Trả về mảng [['t'=>'image|video','u'=>'uploads/tickets/xxx.png','n'=>'tên gốc','s'=>bytes], ...] */
function tk_upload_files($field='attachments'){
  global $CFG;
  if(empty($_FILES[$field]) || empty($_FILES[$field]['name'])) return [];
  $files = $_FILES[$field];
  // Chuẩn hoá: nếu chỉ 1 file (không phải array) thì wrap lại
  if(!is_array($files['name'])){
    $files = ['name'=>[$files['name']], 'type'=>[$files['type']], 'tmp_name'=>[$files['tmp_name']], 'error'=>[$files['error']], 'size'=>[$files['size']]];
  }
  $dir = $CFG['upload_dir']; $urlBase = rtrim($CFG['upload_url'],'/');
  if(!is_dir($dir)){ @mkdir($dir, 0775, true); }
  if(!is_dir($dir) || !is_writable($dir)) return []; // nếu không tạo được thì bỏ qua

  // .htaccess chống thực thi PHP trong thư mục upload (chạy 1 lần)
  $hta = $dir.'/.htaccess';
  if(!file_exists($hta)){
    @file_put_contents($hta, "Options -ExecCGI\nAddType text/plain .php .php3 .phtml .pht .phar\n<FilesMatch \"\\.(php|php3|phtml|pht|phar|cgi|pl|py|jsp|asp|sh|exe)$\">\n  Require all denied\n</FilesMatch>\n");
  }

  $imgExt = array_map('strtolower', $CFG['upload_image_ext'] ?? []);
  $vidExt = array_map('strtolower', $CFG['upload_video_ext'] ?? []);
  $maxBytes = (int)($CFG['upload_max_mb'] ?? 25) * 1024 * 1024;
  $maxFiles = (int)($CFG['upload_max_files'] ?? 8);

  $out = []; $n = count($files['name']);
  for($i=0; $i<$n && count($out) < $maxFiles; $i++){
    if(($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
    $tmp = $files['tmp_name'][$i]; if(!is_uploaded_file($tmp)) continue;
    $size = (int)$files['size'][$i]; if($size <= 0 || $size > $maxBytes) continue;
    $orig = (string)$files['name'][$i];
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if($ext===''){ continue; }
    $type = '';
    if(in_array($ext, $imgExt, true)) $type = 'image';
    elseif(in_array($ext, $vidExt, true)) $type = 'video';
    else continue; // bỏ qua loại không cho phép

    // Xác minh thêm: với ảnh, dùng getimagesize để chắc đó là ảnh thật
    if($type === 'image'){
      $info = @getimagesize($tmp); if(!$info) continue;
    } else {
      // Với video: kiểm tra MIME bằng finfo nếu có
      if(function_exists('finfo_open')){
        $f = @finfo_open(FILEINFO_MIME_TYPE);
        if($f){ $mime = @finfo_file($f, $tmp); @finfo_close($f);
          if($mime && strpos($mime, 'video/') !== 0) continue;
        }
      }
    }

    // Tên file an toàn: ngẫu nhiên + extension
    $safeExt = preg_replace('/[^a-z0-9]/', '', $ext); if($safeExt==='') continue;
    $base = date('Ymd_His').'_'.bin2hex(random_bytes(5)).'.'.$safeExt;
    $dest = $dir.'/'.$base;
    if(!@move_uploaded_file($tmp, $dest)) continue;
    @chmod($dest, 0644);

    // Lưu tên gốc đã sanitize để hiển thị
    $displayName = preg_replace('/[^\p{L}\p{N}\.\-_ ]+/u', '', $orig);
    if(mb_strlen($displayName) > 80) $displayName = mb_substr($displayName, 0, 80).'…';

    $out[] = ['t'=>$type, 'u'=>$urlBase.'/'.$base, 'n'=>$displayName, 's'=>$size];
  }
  return $out;
}

function tk_render_atts($json){
  if(!$json) return '';
  $arr = json_decode($json, true); if(!is_array($arr) || !$arr) return '';
  $html = '<div class="atts">';
  foreach($arr as $a){
    $u = h($a['u'] ?? ''); $t = $a['t'] ?? 'link'; $n = h($a['n'] ?? ''); if($u==='') continue;
    if($t==='image'){
      $html .= '<a class="att-img" href="'.$u.'" target="_blank" rel="noopener" title="'.$n.'"><img src="'.$u.'" alt="'.$n.'" loading="lazy"></a>';
    } elseif($t==='video'){
      $html .= '<video class="att-vid" src="'.$u.'" controls preload="metadata" title="'.$n.'"></video>';
    } else {
      $html .= '<a class="att-link" href="'.$u.'" target="_blank" rel="noopener">🔗 '.($n!==''?$n:$u).'</a>';
    }
  }
  $html .= '</div>';
  return $html;
}

/* Lấy danh sách admin để gán ticket */
function tk_admin_list(){
  try{ return db()->query("SELECT username FROM web_admins ORDER BY username")->fetchAll(PDO::FETCH_COLUMN); }
  catch(Exception $e){ return []; }
}
