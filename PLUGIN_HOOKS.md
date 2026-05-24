# DogelandSync — 3 Hooks mới cần thêm vào plugin

Tài liệu này hướng dẫn paste code Java vào plugin `DogelandSync` (Paper 1.20+) để hook 3 feature web mới sẵn UI:

1. **Heartbeat** — gửi số người online → home page hiển thị
2. **Player Stats** — batch metrics → top page per-server tabs
3. **RCON Consumer** — đọc queue lệnh từ admin web → exec console

---

## 0. Prerequisites

Plugin đã có:
- `Database.java` với HikariCP pool kết nối MySQL `web_*`
- `config.yml` đã có `server-id` (vd: `sdo`, `towny`, `skyblock`)
- Đã chạy schema migration mới từ web (3 ALTER + 1 CREATE TABLE) — chỉ cần mở web 1 lần là tự migrate

**Trong `DogelandSync.java#onEnable()`** schedule 3 task + register Log4j2 Appender:

```java
new HeartbeatTask(this).runTaskTimerAsynchronously(this, 100L, 100L);    // 5s
new StatsTask(this).runTaskTimerAsynchronously(this, 200L, 600L);        // 30s
new RconConsumerTask(this).runTaskTimer(this, 100L, 20L);                // 1s (SYNC — exec command phải sync)
ConsoleLogAppender.register(this);                                       // bắt mọi log → DB
```

**Trong `onDisable()`**: `ConsoleLogAppender.unregister();`

---

## 1. HeartbeatTask — UPSERT `web_sync_heartbeat`

**File: `src/main/java/vn/dogeland/sync/tasks/HeartbeatTask.java`**

```java
package vn.dogeland.sync.tasks;

import org.bukkit.Bukkit;
import org.bukkit.entity.Player;
import org.bukkit.scheduler.BukkitRunnable;
import vn.dogeland.sync.DogelandSync;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.util.StringJoiner;

public class HeartbeatTask extends BukkitRunnable {
    private final DogelandSync plugin;
    public HeartbeatTask(DogelandSync plugin) { this.plugin = plugin; }

    @Override
    public void run() {
        // Build JSON array of online player names (web parse JSON từ cột TEXT)
        StringJoiner sj = new StringJoiner(",", "[", "]");
        for (Player p : Bukkit.getOnlinePlayers()) {
            sj.add("\"" + p.getName().replace("\"", "\\\"") + "\"");
        }
        String playersJson = sj.toString();
        String serverId = plugin.getConfig().getString("server-id", "unknown");
        String version  = plugin.getDescription().getVersion();
        long now = System.currentTimeMillis();

        try (Connection c = plugin.getDatabase().getConnection();
             PreparedStatement ps = c.prepareStatement(
                "INSERT INTO web_sync_heartbeat(server_id, online_players, plugin_version, last_beat) " +
                "VALUES(?, ?, ?, ?) " +
                "ON DUPLICATE KEY UPDATE online_players=VALUES(online_players), " +
                "plugin_version=VALUES(plugin_version), last_beat=VALUES(last_beat)")) {
            ps.setString(1, serverId);
            ps.setString(2, playersJson);
            ps.setString(3, version);
            ps.setLong(4, now);
            ps.executeUpdate();
        } catch (Exception e) {
            plugin.getLogger().warning("Heartbeat failed: " + e.getMessage());
        }
    }
}
```

**Web side:** đọc `web_sync_heartbeat` qua `get_servers_status()` trong [includes/servers.php](includes/servers.php). Server online = `last_beat` trong 30s qua. Home page sẽ tự refresh khi user reload.

---

## 2. StatsTask — batch UPSERT `web_player_stats`

**Lưu ý:** metric `balance` cần Vault hoặc Essentials Economy. Nếu chưa có thì set 0 hoặc skip cột đó.

**File: `src/main/java/vn/dogeland/sync/tasks/StatsTask.java`**

```java
package vn.dogeland.sync.tasks;

import net.milkbowl.vault.economy.Economy; // require Vault dependency, optional
import org.bukkit.Bukkit;
import org.bukkit.Statistic;
import org.bukkit.entity.EntityType;
import org.bukkit.entity.Player;
import org.bukkit.plugin.RegisteredServiceProvider;
import org.bukkit.scheduler.BukkitRunnable;
import vn.dogeland.sync.DogelandSync;

import java.sql.Connection;
import java.sql.PreparedStatement;

public class StatsTask extends BukkitRunnable {
    private final DogelandSync plugin;
    private Economy econ; // null nếu không có Vault

    public StatsTask(DogelandSync plugin) {
        this.plugin = plugin;
        RegisteredServiceProvider<Economy> rsp = Bukkit.getServicesManager().getRegistration(Economy.class);
        if (rsp != null) this.econ = rsp.getProvider();
    }

    @Override
    public void run() {
        String serverId = plugin.getConfig().getString("server-id", "unknown");
        long now = System.currentTimeMillis();

        try (Connection c = plugin.getDatabase().getConnection();
             PreparedStatement ps = c.prepareStatement(
                "INSERT INTO web_player_stats" +
                "(username, server_id, playtime_sec, level, xp, balance, mob_kills, player_kills, deaths, last_seen, updated) " +
                "VALUES(?,?,?,?,?,?,?,?,?,?,?) " +
                "ON DUPLICATE KEY UPDATE " +
                "playtime_sec=VALUES(playtime_sec), level=VALUES(level), xp=VALUES(xp), " +
                "balance=VALUES(balance), mob_kills=VALUES(mob_kills), " +
                "player_kills=VALUES(player_kills), deaths=VALUES(deaths), " +
                "last_seen=VALUES(last_seen), updated=VALUES(updated)")) {

            for (Player p : Bukkit.getOnlinePlayers()) {
                // Statistic.PLAY_ONE_MINUTE đếm theo ticks (20 tick = 1s)
                long playtimeSec = p.getStatistic(Statistic.PLAY_ONE_MINUTE) / 20L;
                int level = p.getLevel();
                long totalXp = p.getTotalExperience();
                long bal = (econ != null) ? (long) econ.getBalance(p) : 0L;
                int mobKills = p.getStatistic(Statistic.MOB_KILLS);
                int playerKills = p.getStatistic(Statistic.PLAYER_KILLS);
                int deaths = p.getStatistic(Statistic.DEATHS);

                ps.setString(1, p.getName());
                ps.setString(2, serverId);
                ps.setLong(3, playtimeSec);
                ps.setInt(4, level);
                ps.setLong(5, totalXp);
                ps.setLong(6, bal);
                ps.setInt(7, mobKills);
                ps.setInt(8, playerKills);
                ps.setInt(9, deaths);
                ps.setLong(10, now);
                ps.setLong(11, now);
                ps.addBatch();
            }
            ps.executeBatch();
        } catch (Exception e) {
            plugin.getLogger().warning("Stats batch failed: " + e.getMessage());
        }
    }
}
```

**Web side:** [pages/top.php](pages/top.php) đọc `web_player_stats` qua `get_server_top($serverId, $metric)`. Tab per-server hiển thị top 50 theo metric chọn.

---

## 3. RconConsumerTask — exec lệnh từ web admin

**QUAN TRỌNG:** task này phải **SYNC** (`runTaskTimer` không phải `runTaskTimerAsynchronously`) vì `Bukkit.dispatchCommand` chỉ chạy được trên main thread.

**File: `src/main/java/vn/dogeland/sync/tasks/RconConsumerTask.java`**

```java
package vn.dogeland.sync.tasks;

import org.bukkit.Bukkit;
import org.bukkit.command.CommandSender;
import org.bukkit.scheduler.BukkitRunnable;
import vn.dogeland.sync.DogelandSync;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.serializer.plain.PlainTextComponentSerializer;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.util.ArrayList;
import java.util.List;

public class RconConsumerTask extends BukkitRunnable {
    private final DogelandSync plugin;
    public RconConsumerTask(DogelandSync plugin) { this.plugin = plugin; }

    @Override
    public void run() {
        String serverId = plugin.getConfig().getString("server-id", "unknown");
        long now = System.currentTimeMillis();
        List<long[]> claimed = new ArrayList<>(); // [id, ...]

        // 1) ATOMIC CLAIM: chuyển 'pending' → 'processing' với owner = serverId
        try (Connection c = plugin.getDatabase().getConnection()) {
            // Mark mỗi row một cái — tránh race condition với plugin khác
            try (PreparedStatement claim = c.prepareStatement(
                    "UPDATE web_rcon_queue SET status='processing' " +
                    "WHERE id=? AND status='pending' AND server_id=?")) {
                try (PreparedStatement find = c.prepareStatement(
                        "SELECT id FROM web_rcon_queue WHERE status='pending' AND server_id=? ORDER BY id ASC LIMIT 10")) {
                    find.setString(1, serverId);
                    try (ResultSet rs = find.executeQuery()) {
                        while (rs.next()) {
                            long id = rs.getLong(1);
                            claim.setLong(1, id);
                            claim.setString(2, serverId);
                            if (claim.executeUpdate() == 1) claimed.add(new long[]{id});
                        }
                    }
                }
            }

            // 2) EXEC từng lệnh (must be on main thread — task này đã là sync nên OK)
            for (long[] arr : claimed) {
                long id = arr[0];
                String cmd = null;
                try (PreparedStatement get = c.prepareStatement("SELECT command FROM web_rcon_queue WHERE id=?")) {
                    get.setLong(1, id);
                    try (ResultSet rs = get.executeQuery()) { if (rs.next()) cmd = rs.getString(1); }
                }
                if (cmd == null) continue;

                StringBuilder output = new StringBuilder();
                CaptureSender sender = new CaptureSender(Bukkit.getConsoleSender(), output);
                boolean ok = false;
                try {
                    ok = Bukkit.dispatchCommand(sender, cmd);
                } catch (Exception e) {
                    output.append("EXCEPTION: ").append(e.getMessage());
                }

                String result = output.length() > 0 ? output.toString() : (ok ? "(OK, no output)" : "(failed)");
                if (result.length() > 4000) result = result.substring(0, 4000) + "...(truncated)";

                try (PreparedStatement upd = c.prepareStatement(
                        "UPDATE web_rcon_queue SET status=?, output=?, executed=? WHERE id=?")) {
                    upd.setString(1, ok ? "done" : "failed");
                    upd.setString(2, result);
                    upd.setLong(3, System.currentTimeMillis());
                    upd.setLong(4, id);
                    upd.executeUpdate();
                }
            }
        } catch (Exception e) {
            plugin.getLogger().warning("RCON consumer failed: " + e.getMessage());
        }
    }

    /** Wrapper around ConsoleCommandSender để bắt output (sendMessage) — viết vào StringBuilder. */
    private static class CaptureSender implements org.bukkit.command.CommandSender {
        private final org.bukkit.command.CommandSender delegate;
        private final StringBuilder out;
        CaptureSender(org.bukkit.command.CommandSender d, StringBuilder s) { delegate = d; out = s; }
        @Override public void sendMessage(String msg) { out.append(msg).append("\n"); delegate.sendMessage(msg); }
        @Override public void sendMessage(String... msgs) { for (String m : msgs) sendMessage(m); }
        @Override public void sendMessage(net.md_5.bungee.api.chat.BaseComponent... cs) { for (var c : cs) sendMessage(c.toPlainText()); }
        @Override public void sendMessage(net.md_5.bungee.api.chat.BaseComponent c) { sendMessage(c.toPlainText()); }
        @Override public void sendMessage(java.util.UUID id, String m) { sendMessage(m); }
        @Override public void sendMessage(java.util.UUID id, String... msgs) { sendMessage(msgs); }
        // Adventure API (Paper)
        public void sendMessage(net.kyori.adventure.text.Component c) {
            String plain = PlainTextComponentSerializer.plainText().serialize(c);
            out.append(plain).append("\n");
            delegate.sendMessage(c);
        }
        // Delegate hết các method khác về console
        @Override public org.bukkit.Server getServer() { return delegate.getServer(); }
        @Override public String getName() { return delegate.getName(); }
        @Override public boolean isOp() { return true; }
        @Override public void setOp(boolean v) {}
        @Override public boolean isPermissionSet(String n) { return true; }
        @Override public boolean isPermissionSet(org.bukkit.permissions.Permission p) { return true; }
        @Override public boolean hasPermission(String n) { return true; }
        @Override public boolean hasPermission(org.bukkit.permissions.Permission p) { return true; }
        @Override public org.bukkit.permissions.PermissionAttachment addAttachment(org.bukkit.plugin.Plugin pl, String n, boolean v) { return delegate.addAttachment(pl, n, v); }
        @Override public org.bukkit.permissions.PermissionAttachment addAttachment(org.bukkit.plugin.Plugin pl) { return delegate.addAttachment(pl); }
        @Override public org.bukkit.permissions.PermissionAttachment addAttachment(org.bukkit.plugin.Plugin pl, String n, boolean v, int t) { return delegate.addAttachment(pl, n, v, t); }
        @Override public org.bukkit.permissions.PermissionAttachment addAttachment(org.bukkit.plugin.Plugin pl, int t) { return delegate.addAttachment(pl, t); }
        @Override public void removeAttachment(org.bukkit.permissions.PermissionAttachment a) { delegate.removeAttachment(a); }
        @Override public void recalculatePermissions() {}
        @Override public java.util.Set<org.bukkit.permissions.PermissionAttachmentInfo> getEffectivePermissions() { return delegate.getEffectivePermissions(); }
        @Override public org.bukkit.command.CommandSender.Spigot spigot() { return delegate.spigot(); }
        @Override public net.kyori.adventure.text.Component name() { return delegate.name(); }
    }
}
```

**Web side:** Admin Mode → tab "Server Console" (chỉ Owner + Console grantees thấy):
- Form chọn server + gõ lệnh → INSERT vào `web_rcon_queue` với `server_id` + `status='pending'`
- Plugin task pick up, exec, write back `output` + `status='done'/'failed'`
- Bảng "Lệnh gần đây (50)" auto refresh khi reload trang (TODO: real-time SSE)

---

## 4. ConsoleLogAppender — stream MỌI log line về web realtime

**Mục đích:** bắt mọi log line trên console (vanilla server, mọi plugin, chat events, command output) → push vào `web_server_log` → web SSE stream tới admin tab "Server Console" hiển thị live terminal.

**Cơ chế:** Paper dùng Log4j2 → register custom Appender vào root logger context. Mỗi LogEvent gọi → INSERT bulk vào DB. Append async với batch buffer 100 lines / 1s flush để không block server tick.

**File: `src/main/java/vn/dogeland/sync/tasks/ConsoleLogAppender.java`**

```java
package vn.dogeland.sync.tasks;

import org.apache.logging.log4j.Level;
import org.apache.logging.log4j.LogManager;
import org.apache.logging.log4j.core.*;
import org.apache.logging.log4j.core.appender.AbstractAppender;
import org.apache.logging.log4j.core.config.Property;
import org.apache.logging.log4j.core.layout.PatternLayout;
import org.bukkit.Bukkit;
import org.bukkit.scheduler.BukkitTask;
import vn.dogeland.sync.DogelandSync;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.util.ArrayDeque;
import java.util.Deque;

public class ConsoleLogAppender extends AbstractAppender {
    private static ConsoleLogAppender instance;
    private static BukkitTask flushTask;
    private final DogelandSync plugin;
    private final Deque<LogRow> buffer = new ArrayDeque<>();
    private final Object lock = new Object();
    private static final int MAX_BUFFER = 1000;

    private record LogRow(long ts, String level, String source, String message) {}

    private ConsoleLogAppender(DogelandSync plugin) {
        super("DogelandSyncConsole", null,
              PatternLayout.createDefaultLayout(), false, Property.EMPTY_ARRAY);
        this.plugin = plugin;
    }

    public static void register(DogelandSync plugin) {
        if (instance != null) return;
        instance = new ConsoleLogAppender(plugin);
        instance.start();
        // Hook vào root logger context của Paper (Log4j2)
        LoggerContext ctx = (LoggerContext) LogManager.getContext(false);
        org.apache.logging.log4j.core.config.Configuration cfg = ctx.getConfiguration();
        cfg.addAppender(instance);
        cfg.getRootLogger().addAppender(instance, Level.INFO, null);
        ctx.updateLoggers();
        // Flush mỗi 1s (async — không block main thread)
        flushTask = Bukkit.getScheduler().runTaskTimerAsynchronously(plugin, instance::flush, 20L, 20L);
        plugin.getLogger().info("ConsoleLogAppender registered");
    }

    public static void unregister() {
        if (instance == null) return;
        if (flushTask != null) flushTask.cancel();
        LoggerContext ctx = (LoggerContext) LogManager.getContext(false);
        ctx.getConfiguration().getRootLogger().removeAppender("DogelandSyncConsole");
        ctx.updateLoggers();
        instance.flush();
        instance.stop();
        instance = null;
    }

    @Override
    public void append(LogEvent event) {
        // Lọc: bỏ log nội bộ của Hikari + chính plugin DogelandSync (tránh recursive)
        String loggerName = event.getLoggerName() == null ? "" : event.getLoggerName();
        if (loggerName.startsWith("com.zaxxer.hikari")) return;
        if (loggerName.contains("DogelandSync")) {
            String msg = event.getMessage().getFormattedMessage();
            if (msg.contains("ConsoleLog") || msg.contains("Heartbeat") || msg.contains("Stats batch")) return;
        }

        String level = event.getLevel().toString(); // INFO, WARN, ERROR, ...
        String source = simplifyLogger(loggerName);
        String message = event.getMessage().getFormattedMessage();
        // Strip ANSI color codes nếu có
        message = message.replaceAll("\\[[;\\d]*m", "");
        if (message.length() > 2000) message = message.substring(0, 2000) + "...(truncated)";

        synchronized (lock) {
            if (buffer.size() >= MAX_BUFFER) buffer.pollFirst(); // drop oldest nếu burst quá nhanh
            buffer.offerLast(new LogRow(System.currentTimeMillis(), level, source, message));
        }
    }

    /** Bắt thêm chat + commands (Bukkit events, không phải log) — gọi từ Listener */
    public static void appendChat(String player, String message) {
        if (instance == null) return;
        synchronized (instance.lock) {
            instance.buffer.offerLast(new LogRow(System.currentTimeMillis(), "CHAT", player, "<"+player+"> "+message));
        }
    }
    public static void appendCommand(String player, String command) {
        if (instance == null) return;
        synchronized (instance.lock) {
            instance.buffer.offerLast(new LogRow(System.currentTimeMillis(), "COMMAND", player, player + ": /" + command));
        }
    }

    private void flush() {
        java.util.List<LogRow> snapshot;
        synchronized (lock) {
            if (buffer.isEmpty()) return;
            snapshot = new java.util.ArrayList<>(buffer);
            buffer.clear();
        }
        String serverId = plugin.getConfig().getString("server-id", "unknown");
        try (Connection c = plugin.getDatabase().getConnection();
             PreparedStatement ps = c.prepareStatement(
                 "INSERT INTO web_server_log(server_id, level, source, message, created) VALUES(?,?,?,?,?)")) {
            for (LogRow r : snapshot) {
                ps.setString(1, serverId);
                ps.setString(2, r.level);
                ps.setString(3, r.source);
                ps.setString(4, r.message);
                ps.setLong(5, r.ts);
                ps.addBatch();
            }
            ps.executeBatch();
        } catch (Exception e) {
            // Không log ở đây — tránh recursive vào appender
            System.err.println("[DogelandSync] Console log flush failed: " + e.getMessage());
        }
    }

    /** Rút gọn logger name: "net.minecraft.server.MinecraftServer" → "MinecraftServer" */
    private static String simplifyLogger(String name) {
        if (name == null || name.isEmpty()) return "";
        int dot = name.lastIndexOf('.');
        String s = dot >= 0 ? name.substring(dot + 1) : name;
        if (s.length() > 32) s = s.substring(0, 32);
        return s;
    }
}
```

**Thêm Listener bắt chat + player commands (optional, đẹp hơn):**

**File: `src/main/java/vn/dogeland/sync/tasks/ConsoleChatListener.java`**

```java
package vn.dogeland.sync.tasks;

import io.papermc.paper.event.player.AsyncChatEvent;
import net.kyori.adventure.text.serializer.plain.PlainTextComponentSerializer;
import org.bukkit.event.EventHandler;
import org.bukkit.event.EventPriority;
import org.bukkit.event.Listener;
import org.bukkit.event.player.PlayerCommandPreprocessEvent;
import org.bukkit.event.player.PlayerJoinEvent;
import org.bukkit.event.player.PlayerQuitEvent;

public class ConsoleChatListener implements Listener {
    @EventHandler(priority = EventPriority.MONITOR, ignoreCancelled = true)
    public void onChat(AsyncChatEvent ev) {
        String msg = PlainTextComponentSerializer.plainText().serialize(ev.message());
        ConsoleLogAppender.appendChat(ev.getPlayer().getName(), msg);
    }
    @EventHandler(priority = EventPriority.MONITOR, ignoreCancelled = true)
    public void onCmd(PlayerCommandPreprocessEvent ev) {
        String cmd = ev.getMessage();
        if (cmd.startsWith("/")) cmd = cmd.substring(1);
        ConsoleLogAppender.appendCommand(ev.getPlayer().getName(), cmd);
    }
    @EventHandler(priority = EventPriority.MONITOR)
    public void onJoin(PlayerJoinEvent ev) {
        ConsoleLogAppender.appendChat("SYSTEM", ev.getPlayer().getName() + " joined the game");
    }
    @EventHandler(priority = EventPriority.MONITOR)
    public void onQuit(PlayerQuitEvent ev) {
        ConsoleLogAppender.appendChat("SYSTEM", ev.getPlayer().getName() + " left the game");
    }
}
```

**Trong `DogelandSync.java#onEnable()` sau khi register appender:**

```java
getServer().getPluginManager().registerEvents(new ConsoleChatListener(), this);
```

**Performance notes:**
- Buffer 1000 lines max trong memory, drop oldest nếu burst (ko bao giờ block server)
- Flush async mỗi 1s = max 1s delay tới web
- Batch INSERT giảm DB roundtrip
- Web `cleanup_server_logs()` chạy mỗi phút giữ tối đa 3000 row/server (~5 phút log thông thường)
- Nếu server siêu verbose (vd: debug mode) buffer có thể overflow → tăng `MAX_BUFFER` hoặc giảm flush interval xuống 500ms

**Web side:**
- Admin Mode → tab "Server Console" → khối "Live Console" auto SSE stream
- Dropdown filter theo server (lobby/sdo/towny/skyblock/all)
- Color-coded: INFO grey, WARN vàng, ERROR đỏ, CHAT xanh dương, COMMAND xanh lá
- Auto-scroll toggle (off khi user scroll lên xem cũ)
- Reconnect tự động khi mất kết nối

---

## 5. InventoryHistoryManager — backup snapshot trước khi modify

**Mục đích:** trước khi snapshot ghi đè inventory (DELETE+INSERT trong [SyncService.java](plugin/src/main/java/vn/dogeland/sync/sync/SyncService.java)), dump state cũ vào `web_inventory_history` (gzip JSON) để có thể rollback.

**Memory bounded:**
- Snapshot CHỈ khi: pre-action (trước list_market/withdraw/give) hoặc admin trigger — KHÔNG snapshot mỗi join/quit (nó sẽ phình DB)
- Async write, không block main thread
- Gzip JSON → ~70% size reduction
- Web side tự cleanup: cap 50/player + TTL 30 ngày (xem [includes/inv_safety.php](includes/inv_safety.php))

**File: `src/main/java/vn/dogeland/sync/safety/InventoryHistoryManager.java`**

```java
package vn.dogeland.sync.safety;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import org.bukkit.Bukkit;
import org.bukkit.entity.Player;
import org.bukkit.inventory.ItemStack;
import org.bukkit.plugin.Plugin;
import vn.dogeland.sync.db.Database;
import vn.dogeland.sync.inv.InventorySnapshot;
import vn.dogeland.sync.inv.InventorySnapshot.SlotEntry;
import vn.dogeland.sync.inv.ItemSerializer;

import java.io.ByteArrayOutputStream;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.util.List;
import java.util.zip.GZIPOutputStream;

public final class InventoryHistoryManager {
    private final Plugin plugin;
    private final Database db;
    private final String serverId;

    public InventoryHistoryManager(Plugin plugin, Database db, String serverId) {
        this.plugin = plugin; this.db = db; this.serverId = serverId;
    }

    /** Gọi từ MAIN THREAD trước khi pre-action / admin modify.
     *  reason: 'pre_action', 'admin_modify', 'manual'. */
    public void capture(Player p, String reason) {
        if (!Bukkit.isPrimaryThread()) return;
        final String username = p.getName();
        final List<SlotEntry> entries = InventorySnapshot.capture(p);
        Bukkit.getScheduler().runTaskAsynchronously(plugin, () -> write(username, reason, entries));
    }

    private void write(String username, String reason, List<SlotEntry> entries) {
        try {
            JsonArray arr = new JsonArray();
            for (SlotEntry e : entries) {
                ItemStack it = e.item();
                ItemSerializer.Meta m = ItemSerializer.extract(it);
                JsonObject o = new JsonObject();
                o.addProperty("section", e.section());
                o.addProperty("slot", e.slot());
                o.addProperty("material", m.material);
                o.addProperty("amount", m.amount);
                if (m.displayName != null && !m.displayName.isEmpty()) o.addProperty("display_name", m.displayName);
                if (m.enchants != null && !m.enchants.isEmpty())       o.addProperty("enchants", m.enchants);
                if (m.damage > 0) o.addProperty("damage", m.damage);
                String b64 = ItemSerializer.toBase64(it);
                if (b64 != null) o.addProperty("nbt_b64", b64);
                arr.add(o);
            }
            byte[] gz = gzip(arr.toString());
            try (Connection c = db.get();
                 PreparedStatement ps = c.prepareStatement(
                    "INSERT INTO web_inventory_history(username, server_id, reason, snapshot_gz, item_count, created) VALUES(?,?,?,?,?,?)")) {
                ps.setString(1, username);
                ps.setString(2, serverId);
                ps.setString(3, reason);
                ps.setBytes(4, gz);
                ps.setInt(5, entries.size());
                ps.setLong(6, System.currentTimeMillis());
                ps.executeUpdate();
            }
        } catch (Exception ex) {
            plugin.getLogger().warning("InventoryHistory write failed for " + username + ": " + ex.getMessage());
        }
    }

    private static byte[] gzip(String s) throws Exception {
        ByteArrayOutputStream baos = new ByteArrayOutputStream(4096);
        try (GZIPOutputStream gz = new GZIPOutputStream(baos)) {
            gz.write(s.getBytes("UTF-8"));
        }
        return baos.toByteArray();
    }
}
```

**Hook trong existing handlers:**
- `ListMarketHandler#process()`: trước khi remove item từ inv → `historyManager.capture(player, "pre_market_list")`
- `ListAuctionHandler#process()`: tương tự → `"pre_auction_open"`
- `WithdrawHandler#process()`: trước khi add item → `"pre_withdraw"`

Trong `DogelandSync.java#onEnable()`:
```java
this.historyManager = new InventoryHistoryManager(this, db, serverId);
// pass into handlers...
```

## 6. ItemLogger — log mỗi item movement có ý nghĩa kinh tế

**Mục đích:** track ai có/mất item gì khi nào do nguyên nhân nào. Web side đã có helper `item_log_write()` PHP. Plugin Java cũng cần log những event không qua web (vd: player drop item, container move) — **nhưng KHÔNG log mọi event** vì sẽ phình DB.

**Recommended events for plugin to log:**
- Action queue completions (đã có log qua `web_inv_actions.result`)
- Death drops with valuable items (filter: has display_name HOẶC enchants HOẶC damage<max_damage)
- Container deposit/withdraw chests trong server (optional, nếu cần audit)

**File: `src/main/java/vn/dogeland/sync/safety/ItemLogger.java`**

```java
package vn.dogeland.sync.safety;

import org.bukkit.Bukkit;
import org.bukkit.inventory.ItemStack;
import org.bukkit.plugin.Plugin;
import vn.dogeland.sync.db.Database;
import vn.dogeland.sync.inv.ItemSerializer;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.util.concurrent.LinkedBlockingQueue;

/** Async batched logger — buffer 200 entry, flush mỗi 5s. KHÔNG block main thread. */
public final class ItemLogger {
    private final Plugin plugin;
    private final Database db;
    private final String serverId;
    private final LinkedBlockingQueue<Entry> queue = new LinkedBlockingQueue<>(2000); // bounded

    private record Entry(String username, String itemKey, String itemName, int qty, String action,
                         int direction, String sourceType, long sourceId, String actor, String nbtB64, String note, long ts) {}

    public ItemLogger(Plugin plugin, Database db, String serverId) {
        this.plugin = plugin; this.db = db; this.serverId = serverId;
        Bukkit.getScheduler().runTaskTimerAsynchronously(plugin, this::flush, 100L, 100L); // mỗi 5s
    }

    /** Log 1 item movement. Non-blocking — drop nếu queue full. */
    public void log(String username, ItemStack item, int qty, String action, int direction, String actor, String note) {
        if (item == null || item.getType().isAir()) return;
        ItemSerializer.Meta m = ItemSerializer.extract(item);
        String b64 = ItemSerializer.toBase64(item);
        boolean added = queue.offer(new Entry(
            username, m.itemKey, m.material, qty, action, direction, "", 0, actor, b64, note, System.currentTimeMillis()
        ));
        if (!added) plugin.getLogger().warning("ItemLog queue full — dropped event for " + username);
    }

    private void flush() {
        if (queue.isEmpty()) return;
        java.util.List<Entry> batch = new java.util.ArrayList<>(200);
        queue.drainTo(batch, 200);
        if (batch.isEmpty()) return;
        try (Connection c = db.get();
             PreparedStatement ps = c.prepareStatement(
                "INSERT INTO web_item_log(username, server_id, item_key, item_name, qty, action, direction, source_type, source_id, actor, nbt_b64, note, created) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)")) {
            for (Entry e : batch) {
                ps.setString(1, e.username); ps.setString(2, serverId);
                ps.setString(3, e.itemKey);  ps.setString(4, e.itemName);
                ps.setInt(5, e.qty);         ps.setString(6, e.action);
                ps.setInt(7, e.direction);   ps.setString(8, e.sourceType);
                ps.setLong(9, e.sourceId);   ps.setString(10, e.actor);
                ps.setString(11, e.nbtB64);  ps.setString(12, e.note);
                ps.setLong(13, e.ts);
                ps.addBatch();
            }
            ps.executeBatch();
        } catch (Exception ex) {
            plugin.getLogger().warning("ItemLog flush failed (" + batch.size() + " entries lost): " + ex.getMessage());
        }
    }
}
```

**Memory characteristics:**
- Queue bounded (2000 max) → max ~1MB RAM
- Drop oldest if full (warn but không crash)
- Async flush 5s → max 5s delay tới DB
- Batch INSERT (max 200/batch) → 1 DB roundtrip cho nhiều entries
- Web side auto-purge TTL 90 ngày + hard cap 500k rows

## 7. Velocity proxy support

Khi dùng Velocity (modern BungeeCord) làm proxy trước 4 backend MC (lobby/sdo/towny/skyblock):

### AuthMe config trên BACKEND (mỗi server lobby/sdo/towny/skyblock):

```yaml
Hooks:
    bungeecord: true                                  # đổi từ false
    proxySharedSecret: '<your-secret-here>'           # cùng giá trị mọi backend + Velocity
    sendPlayerTo: ''                                  # bỏ trống nếu không want auto-send
```

### Trên VELOCITY proxy: cài AuthMe Velocity plugin

Download: https://github.com/AuthMe/AuthMeVelocity/releases

File `plugins/authmevelocity/main.toml`:
```toml
proxy-shared-secret = "<your-secret-here>"   # khớp với backends
auth-servers = ["lobby"]                       # server làm auth (player join vào đây trước)
limbo-server = "lobby"                         # nơi player limbo nếu chưa login
```

### DogelandSync trên Velocity?

**Không cần.** DogelandSync chỉ chạy trên backend MC (Paper) vì nó sync inventory thật của player với DB. Velocity không có inventory.

Mỗi backend có `server-id` riêng trong `config.yml`:
- lobby: `server-id: lobby`
- sdo: `server-id: sdo`
- towny: `server-id: towny`
- skyblock: `server-id: skyblock`

→ Plugin trên mỗi backend tự ghi data vào DB chung, web tự merge theo `server_id`.

### Player UUID với Velocity

Velocity forward Mojang UUID thật của player (nếu enable `player-info-forwarding-mode = MODERN`). DogelandSync dùng `p.getUniqueId()` → tự động lấy đúng UUID forward.

Đảm bảo backend `paper-global.yml`:
```yaml
proxies:
  velocity:
    enabled: true
    online-mode: true
    secret: '<velocity-forwarding-secret>'
```

## Test plan

1. Build plugin (mvn clean package) → copy JAR sang 3 server Minecraft với `server-id` đúng (sdo/towny/skyblock)
2. Vào web `http://44.222.244.164/` → check 3 server card có đổi từ Offline → Online sau 5-10s
3. Vào `/?p=top&srv=sdo` → check có người chơi xuất hiện sau 30s (StatsTask chạy)
4. Login owner → Admin Mode → tab "Server Console" → gõ thử `say Hello from web!` chọn server đang online → check chat in-game + log "done" trong bảng

## Troubleshooting

- **Server vẫn Offline trên web**: check log MC server có lỗi DB không. Kiểm tra `last_beat` table:
  ```sql
  SELECT server_id, FROM_UNIXTIME(last_beat/1000) FROM web_sync_heartbeat;
  ```
- **Stats không xuất hiện**: chắc chắn có player online lúc StatsTask chạy (mỗi 30s 1 lần)
- **Lệnh status mãi `pending`**: plugin chưa pickup hoặc `server_id` trong queue ≠ config plugin. Check:
  ```sql
  SELECT id, server_id, status FROM web_rcon_queue ORDER BY id DESC LIMIT 5;
  ```
- **Lệnh `failed` no output**: thường là sai cú pháp Minecraft command, hoặc lệnh cần permission chưa cấp cho ConsoleSender (rare). Test lệnh trong console trực tiếp trước.
