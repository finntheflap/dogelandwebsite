package vn.dogeland.sync.inv;

import org.bukkit.enchantments.Enchantment;
import org.bukkit.inventory.ItemStack;
import org.bukkit.inventory.meta.Damageable;
import org.bukkit.inventory.meta.ItemMeta;

import java.util.Base64;
import java.util.Map;
import java.util.stream.Collectors;

/**
 * Bridge giữa Bukkit ItemStack và các cột trong web_inventory.
 *
 * `serializeAsBytes()` (Paper 1.20+) là cách canonical để giữ NGUYÊN VẸN NBT, enchant, custom data.
 * Web chỉ nên đọc các cột metadata (material, display_name, enchants, damage) để hiển thị —
 * còn nbt_b64 là source of truth khi plugin restore lại.
 */
public final class ItemSerializer {

    private ItemSerializer() {}

    public static String toBase64(ItemStack item) {
        if (item == null || item.getType().isAir()) return null;
        byte[] bytes = item.serializeAsBytes();
        return Base64.getEncoder().encodeToString(bytes);
    }

    public static ItemStack fromBase64(String b64) {
        if (b64 == null || b64.isEmpty()) return null;
        byte[] bytes = Base64.getDecoder().decode(b64);
        return ItemStack.deserializeBytes(bytes);
    }

    public static Meta extract(ItemStack item) {
        Meta m = new Meta();
        m.material = item.getType().name();
        m.itemKey  = m.material.toLowerCase();
        m.amount   = item.getAmount();

        if (item.hasItemMeta()) {
            ItemMeta im = item.getItemMeta();
            if (im.hasDisplayName()) m.displayName = im.getDisplayName();
            if (im.hasLore() && im.getLore() != null) {
                m.lore = String.join("\n", im.getLore());
            }
            if (!im.getEnchants().isEmpty()) {
                m.enchants = im.getEnchants().entrySet().stream()
                        .map(e -> shortKey(e.getKey()) + ":" + e.getValue())
                        .collect(Collectors.joining(","));
            }
            if (im instanceof Damageable d && item.getType().getMaxDurability() > 0) {
                m.damage    = d.getDamage();
                m.maxDamage = item.getType().getMaxDurability();
            }
        }
        return m;
    }

    private static String shortKey(Enchantment e) {
        // "minecraft:sharpness" -> "sharpness"
        String key = e.getKey().getKey();
        return key == null ? e.getKey().toString() : key;
    }

    /** Snapshot metadata để ghi vào các cột của web_inventory cho hiển thị nhanh trên web. */
    public static final class Meta {
        public String material   = "";
        public String itemKey    = "";
        public int    amount     = 1;
        public String displayName = "";
        public String lore        = null;
        public String enchants    = "";
        public int    damage      = 0;
        public int    maxDamage   = 0;
    }
}
