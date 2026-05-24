package vn.dogeland.sync.tasks;

import org.bukkit.Bukkit;
import org.bukkit.scheduler.BukkitRunnable;
import vn.dogeland.sync.DogelandSync;
import vn.dogeland.sync.db.Database;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.util.ArrayList;
import java.util.List;

/**
 * Poll web_rcon_queue mỗi 1s, claim atomically, exec qua console, update result.
 *
 * SYNC task (runTaskTimer NOT Async) — Bukkit.dispatchCommand chỉ chạy main thread.
 * DB queries trên main thread (max 10 cmd/tick, thường rỗng → OK).
 *
 * Output của command sẽ tự hiện trong Live Console (web admin) vì ConsoleLogAppender
 * đã capture mọi server log → KHÔNG cần wrap CommandSender riêng (modern Paper reject
 * custom sender vì yêu cầu vanilla command listener interface).
 */
public final class RconConsumerTask extends BukkitRunnable {

    private final DogelandSync plugin;
    private final Database db;
    private final String serverId;

    public RconConsumerTask(DogelandSync plugin, Database db, String serverId) {
        this.plugin = plugin;
        this.db = db;
        this.serverId = serverId;
    }

    @Override
    public void run() {
        List<Long> claimed = new ArrayList<>();
        try (Connection c = db.get()) {
            try (PreparedStatement find = c.prepareStatement(
                    "SELECT id FROM web_rcon_queue WHERE status='pending' AND server_id=? ORDER BY id ASC LIMIT 10");
                 PreparedStatement claim = c.prepareStatement(
                    "UPDATE web_rcon_queue SET status='processing' WHERE id=? AND status='pending'")) {
                find.setString(1, serverId);
                try (ResultSet rs = find.executeQuery()) {
                    while (rs.next()) {
                        long id = rs.getLong(1);
                        claim.setLong(1, id);
                        if (claim.executeUpdate() == 1) claimed.add(id);
                    }
                }
            }

            for (long id : claimed) {
                String cmd = null;
                try (PreparedStatement get = c.prepareStatement("SELECT command FROM web_rcon_queue WHERE id=?")) {
                    get.setLong(1, id);
                    try (ResultSet rs = get.executeQuery()) { if (rs.next()) cmd = rs.getString(1); }
                }
                if (cmd == null || cmd.isEmpty()) continue;

                // Strip leading slash nếu user gõ /cmd
                if (cmd.startsWith("/")) cmd = cmd.substring(1);

                boolean ok = false;
                String resultMsg;
                try {
                    ok = Bukkit.dispatchCommand(Bukkit.getConsoleSender(), cmd);
                    resultMsg = ok
                        ? "(executed — xem output ở Live Console)"
                        : "(command unknown hoặc fail — xem output ở Live Console)";
                } catch (Throwable t) {
                    resultMsg = "EXCEPTION: " + t.getClass().getSimpleName() + ": " + t.getMessage();
                }

                if (resultMsg.length() > 4000) resultMsg = resultMsg.substring(0, 4000) + "...(truncated)";

                try (PreparedStatement upd = c.prepareStatement(
                        "UPDATE web_rcon_queue SET status=?, output=?, executed=? WHERE id=?")) {
                    upd.setString(1, ok ? "done" : "failed");
                    upd.setString(2, resultMsg);
                    upd.setLong(3, System.currentTimeMillis());
                    upd.setLong(4, id);
                    upd.executeUpdate();
                }
            }
        } catch (Exception ex) {
            plugin.getLogger().warning("RCON consumer failed: " + ex.getMessage());
        }
    }
}
