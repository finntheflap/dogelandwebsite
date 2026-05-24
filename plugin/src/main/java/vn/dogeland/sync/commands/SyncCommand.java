package vn.dogeland.sync.commands;

import org.bukkit.Bukkit;
import org.bukkit.command.Command;
import org.bukkit.command.CommandExecutor;
import org.bukkit.command.CommandSender;
import org.bukkit.command.TabCompleter;
import org.bukkit.entity.Player;
import org.jetbrains.annotations.NotNull;
import vn.dogeland.sync.sync.SyncService;

import java.util.ArrayList;
import java.util.List;

public final class SyncCommand implements CommandExecutor, TabCompleter {

    private final SyncService sync;

    public SyncCommand(SyncService sync) {
        this.sync = sync;
    }

    @Override
    public boolean onCommand(@NotNull CommandSender sender, @NotNull Command cmd,
                             @NotNull String label, @NotNull String[] args) {
        if (!sender.hasPermission("dogelandsync.admin")) {
            sender.sendMessage("§cKhông có quyền.");
            return true;
        }

        Player target;
        if (args.length == 0) {
            if (!(sender instanceof Player p)) {
                sender.sendMessage("§eDùng: /dgsync <player>");
                return true;
            }
            target = p;
        } else {
            target = Bukkit.getPlayerExact(args[0]);
            if (target == null) {
                sender.sendMessage("§cKhông tìm thấy player online: " + args[0]);
                return true;
            }
        }

        sync.requestSnapshot(target, true);
        sender.sendMessage("§aĐã đẩy snapshot inventory của §e" + target.getName() + "§a lên web.");
        return true;
    }

    @Override
    public List<String> onTabComplete(@NotNull CommandSender sender, @NotNull Command cmd,
                                      @NotNull String alias, @NotNull String[] args) {
        if (args.length == 1) {
            List<String> names = new ArrayList<>();
            String q = args[0].toLowerCase();
            for (Player p : Bukkit.getOnlinePlayers()) {
                if (p.getName().toLowerCase().startsWith(q)) names.add(p.getName());
            }
            return names;
        }
        return List.of();
    }
}
