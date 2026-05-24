<?php
/* ============================================================================
   INVENTORY SAFETY — history + item movement log + cleanup
   Memory bounded: per-player cap + TTL. Cleanup throttled (1 lần / 5 phút).
   ========================================================================== */

/* Giới hạn lưu trữ — tinh chỉnh nếu DB phình. */
const INV_HISTORY_KEEP_PER_USER = 50;          // max snapshot/player
const INV_HISTORY_TTL_DAYS      = 30;          // xoá row cũ hơn N ngày
const ITEM_LOG_TTL_DAYS         = 90;          // xoá log cũ hơn N ngày
const ITEM_LOG_MAX_ROWS         = 500000;      // hard cap tổng (auto-trim oldest)

/* Decode gzip snapshot từ DB (LONGBLOB) → array. */
function inv_hist_decode($gz){
  if (empty($gz)) return [];
  $json = @gzdecode($gz);
  if ($json === false) return [];
  $arr = json_decode($json, true);
  return is_array($arr) ? $arr : [];
}

/* Lấy lịch sử snapshot của 1 player (KHÔNG decode JSON — chỉ metadata). */
function inv_history_list($username, $limit=50){
  $limit = max(1, min(200, (int)$limit));
  try {
    $st = db()->prepare("SELECT id, server_id, reason, item_count, created
                         FROM web_inventory_history
                         WHERE username=?
                         ORDER BY id DESC LIMIT $limit");
    $st->execute([$username]);
    return $st->fetchAll();
  } catch (Exception $e) { return []; }
}

/* Lấy 1 snapshot cụ thể + decode. */
function inv_history_get($id){
  try {
    $st = db()->prepare("SELECT * FROM web_inventory_history WHERE id=?");
    $st->execute([(int)$id]);
    $r = $st->fetch();
    if (!$r) return null;
    $r['items'] = inv_hist_decode($r['snapshot_gz']);
    unset($r['snapshot_gz']);
    return $r;
  } catch (Exception $e) { return null; }
}

/* Lấy item log của 1 player. */
function item_log_list($username=null, $action=null, $limit=100){
  $limit = max(1, min(500, (int)$limit));
  try {
    $sql = "SELECT id, username, server_id, item_key, item_name, qty, action, direction,
                   source_type, source_id, actor, note, created
            FROM web_item_log WHERE 1=1";
    $args = [];
    if ($username !== null && $username !== '') { $sql .= " AND username=?"; $args[] = $username; }
    if ($action !== null && $action !== '')     { $sql .= " AND action=?";   $args[] = $action; }
    $sql .= " ORDER BY id DESC LIMIT $limit";
    $st = db()->prepare($sql); $st->execute($args);
    return $st->fetchAll();
  } catch (Exception $e) { return []; }
}

/* Write 1 row item log từ web side (vd: admin sửa kho, gift code).
   Plugin gọi qua DB trực tiếp nên không cần helper Java. */
function item_log_write($username, $action, $opts=[]){
  try {
    db()->prepare("INSERT INTO web_item_log
      (username, server_id, item_key, item_name, qty, action, direction, source_type, source_id, actor, note, created)
      VALUES(?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
        $username,
        (string)($opts['server_id']   ?? ''),
        (string)($opts['item_key']    ?? ''),
        (string)($opts['item_name']   ?? ''),
        (int)   ($opts['qty']         ?? 1),
        $action,
        (int)   ($opts['direction']   ?? 0),  // 1=in (player nhận), -1=out (player mất), 0=neutral
        (string)($opts['source_type'] ?? ''),
        (int)   ($opts['source_id']   ?? 0),
        (string)($opts['actor']       ?? ''),
        (string)($opts['note']        ?? ''),
        ms()
      ]);
    return true;
  } catch (Exception $e) { return false; }
}

/* Cleanup throttled qua DB marker (5 phút/lần) — an toàn gọi nhiều request đồng thời.
   Mỗi lần xoá batch nhỏ (500-1000 row) để không lag DB. */
function inv_safety_cleanup(){
  try {
    $pdo = db();
    // Throttle check qua web_settings table
    $st = $pdo->query("SELECT v FROM web_settings WHERE k='inv_cleanup_last'");
    $last = $st ? (int)$st->fetchColumn() : 0;
    $now = ms();
    if ($now - $last < 300000) return; // 5 phút
    // Claim ownership trước khi work (atomic via UPSERT)
    $pdo->prepare("INSERT INTO web_settings(k,v) VALUES('inv_cleanup_last',?)
                   ON DUPLICATE KEY UPDATE v=VALUES(v)")->execute([(string)$now]);
    // 1) Inventory history: xoá row > TTL
    $cutoffTime = $now - INV_HISTORY_TTL_DAYS * 86400000;
    $pdo->prepare("DELETE FROM web_inventory_history WHERE created<? LIMIT 500")->execute([$cutoffTime]);
    // 2) Inventory history: per-user cap (giữ 50 row mới nhất / user)
    //    Tìm các user vượt cap, xoá oldest
    $over = $pdo->query("SELECT username FROM web_inventory_history
                         GROUP BY username HAVING COUNT(*) > " . INV_HISTORY_KEEP_PER_USER . "
                         LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($over as $u) {
      $st = $pdo->prepare("SELECT id FROM web_inventory_history WHERE username=?
                            ORDER BY id DESC LIMIT 1 OFFSET " . INV_HISTORY_KEEP_PER_USER);
      $st->execute([$u]);
      $cutoffId = (int)$st->fetchColumn();
      if ($cutoffId > 0) {
        $pdo->prepare("DELETE FROM web_inventory_history WHERE username=? AND id<=? LIMIT 100")
            ->execute([$u, $cutoffId]);
      }
    }
    // 3) Item log: xoá > TTL
    $cutoffLog = $now - ITEM_LOG_TTL_DAYS * 86400000;
    $pdo->prepare("DELETE FROM web_item_log WHERE created<? LIMIT 1000")->execute([$cutoffLog]);
    // 4) Item log: hard cap tổng (chống burst)
    $total = (int)$pdo->query("SELECT COUNT(*) FROM web_item_log")->fetchColumn();
    if ($total > ITEM_LOG_MAX_ROWS) {
      $excess = $total - ITEM_LOG_MAX_ROWS;
      $pdo->prepare("DELETE FROM web_item_log ORDER BY id ASC LIMIT ?")->bindValue(1, min(2000,$excess), PDO::PARAM_INT);
      $pdo->exec("DELETE FROM web_item_log ORDER BY id ASC LIMIT " . min(2000, $excess));
    }
  } catch (Exception $e) { /* ignore */ }
}
