package vn.dogeland.sync.listeners;

import org.bukkit.Bukkit;
import org.bukkit.entity.HumanEntity;
import org.bukkit.entity.Player;
import org.bukkit.event.EventHandler;
import org.bukkit.event.EventPriority;
import org.bukkit.event.Listener;
import org.bukkit.event.inventory.InventoryCloseEvent;
import org.bukkit.event.player.PlayerJoinEvent;
import org.bukkit.event.player.PlayerQuitEvent;
import org.bukkit.plugin.Plugin;
import vn.dogeland.sync.sync.SyncService;

public final class SyncListeners implements Listener {

    private final Plugin plugin;
    private final SyncService sync;
    private final boolean onJoin;
    private final boolean onQuit;
    private final boolean onClose;

    public SyncListeners(Plugin plugin, SyncService sync, boolean onJoin, boolean onQuit, boolean onClose) {
        this.plugin  = plugin;
        this.sync    = sync;
        this.onJoin  = onJoin;
        this.onQuit  = onQuit;
        this.onClose = onClose;
    }

    @EventHandler(priority = EventPriority.MONITOR, ignoreCancelled = true)
    public void onJoin(PlayerJoinEvent e) {
        if (!onJoin) return;
        // Defer 1 tick: chờ Bukkit load xong inventory từ player.dat,
        // sau đó apply pending gives TRƯỚC khi snapshot (để snapshot phản ánh đúng inv mới)
        final Player p = e.getPlayer();
        Bukkit.getScheduler().runTask(plugin, () -> {
            if (!p.isOnline()) return;
            sync.applyPendingGives(p);
            sync.requestSnapshot(p, true);
        });
    }

    @EventHandler(priority = EventPriority.MONITOR)
    public void onQuit(PlayerQuitEvent e) {
        if (!onQuit) return;
        // Player vẫn còn ở main thread tại event này, capture được inventory
        sync.requestSnapshot(e.getPlayer(), true);
        sync.clearLastSync(e.getPlayer().getUniqueId());
    }

    @EventHandler(priority = EventPriority.MONITOR)
    public void onInvClose(InventoryCloseEvent e) {
        if (!onClose) return;
        HumanEntity h = e.getPlayer();
        if (h instanceof Player p) {
            // Defer 1 tick: chờ Bukkit move item về player inv (vd: rút từ chest)
            Bukkit.getScheduler().runTask(plugin, () -> {
                if (p.isOnline()) sync.requestSnapshot(p, false);
            });
        }
    }
}
