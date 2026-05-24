package vn.dogeland.sync.tasks;

import org.apache.logging.log4j.Level;
import org.apache.logging.log4j.LogManager;
import org.apache.logging.log4j.core.Filter;
import org.apache.logging.log4j.core.Layout;
import org.apache.logging.log4j.core.LogEvent;
import org.apache.logging.log4j.core.LoggerContext;
import org.apache.logging.log4j.core.appender.AbstractAppender;
import org.apache.logging.log4j.core.config.Configuration;
import org.apache.logging.log4j.core.config.Property;
import org.apache.logging.log4j.core.layout.PatternLayout;
import org.bukkit.Bukkit;
import org.bukkit.scheduler.BukkitTask;
import vn.dogeland.sync.DogelandSync;
import vn.dogeland.sync.db.Database;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.util.ArrayDeque;
import java.util.ArrayList;
import java.util.Deque;
import java.util.List;

/**
 * Log4j2 Appender: bắt MỌI log line trên console MC (vanilla + plugin + chat + command)
 * → buffer in-memory (bounded 1000) → flush async vào web_server_log mỗi 1s.
 *
 * Memory: ~500KB ceiling (1000 lines × ~500 bytes avg). Drop oldest nếu burst overflow.
 */
public final class ConsoleLogAppender extends AbstractAppender {

    private static ConsoleLogAppender instance;
    private static BukkitTask flushTask;

    private final DogelandSync plugin;
    private final Database db;
    private final String serverId;
    private final Deque<LogRow> buffer = new ArrayDeque<>();
    private final Object lock = new Object();
    private static final int MAX_BUFFER = 1000;
    private static final int FLUSH_BATCH = 200;

    private record LogRow(long ts, String level, String source, String message) {}

    private ConsoleLogAppender(DogelandSync plugin, Database db, String serverId) {
        super("DogelandSyncConsole", (Filter) null,
              (Layout<?>) PatternLayout.createDefaultLayout(), false, Property.EMPTY_ARRAY);
        this.plugin = plugin;
        this.db = db;
        this.serverId = serverId;
    }

    public static void register(DogelandSync plugin, Database db, String serverId) {
        if (instance != null) return;
        instance = new ConsoleLogAppender(plugin, db, serverId);
        instance.start();
        LoggerContext ctx = (LoggerContext) LogManager.getContext(false);
        Configuration cfg = ctx.getConfiguration();
        cfg.addAppender(instance);
        cfg.getRootLogger().addAppender(instance, Level.INFO, null);
        ctx.updateLoggers();
        flushTask = Bukkit.getScheduler().runTaskTimerAsynchronously(plugin, instance::flush, 20L, 20L);
        plugin.getLogger().info("ConsoleLogAppender registered for server-id=" + serverId);
    }

    public static void unregister() {
        if (instance == null) return;
        if (flushTask != null) flushTask.cancel();
        try {
            LoggerContext ctx = (LoggerContext) LogManager.getContext(false);
            ctx.getConfiguration().getRootLogger().removeAppender("DogelandSyncConsole");
            ctx.updateLoggers();
        } catch (Exception ignored) {}
        instance.flush();
        instance.stop();
        instance = null;
    }

    @Override
    public void append(LogEvent event) {
        String loggerName = event.getLoggerName() == null ? "" : event.getLoggerName();
        // Filter recursive: skip Hikari pool log + chính plugin DogelandSync log
        if (loggerName.startsWith("com.zaxxer.hikari")) return;
        if (loggerName.contains("DogelandSync") || loggerName.contains("dogelandsync")) {
            String msg = event.getMessage().getFormattedMessage();
            if (msg.contains("ConsoleLog") || msg.contains("flush") || msg.contains("Heartbeat")
                    || msg.contains("Stats") || msg.contains("Synced")) return;
        }

        String level = event.getLevel().toString();
        String source = simplifyLogger(loggerName);
        String message = event.getMessage().getFormattedMessage();
        if (message == null) message = "";
        // Strip ANSI color codes
        message = message.replaceAll("\\u001B\\[[;\\d]*m", "");
        if (message.length() > 2000) message = message.substring(0, 2000) + "...(truncated)";

        synchronized (lock) {
            if (buffer.size() >= MAX_BUFFER) buffer.pollFirst();
            buffer.offerLast(new LogRow(System.currentTimeMillis(), level, source, message));
        }
    }

    /** Bắt thêm chat / commands / system events qua ConsoleChatListener. */
    public static void appendChat(String player, String message) {
        if (instance == null) return;
        synchronized (instance.lock) {
            if (instance.buffer.size() >= MAX_BUFFER) instance.buffer.pollFirst();
            instance.buffer.offerLast(new LogRow(System.currentTimeMillis(), "CHAT", player, "<" + player + "> " + message));
        }
    }
    public static void appendCommand(String player, String command) {
        if (instance == null) return;
        synchronized (instance.lock) {
            if (instance.buffer.size() >= MAX_BUFFER) instance.buffer.pollFirst();
            instance.buffer.offerLast(new LogRow(System.currentTimeMillis(), "COMMAND", player, player + ": /" + command));
        }
    }

    private void flush() {
        List<LogRow> snapshot;
        synchronized (lock) {
            if (buffer.isEmpty()) return;
            int count = Math.min(FLUSH_BATCH, buffer.size());
            snapshot = new ArrayList<>(count);
            for (int i = 0; i < count; i++) snapshot.add(buffer.pollFirst());
        }
        try (Connection c = db.get();
             PreparedStatement ps = c.prepareStatement(
                 "INSERT INTO web_server_log(server_id, level, source, message, created) VALUES(?,?,?,?,?)")) {
            for (LogRow r : snapshot) {
                ps.setString(1, serverId);
                ps.setString(2, r.level);
                ps.setString(3, r.source);
                ps.setString(4, r.message);
                ps.setLong(5, r.ts);
                ps.addBatch();
            }
            ps.executeBatch();
        } catch (Exception e) {
            // Không log qua plugin.getLogger() — sẽ recursive vào chính appender này
            System.err.println("[DogelandSync] Console log flush failed (" + snapshot.size() + " lost): " + e.getMessage());
        }
    }

    private static String simplifyLogger(String name) {
        if (name == null || name.isEmpty()) return "";
        int dot = name.lastIndexOf('.');
        String s = dot >= 0 ? name.substring(dot + 1) : name;
        if (s.length() > 32) s = s.substring(0, 32);
        return s;
    }
}
