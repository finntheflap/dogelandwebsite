package vn.dogeland.sync.queue.handlers;

import com.google.gson.JsonObject;
import org.bukkit.Bukkit;
import org.bukkit.entity.Player;
import org.bukkit.inventory.ItemStack;
import org.bukkit.plugin.Plugin;
import vn.dogeland.sync.db.Database;
import vn.dogeland.sync.inv.ItemSerializer;
import vn.dogeland.sync.queue.Action;
import vn.dogeland.sync.queue.ActionHandler;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.Types;
import java.util.concurrent.TimeUnit;

/**
 * Action `withdraw` (alias `give`): trao item từ DB → inv player.
 * Dùng cho: market_cancel, auction expired no bids, market_buy (cho buyer), auction win.
 *
 *   payload = {"nbt_b64": "<base64>", "qty": 1, "item_name": "...", "item_key": "...",
 *              "source": "market_cancel"|"market_buy"|"auction_win"|"auction_expired"|"admin"}
 *
 * Player online: drop thẳng vào inv (overflow → drop xuống chân).
 * Player offline: STASH vào web_inventory với locked=2 ("pending give").
 *   Khi player join lần sau, listener sẽ tìm locked=2 rows và đưa vào inv trước khi snapshot.
 */
public final class WithdrawHandler implements ActionHandler {

    private final Plugin plugin;
    private final Database db;
    private final String alias;

    public WithdrawHandler(Plugin plugin, Database db, String alias) {
        this.plugin = plugin;
        this.db = db;
        this.alias = alias;
    }

    @Override public String type() { return alias; }

    @Override
    public void process(Action a, ResultCallback cb) {
        if (!a.payload().has("nbt_b64")) { cb.fail("Thiếu nbt_b64"); return; }
        String nbtB64 = a.payload().get("nbt_b64").getAsString();
        int qty = a.payload().has("qty") ? a.payload().get("qty").getAsInt() : 1;
        if (qty <= 0) { cb.fail("qty phải > 0"); return; }

        ItemStack item = ItemSerializer.fromBase64(nbtB64);
        if (item == null) { cb.fail("nbt_b64 không decode được"); return; }
        item.setAmount(qty);

        Boolean delivered;
        try {
            delivered = Bukkit.getScheduler().callSyncMethod(plugin, () -> {
                Player p = Bukkit.getPlayerExact(a.username());
                if (p == null) return false;
                InventoryOps.addOrDrop(p, item);
                return true;
            }).get(5, TimeUnit.SECONDS);
        } catch (Exception ex) {
            cb.fail("Lỗi schedule: " + ex.getMessage());
            return;
        }

        JsonObject result = new JsonObject();
        if (Boolean.TRUE.equals(delivered)) {
            result.addProperty("delivered", "live");
        } else {
            try {
                stash(a.username(), a.mode(), item, nbtB64);
                result.addProperty("delivered", "stashed");
            } catch (Exception ex) {
                cb.fail("Player offline và stash lỗi: " + ex.getMessage());
                return;
            }
        }
        cb.done(result.toString());
    }

    /** Insert row locked=2 vào web_inventory — sẽ được listener apply khi player join. */
    private void stash(String username, String mode, ItemStack item, String nbtB64) throws Exception {
        ItemSerializer.Meta m = ItemSerializer.extract(item);
        String displayItem = m.displayName.isEmpty()
                ? humanize(m.material)
                : m.displayName.replaceAll("(?i)[§&][0-9a-fk-or]", "");
        long now = System.currentTimeMillis();
        try (Connection c = db.get();
             PreparedStatement ps = c.prepareStatement(
                "INSERT INTO web_inventory("
                + "username,uuid,mode,section,slot,material,item,item_key,"
                + "display_name,lore,enchants,damage,max_damage,nbt_b64,"
                + "qty,color,image,locked,updated"
                + ") VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,2,?)")) {
            ps.setString(1, username);
            ps.setString(2, "");                // unknown when offline
            ps.setString(3, mode == null ? "" : mode);
            ps.setString(4, "pending");
            ps.setInt(5, -1);
            ps.setString(6, m.material);
            ps.setString(7, displayItem);
            ps.setString(8, m.itemKey);
            ps.setString(9, m.displayName == null ? "" : m.displayName);
            if (m.lore == null) ps.setNull(10, Types.LONGVARCHAR);
            else ps.setString(10, m.lore);
            ps.setString(11, m.enchants);
            ps.setInt(12, m.damage);
            ps.setInt(13, m.maxDamage);
            ps.setString(14, nbtB64);
            ps.setInt(15, item.getAmount());
            ps.setString(16, "#f2b631");
            ps.setString(17, "");
            ps.setLong(18, now);
            ps.executeUpdate();
        }
    }

    private static String humanize(String material) {
        if (material == null || material.isEmpty()) return "?";
        String[] parts = material.toLowerCase().split("_");
        StringBuilder b = new StringBuilder();
        for (int i = 0; i < parts.length; i++) {
            if (i > 0) b.append(' ');
            if (parts[i].isEmpty()) continue;
            b.append(Character.toUpperCase(parts[i].charAt(0))).append(parts[i].substring(1));
        }
        return b.toString();
    }
}
