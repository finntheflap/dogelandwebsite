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
 * Action `list_auction`:
 *   payload = {"inv_id":123, "qty":1, "start_price":500, "duration_ms":86400000,
 *              "listing_fee":2, "item_key":"diamond_sword"?}
 *
 * Listing fee đã được web trừ TRƯỚC khi enqueue. Plugin chỉ ghi lại để khi hết hạn
 * không có ai bid, web sẽ hoàn fee (logic đã có trong includes/auction.php).
 */
public final class ListAuctionHandler implements ActionHandler {

    private final Plugin plugin;
    private final Database db;

    public ListAuctionHandler(Plugin plugin, Database db) {
        this.plugin = plugin;
        this.db = db;
    }

    @Override public String type() { return "list_auction"; }

    @Override
    public void process(Action a, ResultCallback cb) {
        long invId      = a.payload().has("inv_id") ? a.payload().get("inv_id").getAsLong() : 0;
        int qty         = a.payload().has("qty") ? a.payload().get("qty").getAsInt() : 1;
        long startPrice = a.payload().has("start_price") ? a.payload().get("start_price").getAsLong() : 0;
        long durationMs = a.payload().has("duration_ms") ? a.payload().get("duration_ms").getAsLong() : 86_400_000L;
        long listingFee = a.payload().has("listing_fee") ? a.payload().get("listing_fee").getAsLong() : 0;
        String itemKeyOverride = a.payload().has("item_key") ? a.payload().get("item_key").getAsString() : "";

        if (invId <= 0)      { cb.fail("inv_id không hợp lệ"); return; }
        if (qty   <= 0)      { cb.fail("qty phải > 0"); return; }
        if (startPrice <= 0) { cb.fail("start_price phải > 0"); return; }
        if (durationMs <= 0) { cb.fail("duration_ms phải > 0"); return; }

        InvRow row;
        try { row = readRow(invId, a.username(), a.mode()); }
        catch (Exception ex) { cb.fail("DB lỗi: " + ex.getMessage()); return; }
        if (row == null) { cb.fail("Vật phẩm không có trong kho."); return; }
        if (qty > row.qty) { cb.fail("Số lượng vượt kho (" + row.qty + ")."); return; }

        ItemStack target = ItemSerializer.fromBase64(row.nbtB64);
        if (target == null) { cb.fail("nbt_b64 trống."); return; }

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
            cb.fail("Bạn cần online để đăng đấu giá.");
            return;
        }
        if (removed < qty) {
            refund(a.username(), target, removed);
            cb.fail("Không đủ item (cần " + qty + ", chỉ có " + removed + ").");
            return;
        }

        long auctionId;
        try {
            auctionId = insertAuction(a.username(), row, qty, startPrice, durationMs, listingFee, itemKeyOverride);
            updateOrDeleteRow(invId, qty, row.qty);
        } catch (Exception ex) {
            refund(a.username(), target, qty);
            cb.fail("DB lỗi: " + ex.getMessage() + " — đã trả item về inv.");
            return;
        }

        JsonObject result = new JsonObject();
        result.addProperty("auction_id", auctionId);
        result.addProperty("qty", qty);
        result.addProperty("start_price", startPrice);
        result.addProperty("end_at", System.currentTimeMillis() + durationMs);
        cb.done(result.toString());
    }

    private void refund(String username, ItemStack target, int amount) {
        if (amount <= 0) return;
        ItemStack copy = target.clone();
        copy.setAmount(amount);
        Bukkit.getScheduler().runTask(plugin, () -> {
            Player p = Bukkit.getPlayerExact(username);
            if (p != null) InventoryOps.addOrDrop(p, copy);
            else plugin.getLogger().warning("Mất " + amount + "× của " + username + " (offline khi refund).");
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

    private long insertAuction(String seller, InvRow row, int qty, long startPrice,
                               long durationMs, long listingFee, String itemKeyOverride) throws Exception {
        String itemKey = itemKeyOverride.isEmpty() ? row.itemKey : itemKeyOverride;
        if (itemKey == null || itemKey.isEmpty()) {
            itemKey = row.material == null ? "diamond" : row.material.toLowerCase();
        }
        long now = System.currentTimeMillis();
        try (Connection c = db.get();
             PreparedStatement ps = c.prepareStatement(
                 "INSERT INTO web_auctions("
                 + "item,item_key,qty,image,from_inv,mode,seller,color,price,start_price,"
                 + "top_bidder,bid_count,listing_fee,status,nbt_b64,end_at,created"
                 + ") VALUES(?,?,?,?,1,?,?,?,?,?,'',0,?,'active',?,?,?)",
                 Statement.RETURN_GENERATED_KEYS)) {
            ps.setString(1, row.itemName == null ? row.material : row.itemName);
            ps.setString(2, itemKey);
            ps.setInt(3, qty);
            ps.setString(4, row.image == null ? "" : row.image);
            ps.setString(5, row.mode);
            ps.setString(6, seller);
            ps.setString(7, "#f2b631");
            ps.setLong(8, startPrice);    // price = start_price initially (matches existing schema)
            ps.setLong(9, startPrice);
            ps.setLong(10, listingFee);
            ps.setString(11, row.nbtB64);
            ps.setLong(12, now + durationMs);
            ps.setLong(13, now);
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
