package vn.dogeland.sync;

import org.bukkit.Bukkit;
import org.bukkit.command.PluginCommand;
import org.bukkit.configuration.file.FileConfiguration;
import org.bukkit.entity.Player;
import org.bukkit.plugin.java.JavaPlugin;
import org.bukkit.scheduler.BukkitTask;
import vn.dogeland.sync.commands.SyncCommand;
import vn.dogeland.sync.db.Database;
import vn.dogeland.sync.listeners.SyncListeners;
import vn.dogeland.sync.queue.ActionQueueService;
import vn.dogeland.sync.queue.handlers.ListAuctionHandler;
import vn.dogeland.sync.queue.handlers.ListMarketHandler;
import vn.dogeland.sync.queue.handlers.WithdrawHandler;
import vn.dogeland.sync.safety.InventoryHistoryManager;
import vn.dogeland.sync.safety.ItemLogger;
import vn.dogeland.sync.sync.SyncService;
import vn.dogeland.sync.tasks.ConsoleChatListener;
import vn.dogeland.sync.tasks.ConsoleLogAppender;
import vn.dogeland.sync.tasks.RconConsumerTask;
import vn.dogeland.sync.tasks.StatsTask;

import java.util.stream.Collectors;

public final class DogelandSync extends JavaPlugin {

    private Database db;
    private SyncService sync;
    private ActionQueueService actionQueue;
    private InventoryHistoryManager historyManager;
    private ItemLogger itemLogger;
    private BukkitTask periodicTask;
    private BukkitTask heartbeatTask;
    private BukkitTask statsTask;
    private BukkitTask rconTask;
    private BukkitTask historyPeriodicTask;

    public InventoryHistoryManager history() { return historyManager; }
    public ItemLogger itemLogger() { return itemLogger; }

    @Override
    public void onEnable() {
        saveDefaultConfig();
        FileConfiguration cfg = getConfig();

        try {
            this.db = new Database(cfg.getConfigurationSection("database"), getLogger());
        } catch (Exception ex) {
            getLogger().severe("Không kết nối được MySQL — disabling plugin: " + ex.getMessage());
            getServer().getPluginManager().disablePlugin(this);
            return;
        }

        String serverId = cfg.getString("server-id", "default");
        long debounceMs = cfg.getLong("sync.debounce-ms", 1500);
        boolean verbose = cfg.getBoolean("verbose", false);

        this.sync = new SyncService(this, db, serverId, debounceMs, verbose);

        // Safety services
        this.historyManager = new InventoryHistoryManager(this, db, serverId);
        this.itemLogger = new ItemLogger(this, db, serverId);
        this.itemLogger.start();

        // Listeners
        boolean onJoin  = cfg.getBoolean("sync.on-join", true);
        boolean onQuit  = cfg.getBoolean("sync.on-quit", true);
        boolean onClose = cfg.getBoolean("sync.on-inventory-close", true);
        getServer().getPluginManager().registerEvents(
                new SyncListeners(this, sync, onJoin, onQuit, onClose), this);
        if (cfg.getBoolean("console-log.enabled", true)) {
            getServer().getPluginManager().registerEvents(new ConsoleChatListener(), this);
        }

        // Command
        PluginCommand dgs = getCommand("dgsync");
        if (dgs != null) {
            SyncCommand sc = new SyncCommand(sync);
            dgs.setExecutor(sc);
            dgs.setTabCompleter(sc);
        }
        PluginCommand reload = getCommand("dgsyncreload");
        if (reload != null) {
            reload.setExecutor((sender, command, label, args) -> {
                if (!sender.hasPermission("dogelandsync.admin")) {
                    sender.sendMessage("§cKhông có quyền.");
                    return true;
                }
                reloadConfig();
                sender.sendMessage("§aĐã reload config.yml. Một số thay đổi cần restart server để có hiệu lực.");
                return true;
            });
        }

        // Periodic inventory snapshot (existing)
        long periodicSec = cfg.getLong("sync.periodic-seconds", 0);
        if (periodicSec > 0) {
            long ticks = periodicSec * 20L;
            periodicTask = Bukkit.getScheduler().runTaskTimer(this, () -> {
                for (Player p : Bukkit.getOnlinePlayers()) sync.requestSnapshot(p, false);
            }, ticks, ticks);
            getLogger().info("Periodic inventory snapshot bật mỗi " + periodicSec + "s.");
        }

        // Heartbeat mỗi 15s (existing)
        heartbeatTask = Bukkit.getScheduler().runTaskTimer(this, this::sendHeartbeat, 100L, 300L);

        // Stats batch upsert mỗi 30s
        if (cfg.getBoolean("stats.enabled", true)) {
            statsTask = new StatsTask(this, db, serverId)
                    .runTaskTimer(this, 200L, cfg.getLong("stats.period-ticks", 600L));
            getLogger().info("Stats batch task bật (period " + cfg.getLong("stats.period-ticks", 600L) + " ticks).");
        }

        // RCON consumer (SYNC task — exec command phải main thread)
        if (cfg.getBoolean("rcon-consumer.enabled", true)) {
            rconTask = new RconConsumerTask(this, db, serverId)
                    .runTaskTimer(this, 100L, cfg.getLong("rcon-consumer.period-ticks", 20L));
            getLogger().info("RCON consumer bật.");
        }

        // Console log appender → web_server_log (live stream cho admin)
        if (cfg.getBoolean("console-log.enabled", true)) {
            ConsoleLogAppender.register(this, db, serverId);
        }

        // Periodic inventory history snapshot (rollback safety net)
        long histPeriodMin = cfg.getLong("inventory-history.period-minutes", 30);
        if (histPeriodMin > 0) {
            long ticks = histPeriodMin * 60L * 20L;
            historyPeriodicTask = Bukkit.getScheduler().runTaskTimer(this, () -> {
                for (Player p : Bukkit.getOnlinePlayers()) historyManager.capture(p, "periodic");
            }, ticks, ticks);
            getLogger().info("Inventory history backup mỗi " + histPeriodMin + " phút.");
        }

        // Action queue (Phase 3): web đẩy action xuống → plugin xử lý
        int pollMs    = cfg.getInt("action-queue.poll-interval-ms", 1500);
        int batchSize = cfg.getInt("action-queue.batch-size", 16);
        this.actionQueue = new ActionQueueService(this, db, serverId, pollMs, batchSize, verbose);
        actionQueue.register(new ListMarketHandler(this, db));
        actionQueue.register(new ListAuctionHandler(this, db));
        actionQueue.register(new WithdrawHandler(this, db, "withdraw"));
        actionQueue.register(new WithdrawHandler(this, db, "give"));
        actionQueue.start();

        getLogger().info("DogelandSync v" + getPluginMeta().getVersion()
                + " enabled (server-id=" + serverId + ")");
    }

    @Override
    public void onDisable() {
        if (actionQueue != null) actionQueue.stop();
        if (periodicTask != null) periodicTask.cancel();
        if (heartbeatTask != null) heartbeatTask.cancel();
        if (statsTask != null) statsTask.cancel();
        if (rconTask != null) rconTask.cancel();
        if (historyPeriodicTask != null) historyPeriodicTask.cancel();
        if (itemLogger != null) itemLogger.stop();
        ConsoleLogAppender.unregister();
        // Snapshot lần cuối cho người đang online (best effort, sync)
        if (sync != null) {
            for (Player p : Bukkit.getOnlinePlayers()) sync.requestSnapshot(p, true);
        }
        if (db != null) db.close();
        getLogger().info("DogelandSync disabled.");
    }

    private void sendHeartbeat() {
        String list = Bukkit.getOnlinePlayers().stream()
                .map(Player::getName)
                .collect(Collectors.joining(","));
        sync.heartbeat(Bukkit.getOnlinePlayers().size(), list, getPluginMeta().getVersion());
    }
}
