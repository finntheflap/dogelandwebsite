<?php
/* ============================================================================
   BOOTSTRAP — session, timezone, load all helpers
   ========================================================================== */

session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Load config (sets $CFG, $PACKAGES)
require __DIR__ . '/../config/config.php';

// Load helpers theo thứ tự dependency
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/uuid.php';
require __DIR__ . '/wallet.php';
require __DIR__ . '/items.php';
require __DIR__ . '/posts.php';
require __DIR__ . '/tickets.php';
require __DIR__ . '/auction.php';
require __DIR__ . '/notify.php';
require __DIR__ . '/settings.php';
require __DIR__ . '/chat.php';
require __DIR__ . '/api_utils.php';
require __DIR__ . '/icons.php';

// Khởi tạo state user + IS_ADMIN
$user = $_SESSION['user'] ?? null;
$p    = $_GET['p'] ?? 'home';
$IS_ADMIN = is_admin($user);
dgl_apply_pricing();
if ($user) {
  $myw = wallet($user);
  if (!empty($myw['banned']) && !is_owner($user)) {
    $_SESSION['user'] = null; unset($_SESSION['user']);
    $user = null; $IS_ADMIN = false;
    flash(['error', 'Tài khoản của bạn đã bị khoá (ban).']);
  }
}
