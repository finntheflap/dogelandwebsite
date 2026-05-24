<?php
/* ============================================================================
   DOGELAND NETWORK — Cấu hình chính
   ----------------------------------------------------------------------------
   Secret (db_pass, smtp_pass, discord_client_secret) NÊN đặt qua .env:
     cp .env.example .env  &&  sửa giá trị.
   ========================================================================== */

// Đọc .env nếu có (KHÔNG commit .env vào git)
$__envFile = __DIR__ . '/../.env';
if (is_file($__envFile)) {
  foreach (file($__envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (preg_match('/^\s*#/', $line)) continue;
    if (strpos($line, '=') === false) continue;
    [$k, $v] = explode('=', $line, 2);
    $k = trim($k); $v = trim($v);
    // Bỏ dấu nháy nếu có
    if (strlen($v) >= 2 && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
      $v = substr($v, 1, -1);
    }
    if (!array_key_exists($k, $_ENV)) $_ENV[$k] = $v;
    if (getenv($k) === false) putenv("$k=$v");
  }
}
function env($k, $def = null) {
  $v = getenv($k);
  if ($v === false || $v === '') return $def;
  if ($v === 'true') return true;
  if ($v === 'false') return false;
  return $v;
}

$CFG = [
  // --- Database (dùng chung với AuthMe) ---
  'db_host'   => 'localhost',
  'db_name'   => 'minecraft',
  'db_user'   => 'root',
  'db_pass'   => '',
  'authme_table' => 'authme',
  'authme_hash'  => 'SHA256',          // 'SHA256' (mặc định AuthMe) hoặc 'BCRYPT'

  // --- CHẾ ĐỘ DEV: true để test trên XAMPP (hiện link xác minh ngay trên web,
  //     không cần gửi email). KHI DEPLOY THẬT PHẢI ĐẶT = false !
  //     LƯU Ý: dù bật, dữ liệu DEMO (tài khoản admin/owner mặc định) CHỈ được
  //     gieo khi truy cập từ 127.0.0.1 / ::1 — xem dgl_is_local() bên dưới. ---
  'dev_mode'  => false,
  // --- Tự tạo bảng authme nếu chưa có (tiện test local). Trên server đã chạy
  //     AuthMe thì để true cũng an toàn (không ghi đè bảng cũ). ---
  'auto_create_authme' => true,
  // --- Tài khoản ADMIN (được đăng/sửa/xoá bài sự kiện & thông báo). Thêm tên
  //     IGN vào đây; đăng ký/đăng nhập tài khoản đó sẽ thấy menu "Quản trị". ---
  // --- CHỦ SỞ HỮU (cấp/thu quyền admin cho người khác qua trang Quản trị). ---
  'owner' => 'TheMouseRanger',
  // --- Admin cố định ban đầu (tuỳ chọn). Quyền admin chính do owner cấp. ---
  'admins' => [],
  // --- Các chế độ chơi (kho đồ hiển thị theo từng mode) ---
  'modes' => ['lobby'=>'Lobby','sdo'=>'Sword Dark Online','towny'=>'RPG Towny Survival','skyblock'=>'Skyblock'],
  // --- Render skin Minecraft (mc-heads.net dùng được theo tên, miễn phí) ---
  'skin_api' => 'https://mc-heads.net',
  // --- Discord OAuth2 (điền để bật "Xác thực Discord"). Tạo app tại
  //     https://discord.com/developers → OAuth2 → thêm redirect = site_url/?p=discord_cb ---
  'discord_client_id' => '',
  'discord_client_secret' => '',

  // --- Tên miền (dùng để tạo link xác minh email) ---
  'site_url'  => 'https://dogeland.vn',
  'server_ip' => 'play.dogeland.vn',

  // --- Gửi email (SMTP). Gmail: bật "App Password". ---
  'mail_method' => 'smtp',             // 'smtp' hoặc 'mail'
  'smtp_host'   => 'smtp.gmail.com',
  'smtp_port'   => 587,                // 587 = tls, 465 = ssl
  'smtp_secure' => 'tls',              // 'tls' hoặc 'ssl'
  'smtp_user'   => 'youremail@gmail.com',
  'smtp_pass'   => 'your-app-password',
  'mail_from'   => 'no-reply@dogeland.vn',
  'mail_from_name' => 'Dogeland Network',

  // --- Mạng xã hội ---
  'socials' => [
    'discord'  => 'https://discord.gg/your-invite',
    'facebook' => 'https://facebook.com/your-page',
    'youtube'  => 'https://youtube.com/@your-channel',
    'tiktok'   => 'https://tiktok.com/@your-account',
  ],

  // --- Danh sách server cho ticket (player chọn server đang gặp vấn đề) ---
  'ticket_servers' => ['Survival','Skyblock','Bedwars','Lobby/Hub','Creative','Khác / Không rõ'],

  // --- Danh mục yêu cầu hỗ trợ ---
  'ticket_categories' => ['Báo lỗi server','Nạp thẻ / Thanh toán','Vật phẩm in-game','Báo cáo người chơi','Mất đồ','Tố cáo','Tài khoản','Góp ý','Khác'],

  // --- Upload file đính kèm ticket ---
  'upload_dir'       => __DIR__.'/../uploads/tickets', // ở web root (tránh /config/ bị Apache chặn)
  'upload_url'       => 'uploads/tickets',             // URL prefix (tương đối với index.php)
  'upload_max_mb'    => 25,                          // dung lượng tối đa / file (MB)
  'upload_max_files' => 8,                           // số file tối đa / tin nhắn
  'upload_image_ext' => ['jpg','jpeg','png','gif','webp','avif','bmp'],
  'upload_video_ext' => ['mp4','webm','mov','m4v','ogg'],

  // --- Tiền tố số ticket (DGL-000123) ---
  'ticket_prefix' => 'DGL',
  // --- Webhook Discord cho Thông báo khẩn (Server Settings → Integrations → Webhooks) ---
  'discord_webhook' => '',
  // --- Nạp tự chọn (custom) cho Momo/QR: giới hạn số tiền (VND) + tỉ giá ---
  'custom_min' => 1000,
  'custom_max' => 10000000,
  'vnd_per_doge' => 1000,   // 1.000đ = 1 Dogecoin  → nạp 10.000đ = 10 Dogecoin

  /* ====================== DOGECOIN (PlayerPoints) ======================
     Toàn bộ tiền tệ trên web GỘP thành 1 loại: DOGECOIN.
     - pp_enabled=true  : đọc/ghi số dư trực tiếp từ plugin PlayerPoints
                          (bảng MySQL dùng chung). Đây là NGUỒN CHÍNH.
     - pp_enabled=false : dùng cột mirror web_wallet.dogecoin (tiện test local).
     PlayerPoints v3 mặc định: bảng `playerpoints` (uuid VARCHAR(36), points INT).
     uuid_mode:
       'offline'      = UUID offline (md5 "OfflinePlayer:<tên>") — server offline/cracked.
       'online'       = UUID Mojang (server online-mode), tự tra & cache.
       'username'     = lưu theo tên (một số bản PP cũ).
     ===================================================================== */
  'doge_label'   => 'Dogecoin',
  'doge_symbol'  => 'Ð',
  'pp_enabled'   => false,
  'pp_table'     => 'playerpoints',
  'pp_uuid_col'  => 'uuid',
  'pp_points_col'=> 'points',
  'uuid_mode'    => 'offline',

  // --- API icon vật phẩm (đấu giá & chợ trời). %s = mã item (vd: diamond_sword) ---
  'item_icon_api' => 'https://cdn.jsdelivr.net/gh/InventivetalentDev/minecraft-assets@1.20.1/assets/minecraft/textures/item/%s.png',

  // --- Phí % sàn giao dịch Chợ Trời (server thu) ---
  'market_fee_percent' => 5,
  // --- Phí mở phiên Đấu Giá (Dogecoin, hoàn lại nếu không có ai đấu) ---
  'auction_open_fee' => 2,
];
// Tương thích ngược: code cũ còn đọc 'vnd_per_diamond'
$CFG['vnd_per_diamond'] = $CFG['vnd_per_doge'];

/* ===== Dữ liệu hiển thị (sửa thoải mái) ===== */
/* ===== Gói nạp mặc định — quy đổi 1.000đ = 1 Dogecoin (kèm % thưởng) ===== */
$PACKAGES = [
  ['amount'=>20000,  'dia'=>20,  'xu'=>0, 'bonus'=>''],
  ['amount'=>50000,  'dia'=>55,  'xu'=>0, 'bonus'=>'+10% thưởng'],
  ['amount'=>100000, 'dia'=>120, 'xu'=>0, 'bonus'=>'+20% thưởng', 'hot'=>true],
  ['amount'=>200000, 'dia'=>260, 'xu'=>0, 'bonus'=>'+30% thưởng'],
  ['amount'=>500000, 'dia'=>700, 'xu'=>0, 'bonus'=>'+40% thưởng'],
];

// Cho phép override các khoá nhạy cảm từ .env
$CFG['db_host']               = env('DB_HOST',               $CFG['db_host']);
$CFG['db_name']               = env('DB_NAME',               $CFG['db_name']);
$CFG['db_user']               = env('DB_USER',               $CFG['db_user']);
$CFG['db_pass']               = env('DB_PASS',               $CFG['db_pass']);
$CFG['smtp_host']             = env('SMTP_HOST',             $CFG['smtp_host']);
$CFG['smtp_user']             = env('SMTP_USER',             $CFG['smtp_user']);
$CFG['smtp_pass']             = env('SMTP_PASS',             $CFG['smtp_pass']);
$CFG['discord_client_id']     = env('DISCORD_CLIENT_ID',     $CFG['discord_client_id']);
$CFG['discord_client_secret'] = env('DISCORD_CLIENT_SECRET', $CFG['discord_client_secret']);
$CFG['discord_webhook']       = env('DISCORD_WEBHOOK',       $CFG['discord_webhook']);
$CFG['site_url']              = env('SITE_URL',              $CFG['site_url']);
$CFG['server_ip']             = env('SERVER_IP',             $CFG['server_ip']);
if (env('DEV_MODE') !== null)    $CFG['dev_mode'] = (bool) env('DEV_MODE');
if (env('OWNER')    !== null)    $CFG['owner']    = env('OWNER');
