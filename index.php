<?php
/* ============================================================================
   DOGELAND NETWORK — Front controller (router)
   ----------------------------------------------------------------------------
   - Mọi request đi qua đây.
   - Bootstrap session + helpers, dispatch theo $_GET['p'] và $_POST['act'].
   - Logic không đổi so với bản 4175-dòng cũ — chỉ tách file.
   ========================================================================== */

// 1) Serve image assets sớm (?img=) trước khi load DB/session
require __DIR__ . '/includes/assets.php';

// 2) Bootstrap: config, session, helpers
require __DIR__ . '/includes/bootstrap.php';

// 3) POST handlers (act=...)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_ok()) { flash(['error', 'Phiên làm việc hết hạn, vui lòng thử lại.']); redirect($p); }
  $act = $_POST['act'] ?? '';
  require __DIR__ . '/actions/handle_post.php';
}

// 4) API endpoints (sớm — return JSON / SSE rồi exit)
if ($p === 'api')         { require __DIR__ . '/api/api.php'; }
if ($p === 'sse')         { require __DIR__ . '/api/sse.php'; }
require __DIR__ . '/api/auth_callbacks.php'; // verify / logout / discord_cb / updates

// 5) Yêu cầu đăng nhập với một số trang
$NEEDS_LOGIN = ['topup', 'profile', 'tickets', 'ticket'];
if (in_array($p, $NEEDS_LOGIN, true) && !$user) {
  flash(['error', 'Bạn cần đăng nhập để truy cập trang này.']);
  redirect('login');
}

// 6) View — header + page + footer
require __DIR__ . '/views/header.php';

$PAGES = [
  'home', 'events', 'guide', 'rules', 'info', 'post', 'admin', 'top',
  'profile', 'tickets', 'ticket', 'topup', 'shop', 'auction', 'ranks',
  'market', 'login', 'register', 'verify',
];

echo '<main>';
if (in_array($p, $PAGES, true)) {
  require __DIR__ . '/pages/' . $p . '.php';
} else {
  http_response_code(404);
  echo '<div class="phead"><h1>404</h1><p>Trang không tồn tại.</p></div>';
}
echo '</main>';

require __DIR__ . '/views/footer.php';
