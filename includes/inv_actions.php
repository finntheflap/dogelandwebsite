<?php
/* ============================================================================
   INV ACTIONS — helper enqueue + poll action queue cho plugin DogelandSync
   ----------------------------------------------------------------------------
   Web không sửa web_inventory / web_market / web_auctions trực tiếp khi cần
   thao tác trên inventory thật của player. Thay vào đó:

     $aid = inv_action_enqueue('list_market', $username, $mode, [
       'inv_id' => 123, 'qty' => 1, 'price' => 500, 'description' => '...']);
     $res = inv_action_wait($aid, 5);  // timeout 5s
     if ($res['status'] === 'done')   ...
     if ($res['status'] === 'failed') ...
     if ($res['status'] === 'pending') ... // plugin chưa pick up — show "đang xử lý"

   Plugin sẽ pick up bảng web_inv_actions, xử lý trên main thread MC, ghi kết
   quả lại vào cột `result` (JSON) + status='done'|'failed'.
   ========================================================================== */

/** Encode payload thành JSON an toàn (UTF-8 + JSON_UNESCAPED_UNICODE). */
function inv_action_enqueue(string $action, string $username, string $mode, array $payload, string $requestedBy = ''): int {
  $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
  if ($json === false) throw new RuntimeException('payload encode lỗi');
  $st = db()->prepare("INSERT INTO web_inv_actions(username,mode,action,payload,requested_by,status,created) VALUES(?,?,?,?,?,'pending',?)");
  $st->execute([$username, $mode, $action, $json, $requestedBy ?: $username, ms()]);
  return (int)db()->lastInsertId();
}

/** Lấy trạng thái 1 action. */
function inv_action_status(int $id): ?array {
  $st = db()->prepare("SELECT id,status,result,created,processed FROM web_inv_actions WHERE id=?");
  $st->execute([$id]);
  $r = $st->fetch();
  return $r ?: null;
}

/**
 * Poll action cho đến khi status != 'pending' OR hết timeout.
 * Trả ['status'=>..., 'result'=>parsed-json, 'raw'=>...].
 * status có thể là: 'done', 'failed', 'pending' (timeout), hoặc 'processing-xxxx' (đang chạy).
 */
function inv_action_wait(int $id, float $timeoutSec = 5.0): array {
  $deadline = microtime(true) + $timeoutSec;
  while (true) {
    $r = inv_action_status($id);
    if (!$r) return ['status' => 'missing', 'result' => null, 'raw' => null];
    $st = (string)$r['status'];
    if ($st === 'done' || $st === 'failed') {
      $parsed = null;
      if (!empty($r['result'])) {
        $parsed = json_decode($r['result'], true);
        if ($parsed === null && $st === 'failed') $parsed = ['error' => $r['result']];
      }
      return ['status' => $st, 'result' => $parsed, 'raw' => $r['result']];
    }
    if (microtime(true) >= $deadline) {
      return ['status' => $st, 'result' => null, 'raw' => $r['result']];
    }
    usleep(200_000); // 200ms — match plugin poll interval ~1.5s, đủ nhanh để bắt
  }
}

/** Plugin có còn sống không (heartbeat <= 30s trước)? */
function inv_plugin_alive(string $serverId): bool {
  try {
    $st = db()->prepare("SELECT last_beat FROM web_sync_heartbeat WHERE server_id=?");
    $st->execute([$serverId]);
    $r = $st->fetch();
    if (!$r) return false;
    return (ms() - (int)$r['last_beat']) <= 30_000;
  } catch (Exception $e) { return false; }
}

/** Player có online (theo heartbeat plugin)? */
function inv_player_online(string $username, string $serverId): bool {
  try {
    $st = db()->prepare("SELECT online_players,last_beat FROM web_sync_heartbeat WHERE server_id=?");
    $st->execute([$serverId]);
    $r = $st->fetch();
    if (!$r) return false;
    if (ms() - (int)$r['last_beat'] > 30_000) return false;
    $names = array_map('strtolower', array_filter(array_map('trim', explode(',', (string)$r['online_players']))));
    return in_array(strtolower($username), $names, true);
  } catch (Exception $e) { return false; }
}
