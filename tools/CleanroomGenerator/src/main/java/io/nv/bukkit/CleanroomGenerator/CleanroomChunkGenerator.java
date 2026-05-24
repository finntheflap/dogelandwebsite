package io.nv.bukkit.CleanroomGenerator;

import org.bukkit.Bukkit;
import org.bukkit.Location;
import org.bukkit.Material;
import org.bukkit.World;
import org.bukkit.block.data.BlockData;
import org.bukkit.generator.ChunkGenerator;
import org.bukkit.generator.WorldInfo;

import java.util.Random;
import java.util.logging.Logger;

/**
 * Flat / layered world chunk generator.
 * <p>
 * ID format: [.][^]layer|block[|layer|block...]
 * <ul>
 *   <li>"." prefix skips the default bedrock layer at y=0 (or y=minHeight if "^").</li>
 *   <li>"^" prefix starts generation at world minHeight (e.g. -64 in 1.21 overworld) instead of y=0.</li>
 *   <li>".." (just a single ".") produces a void world.</li>
 * </ul>
 * Example: "1|minecraft:bedrock|3|minecraft:dirt|1|minecraft:grass_block"
 */
public class CleanroomChunkGenerator extends ChunkGenerator {
    private final Logger log = Logger.getLogger("Minecraft");

    private BlockData[] layerBlock;
    private int[] layerHeight;
    private boolean noBedrock = false;
    private boolean startAtMinHeight = false;

    public CleanroomChunkGenerator() {
        this("");
    }

    CleanroomChunkGenerator(String id) {
        if (id == null || id.isEmpty()) {
            id = "64|stone";
        }

        if (id.equals(".")) {
            // Void world early exit
            layerBlock = new BlockData[0];
            layerHeight = new int[0];
            noBedrock = true;
            return;
        }

        try {
            while (!id.isEmpty() && (id.charAt(0) == '.' || id.charAt(0) == '^')) {
                if (id.charAt(0) == '.') noBedrock = true;
                if (id.charAt(0) == '^') startAtMinHeight = true;
                id = id.substring(1);
            }

            if (!noBedrock) {
                id = "1|minecraft:bedrock|" + id;
            }

            String[] tokens = id.split("\\|");
            if ((tokens.length % 2) != 0) {
                throw new IllegalArgumentException("Odd number of tokens (need pairs of height|block)");
            }

            int layerCount = tokens.length / 2;
            layerBlock = new BlockData[layerCount];
            layerHeight = new int[layerCount];

            for (int i = 0; i < layerCount; i++) {
                int j = i * 2;
                int height;
                try {
                    height = Integer.parseInt(tokens[j]);
                } catch (NumberFormatException nfe) {
                    log.warning("[CleanroomGenerator] Invalid height '" + tokens[j] + "'. Using 64.");
                    height = 64;
                }
                if (height <= 0) {
                    log.warning("[CleanroomGenerator] Non-positive height '" + tokens[j] + "'. Using 64.");
                    height = 64;
                }

                BlockData blockData;
                try {
                    blockData = Bukkit.createBlockData(tokens[j + 1]);
                } catch (Exception e) {
                    log.warning("[CleanroomGenerator] Failed to parse block '" + tokens[j + 1]
                            + "'. Using STONE. Cause: " + e.getMessage());
                    blockData = Material.STONE.createBlockData();
                }

                layerBlock[i] = blockData;
                layerHeight[i] = height;
            }
        } catch (Exception e) {
            log.severe("[CleanroomGenerator] Error parsing id '" + id + "'. Using defaults '1|bedrock|64|stone'. " + e);
            layerBlock = new BlockData[]{
                    Material.BEDROCK.createBlockData(),
                    Material.STONE.createBlockData()
            };
            layerHeight = new int[]{1, 64};
        }
    }

    /* ====================================================================
       Disable all vanilla world-gen stages — we own every block ourselves.
       ==================================================================== */
    @Override public boolean shouldGenerateNoise()         { return false; }
    @Override public boolean shouldGenerateSurface()       { return false; }
    @Override public boolean shouldGenerateBedrock()       { return false; }
    @Override public boolean shouldGenerateCaves()         { return false; }
    @Override public boolean shouldGenerateDecorations()   { return false; }
    @Override public boolean shouldGenerateMobs()          { return true;  } // let mobs spawn normally
    @Override public boolean shouldGenerateStructures()    { return false; }

    /* ====================================================================
       Modern terrain hook (1.17+). Fills layers from worldMinHeight upward.
       For Paper 1.21 overworld, worldInfo.getMinHeight() == -64.
       ==================================================================== */
    @Override
    public void generateNoise(WorldInfo worldInfo, Random random, int chunkX, int chunkZ, ChunkData chunkData) {
        if (layerBlock.length == 0) return; // void

        int minY = worldInfo.getMinHeight();
        int maxY = worldInfo.getMaxHeight();
        int y = startAtMinHeight ? minY : 0;

        for (int i = 0; i < layerBlock.length; i++) {
            int top = y + layerHeight[i];
            if (top > maxY) top = maxY;
            if (y < minY) y = minY;
            if (top > y) {
                chunkData.setRegion(0, y, 0, 16, top, 16, layerBlock[i]);
            }
            y = top;
            if (y >= maxY) break;
        }
    }

    /* ====================================================================
       Legacy fallback. Paper still calls this on some startup paths
       (e.g. ChunkGenerator probes); keep it correct as a safety net.
       ==================================================================== */
    @Override
    public ChunkData generateChunkData(World world, Random random, int chunkX, int chunkZ, BiomeGrid biome) {
        ChunkData chunk = createChunkData(world);
        if (layerBlock.length == 0) return chunk;

        int minY = world.getMinHeight();
        int maxY = world.getMaxHeight();
        int y = startAtMinHeight ? minY : 0;

        for (int i = 0; i < layerBlock.length; i++) {
            int top = y + layerHeight[i];
            if (top > maxY) top = maxY;
            if (y < minY) y = minY;
            if (top > y) chunk.setRegion(0, y, 0, 16, top, 16, layerBlock[i]);
            y = top;
            if (y >= maxY) break;
        }
        return chunk;
    }

    @Override
    public Location getFixedSpawnLocation(World world, Random random) {
        if (!world.isChunkLoaded(0, 0)) {
            world.loadChunk(0, 0);
        }

        int highestBlock = world.getHighestBlockYAt(0, 0);

        // Void or air at spawn → drop the player from y=64 so they don't immediately void out
        if (highestBlock <= world.getMinHeight() && world.getBlockAt(0, 64, 0).getType() == Material.AIR) {
            return new Location(world, 0.5, 64, 0.5);
        }

        return new Location(world, 0.5, highestBlock + 1, 0.5);
    }
}
