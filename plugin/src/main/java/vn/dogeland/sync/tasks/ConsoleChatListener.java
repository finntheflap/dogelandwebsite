package vn.dogeland.sync.tasks;

import io.papermc.paper.event.player.AsyncChatEvent;
import net.kyori.adventure.text.serializer.plain.PlainTextComponentSerializer;
import org.bukkit.event.EventHandler;
import org.bukkit.event.EventPriority;
import org.bukkit.event.Listener;
import org.bukkit.event.player.PlayerCommandPreprocessEvent;
import org.bukkit.event.player.PlayerJoinEvent;
import org.bukkit.event.player.PlayerQuitEvent;
import org.bukkit.event.server.ServerCommandEvent;

/**
 * Bắt chat + command events → đẩy vào ConsoleLogAppender buffer.
 * (Log4j2 appender không catch được chat/command events vì chúng không log qua Log4j.)
 */
public final class ConsoleChatListener implements Listener {

    @EventHandler(priority = EventPriority.MONITOR, ignoreCancelled = true)
    public void onChat(AsyncChatEvent ev) {
        try {
            String msg = PlainTextComponentSerializer.plainText().serialize(ev.message());
            ConsoleLogAppender.appendChat(ev.getPlayer().getName(), msg);
        } catch (Throwable ignored) {}
    }

    @EventHandler(priority = EventPriority.MONITOR, ignoreCancelled = true)
    public void onPlayerCmd(PlayerCommandPreprocessEvent ev) {
        try {
            String cmd = ev.getMessage();
            if (cmd.startsWith("/")) cmd = cmd.substring(1);
            ConsoleLogAppender.appendCommand(ev.getPlayer().getName(), cmd);
        } catch (Throwable ignored) {}
    }

    @EventHandler(priority = EventPriority.MONITOR, ignoreCancelled = true)
    public void onConsoleCmd(ServerCommandEvent ev) {
        try {
            ConsoleLogAppender.appendCommand("CONSOLE", ev.getCommand());
        } catch (Throwable ignored) {}
    }

    @EventHandler(priority = EventPriority.MONITOR)
    public void onJoin(PlayerJoinEvent ev) {
        ConsoleLogAppender.appendChat("SYSTEM", ev.getPlayer().getName() + " joined the game");
    }

    @EventHandler(priority = EventPriority.MONITOR)
    public void onQuit(PlayerQuitEvent ev) {
        ConsoleLogAppender.appendChat("SYSTEM", ev.getPlayer().getName() + " left the game");
    }
}
