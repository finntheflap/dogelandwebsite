# Dogeland Network — MySQL daily backup
# Lưu vào C:\backups\mysql\ với name format: dogeland_<yyyyMMdd_HHmmss>.sql.gz
# Giữ tối đa 30 backup gần nhất, auto-prune.
#
# Schedule (run as Administrator PowerShell once):
#   schtasks /Create /SC DAILY /TN "DogelandMySQLBackup" /TR "PowerShell -ExecutionPolicy Bypass -File C:\xampp\htdocs\dogelandwebsite\tools\backup_mysql.ps1" /ST 03:00 /RU SYSTEM

$ErrorActionPreference = 'Stop'

# ---- Config ----
$MYSQL_DUMP = "C:\xampp\mysql\bin\mysqldump.exe"
$DB_USER    = "root"
$DB_PASS    = ""              # XAMPP root mặc định trống
$DB_NAME    = "minecraft"
$BACKUP_DIR = "C:\backups\mysql"
$KEEP_COUNT = 30              # giữ 30 backup gần nhất (1 tháng nếu daily)

# ---- Prep ----
if (-not (Test-Path $BACKUP_DIR)) {
    New-Item -ItemType Directory -Path $BACKUP_DIR -Force | Out-Null
}
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$sqlFile   = Join-Path $BACKUP_DIR "dogeland_$timestamp.sql"
$gzFile    = "$sqlFile.gz"
$logFile   = Join-Path $BACKUP_DIR "backup.log"

function Write-Log($msg) {
    $line = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $msg"
    Add-Content -Path $logFile -Value $line -Encoding UTF8
    Write-Output $line
}

Write-Log "=== Start backup: $DB_NAME -> $gzFile"

# ---- Dump ----
try {
    if ([string]::IsNullOrEmpty($DB_PASS)) {
        & $MYSQL_DUMP --user=$DB_USER --single-transaction --quick --routines --triggers --default-character-set=utf8mb4 $DB_NAME 2>$null | Out-File -FilePath $sqlFile -Encoding UTF8
    } else {
        & $MYSQL_DUMP --user=$DB_USER --password=$DB_PASS --single-transaction --quick --routines --triggers --default-character-set=utf8mb4 $DB_NAME 2>$null | Out-File -FilePath $sqlFile -Encoding UTF8
    }
    if (-not (Test-Path $sqlFile) -or (Get-Item $sqlFile).Length -lt 1024) {
        Write-Log "ERROR: dump file empty/missing - abort"
        if (Test-Path $sqlFile) { Remove-Item $sqlFile -Force }
        exit 1
    }
    $sqlSize = [math]::Round((Get-Item $sqlFile).Length / 1MB, 2)
    Write-Log "Dump OK: $sqlSize MB"
} catch {
    Write-Log "ERROR during dump: $($_.Exception.Message)"
    exit 1
}

# ---- Gzip ----
try {
    $inFile  = [System.IO.File]::OpenRead($sqlFile)
    $outFile = [System.IO.File]::Create($gzFile)
    $gz = New-Object System.IO.Compression.GzipStream($outFile, [System.IO.Compression.CompressionLevel]::Optimal)
    $inFile.CopyTo($gz)
    $gz.Close(); $outFile.Close(); $inFile.Close()
    Remove-Item $sqlFile -Force
    $gzSize = [math]::Round((Get-Item $gzFile).Length / 1MB, 2)
    $ratio = if ($sqlSize -gt 0) { [math]::Round($gzSize / $sqlSize * 100, 1) } else { 0 }
    Write-Log "Gzipped OK: $gzSize MB ($ratio% of raw)"
} catch {
    Write-Log "ERROR during gzip: $($_.Exception.Message)"
    exit 1
}

# ---- Prune old backups (keep KEEP_COUNT newest) ----
try {
    $allBackups = Get-ChildItem -Path $BACKUP_DIR -Filter "dogeland_*.sql.gz" | Sort-Object Name -Descending
    if ($allBackups.Count -gt $KEEP_COUNT) {
        $toDelete = $allBackups | Select-Object -Skip $KEEP_COUNT
        foreach ($f in $toDelete) {
            Remove-Item $f.FullName -Force
            Write-Log "Pruned old: $($f.Name)"
        }
    }
    Write-Log "Backups kept: $([Math]::Min($allBackups.Count, $KEEP_COUNT)) / $KEEP_COUNT"
} catch {
    Write-Log "WARN during prune: $($_.Exception.Message)"
}

Write-Log "=== Done"
exit 0
