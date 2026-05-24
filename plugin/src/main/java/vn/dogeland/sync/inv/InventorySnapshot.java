package vn.dogeland.sync.inv;

import org.bukkit.entity.Player;
import org.bukkit.inventory.Inventory;
import org.bukkit.inventory.ItemStack;
import org.bukkit.inventory.PlayerInventory;

import java.util.ArrayList;
import java.util.List;

/**
 * Đọc 4 loại inventory của 1 player thành danh sách slot phẳng.
 *
 * Convention `section`:
 *   - "main"   slot 0..35  (0-8 hotbar, 9-35 main)
 *   - "armor"  slot 0..3   (boots=0, leggings=1, chest=2, helmet=3)
 *   - "offhand" slot 0
 *   - "ender"  slot 0..26
 */
public final class InventorySnapshot {

    public record SlotEntry(String section, int slot, ItemStack item) {}

    private InventorySnapshot() {}

    public static List<SlotEntry> capture(Player p) {
        List<SlotEntry> out = new ArrayList<>(80);

        PlayerInventory inv = p.getInventory();

        // Main 0..35
        for (int i = 0; i < 36; i++) {
            ItemStack it = inv.getItem(i);
            if (it != null && !it.getType().isAir()) {
                out.add(new SlotEntry("main", i, it.clone()));
            }
        }

        // Armor 0..3 (boots, leggings, chestplate, helmet)
        ItemStack[] armor = inv.getArmorContents();
        for (int i = 0; i < armor.length; i++) {
            ItemStack it = armor[i];
            if (it != null && !it.getType().isAir()) {
                out.add(new SlotEntry("armor", i, it.clone()));
            }
        }

        // Offhand
        ItemStack off = inv.getItemInOffHand();
        if (off != null && !off.getType().isAir()) {
            out.add(new SlotEntry("offhand", 0, off.clone()));
        }

        // Ender chest 0..26
        Inventory ec = p.getEnderChest();
        for (int i = 0; i < ec.getSize(); i++) {
            ItemStack it = ec.getItem(i);
            if (it != null && !it.getType().isAir()) {
                out.add(new SlotEntry("ender", i, it.clone()));
            }
        }

        return out;
    }
}
