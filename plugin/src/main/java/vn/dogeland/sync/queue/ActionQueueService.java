package vn.dogeland.sync.queue;

import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import org.bukkit.Bukkit;
import org.bukkit.plugin.Plugin;
import org.bukkit.scheduler.BukkitTask;
import vn.dogeland.sync.db.Database;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.logging.Level;

/**
 * Async poller bảng web_inv_actions. Atomic claim bằng cách UPDATE status
 * thành 1 token unique cho mỗi tick → tránh 2 plugin instance claim trùng row.
 *
 * Mỗi action:
 *   1. Claim (UPDATE status='processing-<token>')
 *   2. Route đến handler tương ứng
 *   3. Handler tự schedule công việc cần main thread
 *   4. Callback ghi kết quả (done/failed) vào DB
 */
public final class ActionQueueService {

    private final Plugin plugin;
    private final Database db;
    private final String serverId;
    private final int pollMs;
    private final int batchSize;
    private final boolean verbose;
    private final Map<String, ActionHandler> handlers = new HashMap<>();

    private BukkitTask task;

    public ActionQueueService(Plugin plugin, Database db, String serverId,
                              int pollMs, int batchSize, boolean verbose) {
        this.plugin    = plugin;
        this.db        = db;
        this.serverId  = serverId;
        this.pollMs    = pollMs;
        this.batchSize = batchSize;
        this.verbose   = verbose;
    }

    public void register(ActionHandler h) {
        handlers.put(h.type(), h);
    }

    public void start() {
        long ticks = Math.max(1L, pollMs / 50L);  // 1 tick = 50ms
        task = Bukkit.getScheduler().runTaskTimerAsynchronously(plugin, this::tick, ticks, ticks);
        plugin.getLogger().info("ActionQueue poller bật mỗi " + pollMs + "ms (batch=" + batchSize + ")");
    }

    public void stop() {
        if (task != null) task.cancel();
    }

    private void tick() {
        List<Action> batch;
        try {
            batch = claim();
        } catch (Exception ex) {
            plugin.getLogger().log(Level.WARNING, "ActionQueue claim lỗi: " + ex.getMessage(), ex);
            return;
        }
        for (Action a : batch) {
            ActionHandler h = handlers.get(a.type());
            if (h == null) {
                markFailed(a.id(), "unknown action type: " + a.type());
                continue;
            }
            try {
                h.process(a, new ActionHandler.ResultCallback() {
                    @Override public void done(String resultJson) { markDone(a.id(), resultJson); }
                    @Override public void fail(String reason)     { markFailed(a.id(), reason); }
                });
            } catch (Exception ex) {
                plugin.getLogger().log(Level.SEVERE,
                        "Handler '" + a.type() + "' lỗi cho action #" + a.id() + ": " + ex.getMessage(), ex);
                markFailed(a.id(), "exception: " + ex.getMessage());
            }
        }
    }

    private List<Action> claim() throws Exception {
        // Token duy nhất cho lần claim này — tránh xung đột với plugin instance khác
        String token = "processing-" + UUID.randomUUID().toString().substring(0, 8);
        List<Action> out = new ArrayList<>();
        try (Connection c = db.get()) {
            // 1) Atomic claim
            try (PreparedStatement ps = c.prepareStatement(
                    "UPDATE web_inv_actions SET status=? "
                    + "WHERE status='pending' AND (mode=? OR mode='') "
                    + "ORDER BY id ASC LIMIT " + batchSize)) {
                ps.setString(1, token);
                ps.setString(2, serverId);
                int claimed = ps.executeUpdate();
                if (claimed == 0) return out;
            }
            // 2) Đọc lại các row đã claim
            try (PreparedStatement ps = c.prepareStatement(
                    "SELECT id,username,mode,action,payload,requested_by "
                    + "FROM web_inv_actions WHERE status=? ORDER BY id ASC")) {
                ps.setString(1, token);
                try (ResultSet rs = ps.executeQuery()) {
                    while (rs.next()) {
                        String payloadStr = rs.getString("payload");
                        JsonObject payload;
                        try {
                            payload = JsonParser.parseString(payloadStr == null ? "{}" : payloadStr).getAsJsonObject();
                        } catch (Exception parseEx) {
                            markFailed(rs.getLong("id"), "payload JSON parse lỗi: " + parseEx.getMessage());
                            continue;
                        }
                        out.add(new Action(
                                rs.getLong("id"),
                                rs.getString("username"),
                                rs.getString("mode"),
                                rs.getString("action"),
                                payload,
                                rs.getString("requested_by")));
                    }
                }
            }
        }
        if (verbose && !out.isEmpty()) {
            plugin.getLogger().info("Claimed " + out.size() + " actions (token=" + token + ")");
        }
        return out;
    }

    private void markDone(long id, String resultJson) {
        Bukkit.getScheduler().runTaskAsynchronously(plugin, () -> {
            try (Connection c = db.get();
                 PreparedStatement ps = c.prepareStatement(
                    "UPDATE web_inv_actions SET status='done',result=?,processed=? WHERE id=?")) {
                ps.setString(1, resultJson);
                ps.setLong(2, System.currentTimeMillis());
                ps.setLong(3, id);
                ps.executeUpdate();
            } catch (Exception ex) {
                plugin.getLogger().warning("markDone lỗi action #" + id + ": " + ex.getMessage());
            }
        });
    }

    private void markFailed(long id, String reason) {
        Bukkit.getScheduler().runTaskAsynchronously(plugin, () -> {
            try (Connection c = db.get();
                 PreparedStatement ps = c.prepareStatement(
                    "UPDATE web_inv_actions SET status='failed',result=?,processed=? WHERE id=?")) {
                ps.setString(1, reason);
                ps.setLong(2, System.currentTimeMillis());
                ps.setLong(3, id);
                ps.executeUpdate();
            } catch (Exception ex) {
                plugin.getLogger().warning("markFailed lỗi action #" + id + ": " + ex.getMessage());
            }
        });
    }
}
