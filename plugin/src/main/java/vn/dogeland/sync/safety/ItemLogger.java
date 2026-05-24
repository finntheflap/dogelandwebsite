package vn.dogeland.sync.safety;

import org.bukkit.Bukkit;
import org.bukkit.inventory.ItemStack;
import org.bukkit.plugin.Plugin;
import org.bukkit.scheduler.BukkitTask;
import vn.dogeland.sync.db.Database;
import vn.dogeland.sync.inv.ItemSerializer;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.LinkedBlockingQueue;

/**
 * Async batched item movement logger. Buffer bounded 2000 entries (~1MB RAM ceiling).
 * Flush mỗi 5s, batch 200/lần. Drop oldest nếu queue full (warn nhưng không crash).
 *
 * Plugin side dùng cho events NGOÀI action queue (vd: drop on death, container moves).
 * Web side đã có item_log_write() PHP cho admin/market/auction/gift.
 */
public final class ItemLogger {

    private static final int QUEUE_CAP = 2000;
    private static final int FLUSH_BATCH = 200;
    private static final long FLUSH_PERIOD_TICKS = 100L; // 5s

    private final Plugin plugin;
    private final Database db;
    private final String serverId;
    private final LinkedBlockingQueue<Entry> queue = new LinkedBlockingQueue<>(QUEUE_CAP);
    private BukkitTask flushTask;

    private record Entry(String username, String itemKey, String itemName, int qty, String action,
                         int direction, String sourceType, long sourceId, String actor,
                         String nbtB64, String note, long ts) {}

    public ItemLogger(Plugin plugin, Database db, String serverId) {
        this.plugin = plugin;
        this.db = db;
        this.serverId = serverId;
    }

    public void start() {
        if (flushTask != null) return;
        flushTask = Bukkit.getScheduler().runTaskTimerAsynchronously(plugin, this::flush, FLUSH_PERIOD_TICKS, FLUSH_PERIOD_TICKS);
    }

    public void stop() {
        if (flushTask != null) { flushTask.cancel(); flushTask = null; }
        flush(); // final drain
    }

    /**
     * Log 1 item movement. Non-blocking (qua queue).
     * @param direction +1 = player nhận, -1 = player mất, 0 = neutral
     */
    public void log(String username, ItemStack item, int qty, String action, int direction,
                    String actor, String note) {
        if (username == null || item == null || item.getType().isAir()) return;
        ItemSerializer.Meta m = ItemSerializer.extract(item);
        String b64 = ItemSerializer.toBase64(item);
        Entry e = new Entry(
            username, m.itemKey, m.material, qty, action, direction,
            "", 0L, actor == null ? "" : actor,
            b64, note == null ? "" : note,
            System.currentTimeMillis()
        );
        if (!queue.offer(e)) {
            plugin.getLogger().warning("ItemLog queue FULL — dropped event for " + username + " (" + action + ")");
        }
    }

    private void flush() {
        if (queue.isEmpty()) return;
        List<Entry> batch = new ArrayList<>(FLUSH_BATCH);
        queue.drainTo(batch, FLUSH_BATCH);
        if (batch.isEmpty()) return;

        try (Connection c = db.get();
             PreparedStatement ps = c.prepareStatement(
                "INSERT INTO web_item_log(username, server_id, item_key, item_name, qty, action, direction, " +
                "source_type, source_id, actor, nbt_b64, note, created) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)")) {
            for (Entry e : batch) {
                ps.setString(1, e.username);
                ps.setString(2, serverId);
                ps.setString(3, e.itemKey);
                ps.setString(4, e.itemName);
                ps.setInt(5, e.qty);
                ps.setString(6, e.action);
                ps.setInt(7, e.direction);
                ps.setString(8, e.sourceType);
                ps.setLong(9, e.sourceId);
                ps.setString(10, e.actor);
                ps.setString(11, e.nbtB64);
                ps.setString(12, e.note);
                ps.setLong(13, e.ts);
                ps.addBatch();
            }
            ps.executeBatch();
        } catch (Exception ex) {
            plugin.getLogger().warning("ItemLog flush failed (" + batch.size() + " entries lost): " + ex.getMessage());
        }
    }
}
