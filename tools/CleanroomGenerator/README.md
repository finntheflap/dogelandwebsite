# CleanroomGenerator — Paper 1.21 Port

Fork of [nvx/CleanroomGenerator](https://github.com/nvx/CleanroomGenerator) (v1.2.1, last updated Jan 2022) updated for **Paper 1.21** + Java 21.

## Changes vs upstream

- `pom.xml` → Paper API `1.21.1-R0.1-SNAPSHOT`, Java 21, papermc Maven repo
- `plugin.yml` → `api-version: '1.21'`
- `CleanroomChunkGenerator`:
  - Implements modern `generateNoise(WorldInfo, Random, int, int, ChunkData)` (1.17+ ChunkGenerator)
  - Overrides all `shouldGenerate*` to disable vanilla noise/surface/caves/decoration/structures (kept `shouldGenerateMobs=true`)
  - Respects `WorldInfo.getMinHeight()` / `getMaxHeight()` — handles -64..320 in 1.21 overworld
  - `^` prefix now starts layers at world `minHeight` (was hard-coded `-64`)
  - Legacy `generateChunkData()` kept as safety fallback with the same height-aware logic
  - `getFixedSpawnLocation()` returns the block ABOVE highest block + half-block offset so players don't suffocate

## Build

```pwsh
$env:JAVA_HOME = "C:\Tools\jdk-21.0.5+11"
$env:Path = "$env:JAVA_HOME\bin;C:\Tools\apache-maven-3.9.6\bin;" + $env:Path
mvn -f tools\CleanroomGenerator\pom.xml clean package
```

Output: `tools/CleanroomGenerator/target/CleanroomGenerator-1.3.0-paper1.21.jar`

## ID format (unchanged)

```
[.][^]layer|block[|layer|block...]
```

| Prefix | Meaning |
| --- | --- |
| `.` | Skip default bedrock layer at y=0 |
| `^` | Start generating at `world.minHeight` (-64 on overworld) |
| `.` (alone) | Void world |

Examples:

| ID | Result |
| --- | --- |
| `64\|stone` | Bedrock + 64 stone (default if id empty) |
| `.64\|stone` | 64 stone, no bedrock |
| `^.1\|bedrock\|384\|air` | Bedrock at y=-64, air all the way up — clean void with floor |
| `.` | Pure void |
| `1\|bedrock\|3\|dirt\|1\|grass_block` | Tiny flatworld |

## bukkit.yml usage

```yaml
worlds:
  flatworld:
    generator: CleanroomGenerator:.64|stone
```
