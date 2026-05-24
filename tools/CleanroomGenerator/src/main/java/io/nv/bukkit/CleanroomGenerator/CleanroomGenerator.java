package io.nv.bukkit.CleanroomGenerator;

import org.bukkit.generator.ChunkGenerator;
import org.bukkit.plugin.java.JavaPlugin;

public class CleanroomGenerator extends JavaPlugin {
    @Override
    public void onLoad() {
        getLogger().info("CleanroomGenerator loading (Paper 1.21 build, world-gen plugin)...");
    }

    @Override
    public void onEnable() {
        getLogger().info("CleanroomGenerator v" + getPluginMeta().getVersion() + " enabled.");
    }

    @Override
    public ChunkGenerator getDefaultWorldGenerator(String worldName, String id) {
        return new CleanroomChunkGenerator(id);
    }
}
