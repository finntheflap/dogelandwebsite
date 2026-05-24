package vn.dogeland.sync.tasks;

import org.bukkit.Bukkit;
import org.bukkit.Statistic;
import org.bukkit.entity.Player;
import org.bukkit.plugin.RegisteredServiceProvider;
import org.bukkit.scheduler.BukkitRunnable;
import vn.dogeland.sync.DogelandSync;
import vn.dogeland.sync.db.Database;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.util.logging.Level;

/**
 * Batch upsert player stats vào web_player_stats mỗi 30s.
 * Đọc Bukkit Statistic + Vault Economy (nếu có).
 *
 * Performance:
 *  - Đọc statistic phải trên main thread (Bukkit API)
 *  - Build batch INSERT trên main thread (cheap), execute async để không lag tick
 *  - Skip nếu không có player online (no-op)
 */
public final class StatsTask extends BukkitRunnable {

    private final DogelandSync plugin;
    private final Database db;
    private final String serverId;
    private Object econ; // net.milkbowl.vault.economy.Economy — reflection-based để không hard-depend Vault

    public StatsTask(DogelandSync plugin, Database db, String serverId) {
        this.plugin = plugin;
        this.db = db;
        this.serverId = serverId;
        tryHookVault();
    }

    private void tryHookVault() {
        try {
            Class<?> ecoClz = Class.forName("net.milkbowl.vault.economy.Economy");
            RegisteredServiceProvider<?> rsp = Bukkit.getServicesManager().getRegistration(ecoClz);
            if (rsp != null) this.econ = rsp.getProvider();
        } catch (ClassNotFoundException ignored) {
            // Vault không cài → balance = 0
        } catch (Exception ex) {
            plugin.getLogger().warning("Stats: Vault hook lỗi: " + ex.getMessage());
        }
    }

    @Override
    public void run() {
        if (Bukkit.getOnlinePlayers().isEmpty()) return;

        // Capture stats trên main thread vào snapshot list
        final java.util.List<Row> rows = new java.util.ArrayList<>(Bukkit.getOnlinePlayers().size());
        final long now = System.currentTimeMillis();
        for (Player p : Bukkit.getOnlinePlayers()) {
            try {
                long playtime = p.getStatistic(Statistic.PLAY_ONE_MINUTE) / 20L;
                int level = p.getLevel();
                long xp = p.getTotalExperience();
                long bal = readBalance(p);
                int mob = p.getStatistic(Statistic.MOB_KILLS);
                int pk = p.getStatistic(Statistic.PLAYER_KILLS);
                int deaths = p.getStatistic(Statistic.DEATHS);
                rows.add(new Row(p.getName(), playtime, level, xp, bal, mob, pk, deaths, now));
            } catch (Exception ex) {
                plugin.getLogger().warning("Stats read fail for " + p.getName() + ": " + ex.getMessage());
            }
        }

        // Async write
        Bukkit.getScheduler().runTaskAsynchronously(plugin, () -> flush(rows));
    }

    private long readBalance(Player p) {
        if (econ == null) return 0L;
        try {
            return (long) econ.getClass().getMethod("getBalance", org.bukkit.OfflinePlayer.class).invoke(econ, p);
        } catch (Exception ex) {
            return 0L;
        }
    }

    private void flush(java.util.List<Row> rows) {
        if (rows.isEmpty()) return;
        try (Connection c = db.get();
             PreparedStatement ps = c.prepareStatement(
                "INSERT INTO web_player_stats" +
                "(username, server_id, playtime_sec, level, xp, balance, mob_kills, player_kills, deaths, last_seen, updated) " +
                "VALUES(?,?,?,?,?,?,?,?,?,?,?) " +
                "ON DUPLICATE KEY UPDATE " +
                "playtime_sec=VALUES(playtime_sec), level=VALUES(level), xp=VALUES(xp), " +
                "balance=VALUES(balance), mob_kills=VALUES(mob_kills), " +
                "player_kills=VALUES(player_kills), deaths=VALUES(deaths), " +
                "last_seen=VALUES(last_seen), updated=VALUES(updated)")) {
            for (Row r : rows) {
                ps.setString(1, r.username);
                ps.setString(2, serverId);
                ps.setLong(3, r.playtime);
                ps.setInt(4, r.level);
                ps.setLong(5, r.xp);
                ps.setLong(6, r.balance);
                ps.setInt(7, r.mobKills);
                ps.setInt(8, r.playerKills);
                ps.setInt(9, r.deaths);
                ps.setLong(10, r.ts);
                ps.setLong(11, r.ts);
                ps.addBatch();
            }
            ps.executeBatch();
        } catch (Exception ex) {
            plugin.getLogger().log(Level.WARNING, "Stats batch write failed: " + ex.getMessage());
        }
    }

    private record Row(String username, long playtime, int level, long xp, long balance,
                       int mobKills, int playerKills, int deaths, long ts) {}
}
