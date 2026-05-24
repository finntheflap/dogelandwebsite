# DogelandSync — Build & Deploy v0.2.0

Code đã add đầy đủ cho 4 hook mới (Heartbeat, Stats, RCON, Console, Inventory History, Item Logger). Cần build JAR và deploy lên 4 MC server (lobby/sdo/towny/skyblock).

## Yêu cầu máy build

- JDK 17 hoặc 21
- Maven 3.8+

VPS Windows web KHÔNG có JDK/Maven. Build trên máy dev của bạn rồi upload JAR.

## Build

```bash
cd plugin/
mvn clean package
```

Output: `plugin/target/DogelandSync-0.2.0.jar` (~50-70KB).

Nếu báo lỗi `Cannot find symbol: AsyncChatEvent` → server target chưa phải Paper. Plugin cần Paper API (không phải Spigot/Bukkit) cho chat event.

## Deploy lên 4 MC server

Mỗi server (lobby/sdo/towny/skyblock) đang chạy trên VPS `103.232.121.90`. Cần:

1. **Copy JAR** vào `plugins/` của mỗi server:
   ```bash
   scp target/DogelandSync-0.2.0.jar root@103.232.121.90:/path/to/lobby/plugins/
   scp target/DogelandSync-0.2.0.jar root@103.232.121.90:/path/to/sdo/plugins/
   scp target/DogelandSync-0.2.0.jar root@103.232.121.90:/path/to/towny/plugins/
   scp target/DogelandSync-0.2.0.jar root@103.232.121.90:/path/to/skyblock/plugins/
   ```

2. **Xoá JAR cũ** (0.1.0) nếu có để tránh conflict.

3. **Edit `plugins/DogelandSync/config.yml`** trên mỗi server, đổi:
   ```yaml
   server-id: lobby      # → sdo / towny / skyblock cho 3 server còn lại
   database:
     host: 44.222.244.164
     user: dgsync
     password: '<password user dgsync trên web VPS MySQL>'
   ```

   (Defaults cho stats/rcon/console/inventory-history bạn cứ giữ — đã tuned.)

4. **Restart server**:
   ```
   /restart    (ingame)
   # hoặc kill + restart process
   ```

5. **Check log** trong console MC:
   ```
   [DogelandSync] Stats batch task bật (period 600 ticks).
   [DogelandSync] RCON consumer bật.
   [DogelandSync] ConsoleLogAppender registered for server-id=lobby
   [DogelandSync] Inventory history backup mỗi 30 phút.
   [DogelandSync] DogelandSync v0.2.0 enabled (server-id=lobby)
   ```

## Verify từ web

Sau khi 4 server enabled:

1. Vào `http://44.222.244.164/` → 3 card server chuyển từ Offline → Online
2. Login owner → Admin Mode → **Server Console** → terminal hiện log realtime
3. Sau 30s có player chơi → vào `?p=top&srv=lobby` (hoặc khác) → thấy top playtime/level/balance
4. Sau 30 phút → vào Admin → **Inventory History** → nhập tên player → thấy snapshot

## Tuning nếu cần

| Symptom | Fix |
|---|---|
| Server lag mỗi 30s | Tăng `stats.period-ticks: 1200` (60s) |
| RCON queue stuck "pending" | Check `server_id` trong web khớp `server-id` plugin |
| Console terminal quá nhiều dòng debug | Plugin filter sẵn Hikari, nếu plugin khác spam thì sửa `ConsoleLogAppender.append()` filter |
| RAM tăng cao | Giảm `inventory-history.period-minutes: 60` (1 lần/giờ thay vì 30 phút) |
| DB lag khi flush | Tăng `stats.period-ticks` + giảm số player track |

## Velocity (nếu dùng proxy)

Xem section 7 trong [PLUGIN_HOOKS.md](../PLUGIN_HOOKS.md):
- AuthMe `bungeecord: true` + `proxySharedSecret` trên cả 4 backend
- Cài AuthMeVelocity plugin trên proxy
- DogelandSync KHÔNG cần cài trên Velocity (proxy không có inventory)
- `paper-global.yml` proxies config cho mỗi backend

## Rollback nếu v0.2.0 lỗi

Restart server với JAR cũ `DogelandSync-0.1.0.jar` (vẫn còn trong `plugin/target/`). Mọi data trong DB tương thích.

## File mới đã add (vs v0.1.0)

```
src/main/java/vn/dogeland/sync/
├── tasks/
│   ├── StatsTask.java                  ← per-server top stats
│   ├── RconConsumerTask.java           ← exec lệnh admin từ web
│   ├── ConsoleLogAppender.java         ← Log4j2 appender → web_server_log
│   └── ConsoleChatListener.java        ← bắt chat/command events
├── safety/
│   ├── InventoryHistoryManager.java    ← snapshot gzip → web_inventory_history
│   └── ItemLogger.java                 ← queue 2000 + flush 5s → web_item_log
└── DogelandSync.java                   ← updated: register 6 services mới
```

Tổng cộng ~600 dòng Java mới, ~7KB.
