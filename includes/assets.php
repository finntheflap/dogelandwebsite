<?php
/* ============================================================================
   ASSETS — phục vụ ảnh qua ?img=ten (cache trình duyệt)
   File ảnh thật nằm tại assets/img/<tên>.<ext>
   ========================================================================== */

if (isset($_GET['img'])) {
  $k = preg_replace('/[^a-z0-9_]/', '', strtolower((string)$_GET['img']));
  $map = [
    'banner'   => ['image/jpeg', 'assets/img/banner.jpg'],
    'bg'       => ['image/jpeg', 'assets/img/bg.jpg'],
    'doge'     => ['image/png',  'assets/img/doge.png'],
    'logo'     => ['image/png',  'assets/img/logo.png'],
    'world'    => ['image/jpeg', 'assets/img/world.jpg'],
    'dogecoin' => ['image/png',  'assets/img/dogecoin.png'],
  ];
  if (isset($map[$k]) && is_file(__DIR__ . '/../' . $map[$k][1])) {
    header('Content-Type: ' . $map[$k][0]);
    header('Cache-Control: public, max-age=604800, immutable');
    readfile(__DIR__ . '/../' . $map[$k][1]);
  } else {
    http_response_code(404);
  }
  exit;
}
