# DogelandSync

Plugin Paper 1.20+ đồng bộ inventory player Minecraft → bảng `web_inventory` của website Dogeland.

**Phase 1 + 2** (DONE): read-only sync — web hiển thị kho thật của player.
**Phase 3** (DONE): action queue 2 chiều — web rao bán/mua/gỡ tin → plugin remove/give item thật.
**Phase 4** (TODO): UI polish — `pages/profile.php` render 4 tab inventory (main/armor/offhand/ender) với enchant + durability bar; `pages/market.php` hiển thị display_name có màu, lore preview.

## Build

```bash
cd plugin
mvn clean package
```

Output: `target/DogelandSync-0.1.0.jar`

## Install

1. Copy `DogelandSync-0.1.0.jar` vào `<minecraft-server>/plugins/`
2. Start server 1 lần để plugin tạo thư mục + `config.yml` mặc định
3. Stop server, sửa `<minecraft-server>/plugins/DogelandSync/config.yml`:
   - `server-id`: phải khớp với 1 key trong `$CFG['modes']` của web (`config/config.php`). VD: `towny`, `sdo`, `survival`.
   - `database.host/port/name/user/password`: trùng MySQL mà web đang dùng (mặc định `localhost:3306/minecraft`, user `root`, pass rỗng).
4. Start server lại.

## Schema

Plugin **không tự tạo schema** — chỉ ghi. Schema sẽ được web `includes/db.php` auto-migrate vào lần page load đầu tiên sau update (xem comment `DOGELANDSYNC PLUGIN` trong file đó).

Các cột mới trên `web_inventory`:
| Cột | Ý nghĩa |
|---|---|
| `uuid` | UUID player (Mojang hoặc offline) |
| `section` | `main` / `armor` / `offhand` / `ender` |
| `slot` | Vị trí trong section (0-based) |
| `material` | `DIAMOND_SWORD` (Bukkit Material enum) |
| `display_name` | Tên item có format `§` |
| `lore` | Lore nhiều dòng, ngăn bằng `\n` |
| `enchants` | `sharpness:5,unbreaking:3` |
| `damage` / `max_damage` | Durability đã mất / tổng |
| `nbt_b64` | `ItemStack.serializeAsBytes()` → base64. **Source of truth** |
| `locked` | =1 khi item đang rao bán/đấu giá → snapshot KHÔNG đè |
| `updated` | epoch ms lần ghi cuối |

Bảng mới:
- `web_inv_actions` — queue lệnh từ web (Phase 3)
- `web_sync_heartbeat` — plugin báo "đang sống" mỗi 15s

## Action queue (Phase 3)

Web KHÔNG sửa `web_inventory`/`web_market`/`web_auctions` trực tiếp cho item từ Minecraft. Thay vào đó nó ghi 1 row vào `web_inv_actions` rồi `inv_action_wait()` poll trạng thái.

Action types plugin hỗ trợ:

| Action | Khi nào | Payload |
|---|---|---|
| `list_market` | User đăng bán trên Chợ Trời | `{inv_id, qty, price, description?, item_key?}` |
| `list_auction` | User mở phiên đấu giá | `{inv_id, qty, start_price, duration_ms, listing_fee?, item_key?}` |
| `withdraw` (alias `give`) | market_buy/market_cancel/auction_win/auction_expired | `{nbt_b64, qty, item_name?, item_key?, source?}` |

**Status flow**: `pending` → `processing-<token>` (claimed) → `done` (kết quả ở `result` JSON) | `failed` (lý do ở `result` string).

**Offline give**: nếu player offline khi nhận item, plugin insert row `web_inventory` với `locked=2` (pending give). Khi player join, `SyncService.applyPendingGives()` give item vào inv trước khi snapshot, rồi xoá row đó.

## Sync semantics

| Event | Hành động |
|---|---|
| `PlayerJoinEvent` | Snapshot sau 1 tick (chờ Bukkit load inv từ player.dat) |
| `PlayerQuitEvent` | Snapshot ngay (force, bỏ qua debounce) |
| `InventoryCloseEvent` | Snapshot sau 1 tick, có debounce |
| Periodic (config) | Mỗi `periodic-seconds` cho mọi player online |
| `/dgsync [player]` | Manual sync, bỏ qua debounce |

Mỗi snapshot là 1 transaction:
1. `DELETE FROM web_inventory WHERE username=? AND mode=? AND locked=0`
2. Batch `INSERT` từng slot non-empty của 4 section.

Row `locked=1` được giữ nguyên — đó là vật phẩm đang ở trong `web_market` / `web_auctions`.

## Tại sao dùng `libraries:` thay vì shade

Paper 1.17+ hỗ trợ field `libraries` trong `plugin.yml`. HikariCP và MySQL driver được auto-download từ Maven Central vào `<server>/libraries/` lúc startup. Lợi:
- Jar plugin nhỏ (~10KB thay vì ~5MB)
- Không xung đột với plugin khác đã shade cùng dependency
- Update version dễ (sửa `plugin.yml`)

## Troubleshooting

**Plugin start báo "Không kết nối được MySQL"**
- Kiểm tra MySQL chạy (XAMPP control panel)
- Test credentials: `mysql -u root -p`
- Firewall trên Windows nếu MC server không cùng máy với MySQL

**Sync chạy nhưng web vẫn rỗng**
- Kiểm tra cột `mode` trong `web_inventory` — phải khớp với `server-id` trong `config.yml`
- Web `pages/profile.php` đang filter theo `mode` của user — nếu sai key, không thấy

**Player có item nhưng web báo 0**
- Check log có dòng `Synced N slot cho <player>` không (bật `verbose: true` trong config)
- Item enchant bị strip? → kiểm tra cột `nbt_b64` có data không

**Plugin chạy ở MC server khác máy với web**
- Web/MySQL ở VPS A (vd: 44.222.244.164), MC server ở VPS B (vd: 103.232.121.90)
- Trên VPS A: mở MySQL accept remote (`bind-address = 0.0.0.0` trong `my.ini`)
- Cấp user MySQL có quyền connect từ VPS B:
  ```sql
  CREATE USER 'dgsync'@'<VPS_B_ip>' IDENTIFIED BY '<strong-pass>';
  GRANT SELECT, INSERT, UPDATE, DELETE ON minecraft.* TO 'dgsync'@'<VPS_B_ip>';
  FLUSH PRIVILEGES;
  ```
- Mở firewall TCP 3306 inbound từ VPS B (Windows Firewall + AWS Security Group nếu có)
- Sửa `plugin/config.yml`: `database.host = <VPS_A_ip>`, user/pass mới

**User báo "Đang xử lý — kiểm tra lại trong vài giây"**
- Đó là khi plugin chưa xử lý xong trong timeout 6s. Có thể:
  - Plugin đang lag (server đông, GC) — bình thường, action vẫn sẽ done
  - Plugin offline — check `web_sync_heartbeat.last_beat` < 30s gần đây không
  - Reload trang là thấy item đã vào market
