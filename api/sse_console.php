<?php
/* ============================================================================
   SSE CONSOLE — stream realtime log lines từ web_server_log
   Owner + console grantees only. Poll DB mỗi 1s.
   ========================================================================== */

if ($p === 'sse_console') {
  if (!$user || !can_console($user)) { http_response_code(403); exit; }
  @set_time_limit(35); @ini_set('zlib.output_compression', '0');
  header('Content-Type: text/event-stream; charset=utf-8');
  header('Cache-Control: no-cache, no-transform');
  header('X-Accel-Buffering: no');
  header('Connection: keep-alive');
  while (ob_get_level() > 0) @ob_end_flush();
  @ob_implicit_flush(true);

  $serverFilter = $_GET['srv'] ?? 'all'; // 'all' hoặc 1 server-id cụ thể
  $modes = $CFG['modes'] ?? [];
  if ($serverFilter !== 'all' && !isset($modes[$serverFilter])) $serverFilter = 'all';
  $sinceId = (int)($_GET['since'] ?? 0);

  // Nếu since=0 (lần đầu connect): dump 50 log gần nhất để user có context,
  // sau đó stream incremental từ ID cuối.
  $dumpRecent = ($sinceId === 0);

  session_write_close();
  echo ": ok srv=$serverFilter since=$sinceId\n\n"; @flush();

  if ($dumpRecent) {
    try {
      if ($serverFilter === 'all') {
        $st = db()->query("SELECT id, server_id, level, source, message, created FROM web_server_log ORDER BY id DESC LIMIT 50");
      } else {
        $st = db()->prepare("SELECT id, server_id, level, source, message, created FROM web_server_log WHERE server_id=? ORDER BY id DESC LIMIT 50");
        $st->execute([$serverFilter]);
      }
      $recent = array_reverse($st->fetchAll()); // oldest first cho hiển thị đúng thứ tự
      foreach ($recent as $r) {
        $sinceId = max($sinceId, (int)$r['id']);
        echo "event: log\ndata: " . json_encode([
          'id'=>(int)$r['id'],'srv'=>$r['server_id'],'lv'=>$r['level'],
          'src'=>$r['source'],'msg'=>$r['message'],'ts'=>(int)$r['created'],
        ], JSON_UNESCAPED_UNICODE) . "\n\n";
      }
      echo "event: cursor\ndata: " . json_encode(['since'=>$sinceId]) . "\n\n";
      @flush();
    } catch (Exception $e) {}
  }

  $start = time();
  while (time() - $start < 28) {
    try {
      $rows = get_server_logs($serverFilter === 'all' ? 'all' : $serverFilter, $sinceId, 200);
      foreach ($rows as $r) {
        $sinceId = max($sinceId, (int)$r['id']);
        // Đẩy event 'log' với data JSON
        echo "event: log\ndata: " . json_encode([
          'id'     => (int)$r['id'],
          'srv'    => $r['server_id'],
          'lv'     => $r['level'],
          'src'    => $r['source'],
          'msg'    => $r['message'],
          'ts'     => (int)$r['created'],
        ], JSON_UNESCAPED_UNICODE) . "\n\n";
      }
      // Cursor update kể cả khi không có log mới (giữ client biết server còn alive)
      echo "event: cursor\ndata: " . json_encode(['since' => $sinceId]) . "\n\n";
    } catch (Exception $e) {
      echo "event: err\ndata: " . json_encode(['msg' => $e->getMessage()]) . "\n\n";
    }
    @flush();
    if (connection_aborted()) break;
    sleep(1); // poll mỗi 1s = đủ realtime cho user
  }
  echo "event: bye\ndata: {}\n\n"; @flush();
  exit;
}
