# Dogeland Network — Website

Trang web cộng đồng cho server Minecraft Dogeland Network: nạp thẻ, hồ sơ, đấu giá, chợ trời, ticket hỗ trợ, quản trị.

## Yêu cầu

- PHP ≥ 7.4 (đã test trên 8.5)
- MySQL / MariaDB
- Plugin AuthMe trong Minecraft (chia sẻ cùng DB)
- (Tuỳ chọn) Plugin PlayerPoints cho Dogecoin

## Cài đặt

```bash
cp .env.example .env       # rồi sửa giá trị
# upload toàn bộ thư mục lên hosting, trỏ tên miền vào index.php
```

Các bảng phụ (`web_*`) sẽ tự tạo khi mở web lần đầu.

## Cấu trúc thư mục

```
.
├── index.php                 # Front controller (router mỏng ~50 dòng)
├── .env.example              # Mẫu cấu hình bí mật (DB, SMTP, Discord)
├── config/
│   └── config.php            # $CFG + $PACKAGES (đọc override từ .env)
├── includes/                 # Helper module — load qua bootstrap.php
│   ├── bootstrap.php         # Session + load tất cả include + state user
│   ├── assets.php            # Serve ảnh qua ?img=<tên>
│   ├── helpers.php           # h(), csrf, flash, redirect, ms, is_admin, rcon
│   ├── db.php                # PDO connection + CREATE TABLE schema
│   ├── auth.php              # AuthMe hash + verify (SHA256/BCRYPT)
│   ├── mail.php              # SMTP gửi mail (raw socket)
│   ├── uuid.php              # offline/online UUID + PlayerPoints key
│   ├── wallet.php            # web_wallet + Dogecoin (doge_*)
│   ├── items.php             # ikey_norm, item_icon, item_upload
│   ├── posts.php             # get_posts, post_card, post_type_label
│   ├── tickets.php           # tk_upload, tk_render_atts, tk_admin_list
│   ├── auction.php           # auc_settle_due (lazy)
│   ├── notify.php            # notify, notify_admins
│   ├── settings.php          # dgl_setting, dgl_promo, dgl_apply_pricing
│   ├── chat.php              # chat_sys, chat_send, stale ticket alert
│   ├── api_utils.php         # ajax_csrf_ok, json_out, mentions
│   └── icons.php             # ic() — SVG social icons
├── actions/
│   └── handle_post.php       # POST handlers (act=register/login/topup/...)
├── api/
│   ├── api.php               # ?p=api (JSON AJAX)
│   ├── sse.php               # ?p=sse (Server-Sent Events)
│   └── auth_callbacks.php    # ?p=verify / logout / updates / discord_cb
├── views/
│   ├── header.php            # DOCTYPE + <head> + nav + <header>
│   └── footer.php            # <footer> + inline scripts cần biến PHP
├── pages/                    # Một file / một $p
│   ├── home.php  events.php  guide.php   rules.php   info.php
│   ├── post.php  admin.php   top.php     profile.php tickets.php
│   ├── ticket.php topup.php  shop.php    auction.php ranks.php
│   ├── market.php login.php  register.php verify.php
└── assets/
    ├── css/main.css          # CSS gốc (trước đây inline trong <style>)
    ├── js/                   # JS thuần (TODO — JS hiện vẫn inline ở footer)
    └── img/                  # banner.jpg, bg.jpg, doge.png, logo.png, world.jpg, dogecoin.png
                              # (trước đây nhúng base64 trong $ASSETS)
```

## Lifecycle 1 request

```
index.php
  └─> includes/assets.php          # nếu ?img=, serve ảnh rồi exit
  └─> includes/bootstrap.php       # config, session, helpers, state user
  └─> if POST → actions/handle_post.php
  └─> if ?p=api / ?p=sse → api/api.php hoặc api/sse.php (exit)
  └─> require api/auth_callbacks.php   # xử lý verify/logout/discord_cb
  └─> NEEDS_LOGIN gate
  └─> views/header.php             # DOCTYPE, CSS link, navbar
  └─> pages/$p.php                 # nội dung trang
  └─> views/footer.php             # footer + inline JS
```

## Ghi chú refactor

- Bản gốc 1 file 4175 dòng (1.3MB) đã được tách ra ~45 file con — logic không đổi.
- Ảnh (banner/bg/doge/logo/world/dogecoin) trước đây nhúng base64 trong `$ASSETS` đã được extract thành file thật trong `assets/img/`. Endpoint `?img=<tên>` vẫn hoạt động (đọc file từ disk thay vì decode base64).
- Secret (DB password, SMTP password, Discord secret) chuyển sang `.env` (gitignored).
