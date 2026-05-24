<?php
/* ============================================================================
   SERVERS — đọc heartbeat + player stats từ plugin DogelandSync
   ========================================================================== */

/* Server được coi là online nếu plugin gửi heartbeat trong vòng 30 giây qua. */
const SERVER_ONLINE_TIMEOUT_MS = 30000;

/* Lấy meta của 1 server từ DB (admin sửa qua web UI). Fallback config nếu DB rỗng. */
function server_meta($id){
  global $CFG;
  try {
    $st = db()->prepare("SELECT * FROM web_servers WHERE server_id=?");
    $st->execute([$id]);
    $r = $st->fetch();
    if ($r) return $r;
  } catch (Exception $e) {}
  // Fallback: chưa có row trong DB → đọc config legacy
  $name = $CFG['modes'][$id] ?? $id;
  $m = $CFG['modes_meta'][$id] ?? [];
  return [
    'server_id'=>$id, 'name'=>$name, 'tagline'=>'', 'description'=>'',
    'image_url'=>$m['image'] ?? '', 'banner_url'=>'',
    'accent_color'=>'#f2b631', 'ip'=>'', 'version'=>'', 'features'=>'',
    'discord_url'=>'', 'home_show'=>($m['home'] ?? true) ? 1 : 0, 'sort_order'=>0,
  ];
}

/* Trả về list server với trạng thái + số người online (+ meta DB).
   $onlyHome=true: chỉ trả về server có home_show=1 (loại lobby/hidden). */
function get_servers_status($onlyHome=false){
  global $CFG;
  $modes = $CFG['modes'] ?? [];
  // Lấy tất cả meta 1 lần (1 query thay vì N)
  $metaMap = [];
  try {
    foreach (db()->query("SELECT * FROM web_servers ORDER BY sort_order ASC, server_id ASC")->fetchAll() as $r) {
      $metaMap[$r['server_id']] = $r;
    }
  } catch (Exception $e) {}
  // Nếu DB rỗng (chưa migrate), dùng modes config order
  $orderIds = !empty($metaMap) ? array_keys($metaMap) : array_keys($modes);
  $out = [];
  foreach ($orderIds as $id) {
    if (!isset($modes[$id])) continue; // server id không có trong config → bỏ (config là source of truth cho IDs)
    $meta = $metaMap[$id] ?? server_meta($id);
    if ($onlyHome && empty($meta['home_show'])) continue;
    $out[$id] = [
      'id'=>$id,
      'name'=>$meta['name'] ?: $modes[$id],
      'online'=>false, 'count'=>0, 'players'=>[], 'version'=>'',
      'image'=>$meta['image_url'] ?? '',
      'banner'=>$meta['banner_url'] ?? '',
      'tagline'=>$meta['tagline'] ?? '',
      'description'=>$meta['description'] ?? '',
      'accent'=>$meta['accent_color'] ?? '#f2b631',
      'ip'=>$meta['ip'] ?? '',
      'mc_version'=>$meta['version'] ?? '',
      'features'=>$meta['features'] ?? '',
      'discord_url'=>$meta['discord_url'] ?? '',
      'home_show'=>!empty($meta['home_show']),
    ];
  }
  try {
    $now = ms();
    $st = db()->query("SELECT * FROM web_sync_heartbeat");
    foreach ($st->fetchAll() as $r) {
      $id = $r['server_id'];
      if (!isset($out[$id])) continue; // server không có trong config → bỏ qua
      $alive = ((int)$r['last_beat'] + SERVER_ONLINE_TIMEOUT_MS) > $now;
      $players = [];
      if (!empty($r['online_players'])) {
        // Support cả JSON array (format mới) và CSV string (format plugin hiện tại)
        $j = json_decode($r['online_players'], true);
        if (is_array($j)) $players = $j;
        else $players = array_values(array_filter(array_map('trim', explode(',', (string)$r['online_players']))));
      }
      $out[$id]['online']  = $alive;
      $out[$id]['count']   = $alive ? count($players) : 0;
      $out[$id]['players'] = $alive ? $players : [];
      $out[$id]['version'] = (string)($r['plugin_version'] ?? '');
    }
  } catch (Exception $e) { /* table chưa migrate xong */ }
  return $out;
}

/* Tổng số người online toàn network (đếm cả lobby + hidden server). */
function get_total_online(){
  $tot = 0;
  foreach (get_servers_status(false) as $s) $tot += (int)$s['count'];
  return $tot;
}

/* Top player theo metric trong 1 server cụ thể.
   $metric ∈ ['playtime_sec','level','balance','mob_kills','player_kills','deaths']. */
function get_server_top($serverId, $metric='playtime_sec', $limit=50){
  $allowed = ['playtime_sec','level','xp','balance','mob_kills','player_kills','deaths'];
  if (!in_array($metric, $allowed, true)) $metric = 'playtime_sec';
  try {
    $st = db()->prepare("SELECT username AS name, $metric AS val
                         FROM web_player_stats
                         WHERE server_id=? AND $metric>0
                         ORDER BY val DESC LIMIT $limit");
    $st->execute([$serverId]);
    return $st->fetchAll();
  } catch (Exception $e) { return []; }
}

/* Rank của 1 player trong 1 server theo metric (1-based). 0 nếu chưa có data. */
function get_player_server_rank($username, $serverId, $metric='playtime_sec'){
  $allowed = ['playtime_sec','level','xp','balance','mob_kills','player_kills','deaths'];
  if (!in_array($metric, $allowed, true)) return [0, 0];
  try {
    $mv = db()->prepare("SELECT COALESCE($metric,0) FROM web_player_stats WHERE username=? AND server_id=?");
    $mv->execute([$username, $serverId]);
    $myVal = (int)$mv->fetchColumn();
    if ($myVal <= 0) return [0, 0];
    $hr = db()->prepare("SELECT COUNT(*) FROM web_player_stats WHERE server_id=? AND $metric>?");
    $hr->execute([$serverId, $myVal]);
    return [(int)$hr->fetchColumn() + 1, $myVal];
  } catch (Exception $e) { return [0, 0]; }
}

/* Format hh:mm cho playtime_sec. */
function fmt_playtime($sec){
  $sec = (int)$sec; if ($sec <= 0) return '0h';
  $h = intdiv($sec, 3600); $m = intdiv($sec % 3600, 60);
  if ($h > 0) return $h.'h'.($m>0?' '.$m.'m':'');
  return $m.'m';
}

/* Lấy log mới hơn $sinceId của 1 server (hoặc all). Max 500 dòng.
   Trả về [id, server_id, level, source, message, created]. */
function get_server_logs($serverId=null, $sinceId=0, $limit=500){
  $limit = max(1, min(2000, (int)$limit));
  $sinceId = max(0, (int)$sinceId);
  try {
    if ($serverId === null || $serverId === 'all' || $serverId === '') {
      $st = db()->prepare("SELECT id, server_id, level, source, message, created
                           FROM web_server_log
                           WHERE id > ?
                           ORDER BY id ASC LIMIT $limit");
      $st->execute([$sinceId]);
    } else {
      $st = db()->prepare("SELECT id, server_id, level, source, message, created
                           FROM web_server_log
                           WHERE server_id = ? AND id > ?
                           ORDER BY id ASC LIMIT $limit");
      $st->execute([$serverId, $sinceId]);
    }
    return $st->fetchAll();
  } catch (Exception $e) { return []; }
}

/* Cleanup: mỗi server giữ tối đa $keep dòng (default 3000). Chạy mỗi lần fetch.
   Lightweight: 1 DELETE per call, batch 200 rows max để không lag DB. */
function cleanup_server_logs($keep=3000){
  global $CFG;
  static $lastCleanup = 0;
  $now = ms();
  if ($now - $lastCleanup < 60000) return; // throttle: 1 phút 1 lần
  $lastCleanup = $now;
  try {
    foreach (array_keys($CFG['modes'] ?? []) as $sid) {
      // Tìm id của row thứ ($keep+1) tính từ cuối, xoá mọi row có id <= nó
      $st = db()->prepare("SELECT id FROM web_server_log WHERE server_id=? ORDER BY id DESC LIMIT 1 OFFSET ?");
      $st->execute([$sid, $keep]);
      $cutoffId = (int)$st->fetchColumn();
      if ($cutoffId > 0) {
        db()->prepare("DELETE FROM web_server_log WHERE server_id=? AND id<=? LIMIT 200")->execute([$sid, $cutoffId]);
      }
    }
  } catch (Exception $e) { /* ignore */ }
}

/* ============================================================================
   IMAGE UPLOAD cho server (banner / card / gallery)
   Lưu vào uploads/servers/<server_id>/ — phục vụ ảnh từ chính domain (ko bị CORS).
   ============================================================================ */
const SRV_UPLOAD_DIR_NAME = 'uploads/servers';
const SRV_UPLOAD_MAX_BYTES = 8 * 1024 * 1024; // 8 MB / ảnh
const SRV_UPLOAD_ALLOW_EXT = ['jpg','jpeg','png','gif','webp','avif'];

function srv_upload_dir($serverId){
  $base = __DIR__ . '/../' . SRV_UPLOAD_DIR_NAME . '/' . preg_replace('/[^a-z0-9_-]/i','',$serverId);
  if (!is_dir($base)) @mkdir($base, 0775, true);
  // .htaccess chặn thực thi PHP trong thư mục upload
  $hta = $base . '/.htaccess';
  if (!file_exists($hta)) {
    @file_put_contents($hta, "Options -ExecCGI\nAddType text/plain .php .php3 .phtml .pht .phar\n<FilesMatch \"\\.(php|php3|phtml|pht|phar|cgi|pl|py|jsp|asp|sh|exe)$\">\n  Require all denied\n</FilesMatch>\n");
  }
  return $base;
}

/* Upload 1 file từ $_FILES[$field]. Trả về URL tương đối (uploads/servers/...) hoặc '' nếu fail. */
function srv_upload_single($field, $serverId){
  if (empty($_FILES[$field]) || empty($_FILES[$field]['name'])) return '';
  $f = $_FILES[$field];
  if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return '';
  if (!is_uploaded_file($f['tmp_name'])) return '';
  if ((int)$f['size'] <= 0 || (int)$f['size'] > SRV_UPLOAD_MAX_BYTES) return '';
  $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, SRV_UPLOAD_ALLOW_EXT, true)) return '';
  // Verify là ảnh thật
  $info = @getimagesize($f['tmp_name']); if (!$info) return '';
  $dir = srv_upload_dir($serverId);
  $base = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . preg_replace('/[^a-z0-9]/','',$ext);
  $dest = $dir . '/' . $base;
  if (!@move_uploaded_file($f['tmp_name'], $dest)) return '';
  return SRV_UPLOAD_DIR_NAME . '/' . preg_replace('/[^a-z0-9_-]/i','',$serverId) . '/' . $base;
}

/* Upload nhiều file từ $_FILES[$field] (multiple). Trả về array URLs. */
function srv_upload_multiple($field, $serverId){
  if (empty($_FILES[$field]) || empty($_FILES[$field]['name'])) return [];
  $files = $_FILES[$field];
  if (!is_array($files['name'])) return []; // chỉ xử lý multiple
  $out = []; $n = count($files['name']);
  for ($i=0; $i<$n; $i++) {
    if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
    if (!is_uploaded_file($files['tmp_name'][$i])) continue;
    if ((int)$files['size'][$i] <= 0 || (int)$files['size'][$i] > SRV_UPLOAD_MAX_BYTES) continue;
    $ext = strtolower(pathinfo((string)$files['name'][$i], PATHINFO_EXTENSION));
    if (!in_array($ext, SRV_UPLOAD_ALLOW_EXT, true)) continue;
    $info = @getimagesize($files['tmp_name'][$i]); if (!$info) continue;
    $dir = srv_upload_dir($serverId);
    $base = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . preg_replace('/[^a-z0-9]/','',$ext);
    $dest = $dir . '/' . $base;
    if (!@move_uploaded_file($files['tmp_name'][$i], $dest)) continue;
    $out[] = SRV_UPLOAD_DIR_NAME . '/' . preg_replace('/[^a-z0-9_-]/i','',$serverId) . '/' . $base;
  }
  return $out;
}

/* Decode gallery_json → array URLs. */
function srv_gallery_arr($jsonStr){
  if (empty($jsonStr)) return [];
  $j = json_decode($jsonStr, true);
  return is_array($j) ? array_values(array_filter($j, 'is_string')) : [];
}

/* Permission: user gõ Console không? Supervisor luôn có. Admin có nếu console=1. Support không. */
function can_console($username){
  if (!$username) return false;
  if (is_supervisor($username)) return true;
  try {
    $st = db()->prepare("SELECT role, console FROM web_admins WHERE LOWER(username)=? LIMIT 1");
    $st->execute([strtolower($username)]);
    $r = $st->fetch();
    if (!$r) return false;
    if (($r['role'] ?? '') === 'support') return false;
    return !empty($r['console']);
  } catch (Exception $e) { return false; }
}
