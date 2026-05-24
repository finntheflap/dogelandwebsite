package vn.dogeland.sync.sync;

import org.bukkit.Bukkit;
import org.bukkit.entity.Player;
import org.bukkit.inventory.ItemStack;
import org.bukkit.plugin.Plugin;
import vn.dogeland.sync.db.Database;
import vn.dogeland.sync.inv.InventorySnapshot;
import vn.dogeland.sync.inv.InventorySnapshot.SlotEntry;
import vn.dogeland.sync.inv.ItemSerializer;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Types;
import java.util.ArrayList;
import java.util.List;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.TimeUnit;
import java.util.logging.Level;

/**
 * Orchestrate đọc inventory (main thread) → ghi DB (async thread).
 * Mỗi snapshot là 1 transaction: xoá hết row non-locked cho user+mode, rồi insert lại.
 * Row `locked=1` được GIỮ NGUYÊN — đó là vật phẩm đang rao bán/đấu giá trên web.
 */
public final class SyncService {

    private final Plugin plugin;
    private final Database db;
    private final String serverId;
    private final long debounceMs;
    private final boolean verbose;

    private final ConcurrentHashMap<UUID, Long> lastSyncAt = new ConcurrentHashMap<>();

    public SyncService(Plugin plugin, Database db, String serverId, long debounceMs, boolean verbose) {
        this.plugin     = plugin;
        this.db         = db;
        this.serverId   = serverId;
        this.debounceMs = debounceMs;
        this.verbose    = verbose;
    }

    /**
     * Phải gọi từ MAIN THREAD (snapshot Player). Việc ghi DB sẽ async.
     * @param force bỏ qua debounce (cho /dgsync và quit event)
     */
    public void requestSnapshot(Player p, boolean force) {
        if (!Bukkit.isPrimaryThread()) {
            plugin.getLogger().warning("requestSnapshot called off main thread — bỏ qua: " + p.getName());
            return;
        }
        long now = System.currentTimeMillis();
        if (!force) {
            Long last = lastSyncAt.get(p.getUniqueId());
            if (last != null && now - last < debounceMs) return;
        }
        lastSyncAt.put(p.getUniqueId(), now);

        // Capture đồng bộ trên main thread, write async
        final String username = p.getName();
        final String uuid     = p.getUniqueId().toString();
        final List<SlotEntry> entries = InventorySnapshot.capture(p);

        Bukkit.getScheduler().runTaskAsynchronously(plugin, () -> writeSnapshot(username, uuid, entries));
    }

    private void writeSnapshot(String username, String uuid, List<SlotEntry> entries) {
        long t0 = System.currentTimeMillis();
        try (Connection c = db.get()) {
            c.setAutoCommit(false);
            try {
                // 1) Xoá hết row non-locked của user trong mode này
                try (PreparedStatement ps = c.prepareStatement(
                        "DELETE FROM web_inventory WHERE username=? AND mode=? AND locked=0")) {
                    ps.setString(1, username);
                    ps.setString(2, serverId);
                    ps.executeUpdate();
                }
                // 2) Insert lại từ snapshot
                if (!entries.isEmpty()) {
                    String sql = "INSERT INTO web_inventory("
                            + "username,uuid,mode,section,slot,material,item,item_key,"
                            + "display_name,lore,enchants,damage,max_damage,nbt_b64,"
                            + "qty,color,image,locked,updated"
                            + ") VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?)";
                    try (PreparedStatement ps = c.prepareStatement(sql)) {
                        long now = System.currentTimeMillis();
                        for (SlotEntry e : entries) {
                            ItemSerializer.Meta m = ItemSerializer.extract(e.item());
                            String b64 = ItemSerializer.toBase64(e.item());
                            String displayItem = m.displayName.isEmpty()
                                    ? prettify(m.material)
                                    : stripFormat(m.displayName);

                            ps.setString(1, username);
                            ps.setString(2, uuid);
                            ps.setString(3, serverId);
                            ps.setString(4, e.section());
                            ps.setInt(5, e.slot());
                            ps.setString(6, m.material);
                            ps.setString(7, displayItem);
                            ps.setString(8, m.itemKey);
                            ps.setString(9, m.displayName == null ? "" : m.displayName);
                            if (m.lore == null) ps.setNull(10, Types.LONGVARCHAR);
                            else ps.setString(10, m.lore);
                            ps.setString(11, m.enchants);
                            ps.setInt(12, m.damage);
                            ps.setInt(13, m.maxDamage);
                            ps.setString(14, b64);
                            ps.setInt(15, m.amount);
                            ps.setString(16, "#888888");
                            ps.setString(17, "");
                            ps.setLong(18, now);
                            ps.addBatch();
                        }
                        ps.executeBatch();
                    }
                }
                c.commit();
            } catch (SQLException ex) {
                c.rollback();
                throw ex;
            } finally {
                c.setAutoCommit(true);
            }
        } catch (SQLException ex) {
            plugin.getLogger().log(Level.SEVERE,
                    "Lỗi ghi snapshot inventory cho " + username + ": " + ex.getMessage(), ex);
            return;
        }
        if (verbose) {
            long dt = System.currentTimeMillis() - t0;
            plugin.getLogger().info("Synced " + entries.size() + " slot cho "
                    + username + " (" + dt + "ms)");
        }
    }

    public void heartbeat(int onlineCount, String onlinePlayers, String pluginVersion) {
        Bukkit.getScheduler().runTaskAsynchronously(plugin, () -> {
            try (Connection c = db.get();
                 PreparedStatement ps = c.prepareStatement(
                    "INSERT INTO web_sync_heartbeat(server_id,online_players,plugin_version,last_beat) "
                    + "VALUES(?,?,?,?) "
                    + "ON DUPLICATE KEY UPDATE online_players=VALUES(online_players),"
                    + "plugin_version=VALUES(plugin_version),last_beat=VALUES(last_beat)")) {
                ps.setString(1, serverId);
                ps.setString(2, onlinePlayers);
                ps.setString(3, pluginVersion);
                ps.setLong(4, System.currentTimeMillis());
                ps.executeUpdate();
            } catch (SQLException ex) {
                plugin.getLogger().warning("Heartbeat lỗi: " + ex.getMessage());
            }
        });
    }

    public void clearLastSync(UUID id) {
        lastSyncAt.remove(id);
    }

    /**
     * Apply mọi row locked=2 (pending give) cho 1 player: give vào inv → delete row.
     * Phải gọi từ MAIN THREAD (đụng player inventory). DB read/write sync ở đây —
     * KHÔNG ổn cho server đông; đổi sang async + callSyncMethod nếu cần optimization.
     *
     * Trả số item đã giao.
     */
    public int applyPendingGives(Player p) {
        if (!Bukkit.isPrimaryThread()) {
            plugin.getLogger().warning("applyPendingGives must run on main thread.");
            return 0;
        }
        List<long[]> idsToDelete = new ArrayList<>();
        List<ItemStack> toGive = new ArrayList<>();
        try (Connection c = db.get();
             PreparedStatement ps = c.prepareStatement(
                "SELECT id,nbt_b64,qty FROM web_inventory "
                + "WHERE LOWER(username)=LOWER(?) AND mode=? AND locked=2")) {
            ps.setString(1, p.getName());
            ps.setString(2, serverId);
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    String nbt = rs.getString("nbt_b64");
                    int qty = rs.getInt("qty");
                    ItemStack it = ItemSerializer.fromBase64(nbt);
                    if (it == null) continue;
                    it.setAmount(qty);
                    toGive.add(it);
                    idsToDelete.add(new long[]{ rs.getLong("id") });
                }
            }
        } catch (SQLException ex) {
            plugin.getLogger().log(Level.WARNING, "applyPendingGives đọc DB lỗi", ex);
            return 0;
        }

        if (toGive.isEmpty()) return 0;

        for (ItemStack it : toGive) {
            var leftover = p.getInventory().addItem(it);
            for (ItemStack drop : leftover.values()) {
                p.getWorld().dropItemNaturally(p.getLocation(), drop);
            }
        }

        Bukkit.getScheduler().runTaskAsynchronously(plugin, () -> {
            try (Connection c = db.get();
                 PreparedStatement ps = c.prepareStatement("DELETE FROM web_inventory WHERE id=?")) {
                for (long[] arr : idsToDelete) {
                    ps.setLong(1, arr[0]);
                    ps.addBatch();
                }
                ps.executeBatch();
            } catch (SQLException ex) {
                plugin.getLogger().log(Level.WARNING, "applyPendingGives delete lỗi", ex);
            }
        });

        if (verbose) {
            plugin.getLogger().info("Applied " + toGive.size() + " pending give(s) cho " + p.getName());
        }
        p.sendMessage("§a[Dogeland] §fĐã nhận §e" + toGive.size()
                + "§f vật phẩm từ web (mua hàng/đấu giá thắng/gỡ tin).");
        return toGive.size();
    }

    /** "DIAMOND_SWORD" → "Diamond Sword" — chỉ dùng khi item không có display name. */
    private static String prettify(String material) {
        String[] parts = material.toLowerCase().split("_");
        StringBuilder b = new StringBuilder();
        for (int i = 0; i < parts.length; i++) {
            if (i > 0) b.append(' ');
            if (parts[i].isEmpty()) continue;
            b.append(Character.toUpperCase(parts[i].charAt(0))).append(parts[i].substring(1));
        }
        return b.toString();
    }

    /** Bỏ ký tự §x trong display name (web tự apply màu sau). */
    private static String stripFormat(String s) {
        if (s == null) return "";
        return s.replaceAll("(?i)[§&][0-9a-fk-or]", "");
    }
}
