package vn.dogeland.sync.queue.handlers;

import org.bukkit.entity.Player;
import org.bukkit.inventory.Inventory;
import org.bukkit.inventory.ItemStack;
import org.bukkit.inventory.PlayerInventory;

/**
 * Helper cho 2 thao tác inventory dùng chung cho mọi handler:
 *   - removeMatching: tìm + remove qty của 1 ItemStack tương tự
 *   - addOrDrop: thêm item vào inv; nếu inv đầy thì drop xuống chân player
 *
 * `ItemStack.isSimilar()` so sánh tất cả trừ amount — đảm bảo enchant/NBT khớp.
 */
final class InventoryOps {

    private InventoryOps() {}

    /**
     * Tìm item tương tự trong main + armor + offhand + ender chest và remove `needQty`.
     * Trả số lượng THỰC SỰ đã remove (≤ needQty). Nếu < needQty, item không đủ —
     * caller phải gọi addOrDrop để trả lại số đã lỡ remove (atomicity ở app layer).
     */
    static int removeMatching(Player p, ItemStack target, int needQty) {
        if (target == null || needQty <= 0) return 0;
        int remaining = needQty;
        remaining = removeFrom(p.getInventory(), target, remaining);
        if (remaining > 0) remaining = removeFrom(p.getEnderChest(), target, remaining);
        return needQty - remaining;
    }

    private static int removeFrom(Inventory inv, ItemStack target, int remaining) {
        for (int i = 0; i < inv.getSize(); i++) {
            if (remaining <= 0) break;
            ItemStack it = inv.getItem(i);
            if (it == null || it.getType().isAir()) continue;
            if (!it.isSimilar(target)) continue;
            int take = Math.min(it.getAmount(), remaining);
            if (take == it.getAmount()) {
                inv.setItem(i, null);
            } else {
                ItemStack rest = it.clone();
                rest.setAmount(it.getAmount() - take);
                inv.setItem(i, rest);
            }
            remaining -= take;
        }
        return remaining;
    }

    /** Thêm item vào inv main. Phần thừa (inv đầy) thì drop xuống world tại chân player. */
    static void addOrDrop(Player p, ItemStack item) {
        if (item == null || item.getType().isAir() || item.getAmount() <= 0) return;
        PlayerInventory inv = p.getInventory();
        var leftover = inv.addItem(item);
        for (ItemStack drop : leftover.values()) {
            p.getWorld().dropItemNaturally(p.getLocation(), drop);
        }
    }
}
