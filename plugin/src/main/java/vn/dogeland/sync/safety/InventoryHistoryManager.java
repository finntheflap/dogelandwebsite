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
import java.nio.charset.StandardCharsets;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.util.List;
import java.util.zip.GZIPOutputStream;

/**
 * Backup inventory snapshot vào web_inventory_history (gzip JSON).
 * Memory bounded: web side tự cleanup cap 50/player + TTL 30 ngày.
 *
 * Sử dụng:
 *   - capture(player, reason): snapshot trên main thread, write async
 *   - reason: 'pre_action' / 'admin_modify' / 'manual' / 'periodic'
 */
public final class InventoryHistoryManager {

    private final Plugin plugin;
    private final Database db;
    private final String serverId;

    public InventoryHistoryManager(Plugin plugin, Database db, String serverId) {
        this.plugin = plugin;
        this.db = db;
        this.serverId = serverId;
    }

    /** Gọi từ MAIN THREAD. Capture sync, write DB async. */
    public void capture(Player p, String reason) {
        if (p == null) return;
        if (!Bukkit.isPrimaryThread()) {
            plugin.getLogger().warning("InvHistory.capture called off main thread, scheduling sync...");
            Bukkit.getScheduler().runTask(plugin, () -> capture(p, reason));
            return;
        }
        final String username = p.getName();
        final List<SlotEntry> entries = InventorySnapshot.capture(p);
        Bukkit.getScheduler().runTaskAsynchronously(plugin, () -> write(username, reason, entries));
    }

    private void write(String username, String reason, List<SlotEntry> entries) {
        try {
            JsonArray arr = new JsonArray();
            for (SlotEntry e : entries) {
                ItemStack it = e.item();
                if (it == null || it.getType().isAir()) continue;
                ItemSerializer.Meta m = ItemSerializer.extract(it);
                JsonObject o = new JsonObject();
                o.addProperty("section", e.section());
                o.addProperty("slot", e.slot());
                o.addProperty("material", m.material);
                o.addProperty("amount", m.amount);
                if (m.displayName != null && !m.displayName.isEmpty()) o.addProperty("display_name", m.displayName);
                if (m.enchants != null && !m.enchants.isEmpty()) o.addProperty("enchants", m.enchants);
                if (m.damage > 0) o.addProperty("damage", m.damage);
                String b64 = ItemSerializer.toBase64(it);
                if (b64 != null) o.addProperty("nbt_b64", b64);
                arr.add(o);
            }

            byte[] gz = gzip(arr.toString());
            try (Connection c = db.get();
                 PreparedStatement ps = c.prepareStatement(
                    "INSERT INTO web_inventory_history(username, server_id, reason, snapshot_gz, item_count, created) " +
                    "VALUES(?,?,?,?,?,?)")) {
                ps.setString(1, username);
                ps.setString(2, serverId);
                ps.setString(3, reason);
                ps.setBytes(4, gz);
                ps.setInt(5, arr.size());
                ps.setLong(6, System.currentTimeMillis());
                ps.executeUpdate();
            }
        } catch (Exception ex) {
            plugin.getLogger().warning("InvHistory write failed for " + username + ": " + ex.getMessage());
        }
    }

    private static byte[] gzip(String s) throws Exception {
        ByteArrayOutputStream baos = new ByteArrayOutputStream(4096);
        try (GZIPOutputStream gz = new GZIPOutputStream(baos)) {
            gz.write(s.getBytes(StandardCharsets.UTF_8));
        }
        return baos.toByteArray();
    }
}
