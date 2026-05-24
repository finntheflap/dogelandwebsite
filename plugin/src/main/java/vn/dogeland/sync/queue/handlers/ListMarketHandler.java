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
import java.sql.ResultSet;
import java.sql.Statement;
import java.util.concurrent.TimeUnit;

/**
 * Action `list_market`:
 *   payload = {"inv_id": 123, "qty": 1, "price": 500, "description": "...", "item_key": "diamond_sword"?}
 *
 * Flow:
 *   1. Đọc row web_inventory (verify ownership + chưa locked)
 *   2. Main thread: remove qty của item tương ứng khỏi inv player
 *   3. Insert web_market với nbt_b64 + delete/decrement row inventory
 *   4. Nếu DB lỗi sau khi đã remove → trả item về cho player
 */
public final class ListMarketHandler implements ActionHandler {

    private final Plugin plugin;
    private final Database db;

    public ListMarketHandler(Plugin plugin, Database db) {
        this.plugin = plugin;
        this.db = db;
    }

    @Override public String type() { return "list_market"; }

    @Override
    public void process(Action a, ResultCallback cb) {
        long invId = a.payload().has("inv_id") ? a.payload().get("inv_id").getAsLong() : 0;
        int qty    = a.payload().has("qty")    ? a.payload().get("qty").getAsInt()    : 1;
        long price = a.payload().has("price")  ? a.payload().get("price").getAsLong() : 0;
        String desc = a.payload().has("description") ? a.payload().get("description").getAsString() : "";
        String itemKeyOverride = a.payload().has("item_key") ? a.payload().get("item_key").getAsString() : "";

        if (invId <= 0) { cb.fail("inv_id không hợp lệ"); return; }
        if (price <= 0) { cb.fail("price phải > 0"); return; }
        if (qty   <= 0) { cb.fail("qty phải > 0"); return; }

        InvRow row;
        try { row = readRow(invId, a.username(), a.mode()); }
        catch (Exception ex) { cb.fail("DB lỗi đọc inventory: " + ex.getMessage()); return; }
        if (row == null) { cb.fail("Vật phẩm không có trong kho (hoặc đã khoá)."); return; }
        if (qty > row.qty) { cb.fail("Số lượng yêu cầu vượt số trong kho (" + row.qty + ")."); return; }

        ItemStack target = ItemSerializer.fromBase64(row.nbtB64);
        if (target == null) { cb.fail("Item snapshot trống NBT, không xác định được vật phẩm."); return; }

        // Remove trên main thread
        Integer removed;
        try {
            removed = Bukkit.getScheduler().callSyncMethod(plugin, () -> {
                Player p = Bukkit.getPlayerExact(a.username());
                if (p == null) return -1;
                return InventoryOps.removeMatching(p, target, qty);
            }).get(5, TimeUnit.SECONDS);
        } catch (Exception ex) {
            cb.fail("Lỗi schedule main thread: " + ex.getMessage());
            return;
        }
        if (removed == null || removed == -1) {
            cb.fail("Bạn cần online trong game để rao bán.");
            return;
        }
        if (removed < qty) {
            // Refund số đã remove
            refund(a.username(), target, removed);
            cb.fail("Không đủ item trong inv (cần " + qty + ", chỉ có " + removed + ").");
            return;
        }

        long marketId;
        try {
            marketId = insertMarket(a.username(), row, qty, price, desc, itemKeyOverride);
            updateOrDeleteRow(invId, qty, row.qty);
        } catch (Exception ex) {
            refund(a.username(), target, qty);
            cb.fail("DB lỗi khi tạo tin: " + ex.getMessage() + " — đã trả item về inv.");
            return;
        }

        JsonObject result = new JsonObject();
        result.addProperty("market_id", marketId);
        result.addProperty("qty", qty);
        result.addProperty("price", price);
        cb.done(result.toString());
    }

    private void refund(String username, ItemStack target, int amount) {
        if (amount <= 0) return;
        ItemStack copy = target.clone();
        copy.setAmount(amount);
        Bukkit.getScheduler().runTask(plugin, () -> {
            Player p = Bukkit.getPlayerExact(username);
            if (p != null) InventoryOps.addOrDrop(p, copy);
            else plugin.getLogger().warning("Không refund được " + amount + "× cho " + username
                    + " (offline). Item BỊ MẤT — cần admin restore.");
        });
    }

    private record InvRow(long id, String mode, String material, String itemName,
                          String itemKey, String image, int qty, String nbtB64) {}

    private InvRow readRow(long id, String username, String mode) throws Exception {
        try (Connection c = db.get();
             PreparedStatement ps = c.prepareStatement(
                "SELECT id,mode,material,item,item_key,image,qty,nbt_b64 "
                + "FROM web_inventory WHERE id=? AND LOWER(username)=LOWER(?) AND mode=? AND locked=0")) {
            ps.setLong(1, id);
            ps.setString(2, username);
            ps.setString(3, mode);
            try (ResultSet rs = ps.executeQuery()) {
                if (!rs.next()) return null;
                return new InvRow(rs.getLong(1), rs.getString(2), rs.getString(3),
                        rs.getString(4), rs.getString(5), rs.getString(6),
                        rs.getInt(7), rs.getString(8));
            }
        }
    }

    private long insertMarket(String seller, InvRow row, int qty, long price,
                              String desc, String itemKeyOverride) throws Exception {
        String itemKey = itemKeyOverride.isEmpty() ? row.itemKey : itemKeyOverride;
        if (itemKey == null || itemKey.isEmpty()) {
            itemKey = row.material == null ? "diamond" : row.material.toLowerCase();
        }
        try (Connection c = db.get();
             PreparedStatement ps = c.prepareStatement(
                 "INSERT INTO web_market(seller,item_name,item_key,qty,price,image,description,mode,nbt_b64,status,created) "
                 + "VALUES(?,?,?,?,?,?,?,?,?,'active',?)",
                 Statement.RETURN_GENERATED_KEYS)) {
            ps.setString(1, seller);
            ps.setString(2, row.itemName == null ? row.material : row.itemName);
            ps.setString(3, itemKey);
            ps.setInt(4, qty);
            ps.setLong(5, price);
            ps.setString(6, row.image == null ? "" : row.image);
            ps.setString(7, desc);
            ps.setString(8, row.mode);
            ps.setString(9, row.nbtB64);
            ps.setLong(10, System.currentTimeMillis());
            ps.executeUpdate();
            try (ResultSet rs = ps.getGeneratedKeys()) {
                if (rs.next()) return rs.getLong(1);
            }
        }
        return -1L;
    }

    private void updateOrDeleteRow(long id, int qtySold, int total) throws Exception {
        try (Connection c = db.get()) {
            if (qtySold >= total) {
                try (PreparedStatement ps = c.prepareStatement("DELETE FROM web_inventory WHERE id=?")) {
                    ps.setLong(1, id);
                    ps.executeUpdate();
                }
            } else {
                try (PreparedStatement ps = c.prepareStatement(
                        "UPDATE web_inventory SET qty=qty-?,updated=? WHERE id=?")) {
                    ps.setInt(1, qtySold);
                    ps.setLong(2, System.currentTimeMillis());
                    ps.setLong(3, id);
                    ps.executeUpdate();
                }
            }
        }
    }
}
