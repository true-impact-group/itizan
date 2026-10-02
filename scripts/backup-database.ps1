<#
.SYNOPSIS
  Dumps the Itizan PostgreSQL database and archives private uploads (Windows).
.DESCRIPTION
  Creates <BackupDir>\itizan-<timestamp>.dump (pg_dump custom format) and uploads-<timestamp>.zip, then deletes
  backups older than -KeepDays. Reads DB_* from .env. The password is passed through PGPASSWORD for this process only.
  Schedule it with Task Scheduler (daily) and copy the output folder off the machine.
#>
param(
    [string]$BackupDir = 'C:\itizan-backups',
    [int]$KeepDays = 14,
    [string]$PgBin = 'C:\Program Files\PostgreSQL\18\bin'
)
$ErrorActionPreference = 'Stop'
$envMap = @{}
Get-Content .env | Where-Object { $_ -match '^\s*[A-Z_]+=' } | ForEach-Object { $k, $v = $_ -split '=', 2; $envMap[$k.Trim()] = $v.Trim().Trim('"') }

New-Item -ItemType Directory -Force $BackupDir | Out-Null
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$env:PGPASSWORD = $envMap['DB_PASSWORD']
try {
    & (Join-Path $PgBin 'pg_dump.exe') -h $envMap['DB_HOST'] -p $envMap['DB_PORT'] -U $envMap['DB_USERNAME'] -Fc -f "$BackupDir\itizan-$stamp.dump" $envMap['DB_DATABASE']
    if ($LASTEXITCODE -ne 0) { throw 'pg_dump failed' }
} finally { $env:PGPASSWORD = $null }

if (Test-Path storage\app\private) { Compress-Archive -Path storage\app\private\* -DestinationPath "$BackupDir\uploads-$stamp.zip" -Force }
Get-ChildItem $BackupDir -File | Where-Object LastWriteTime -lt (Get-Date).AddDays(-$KeepDays) | Remove-Item -Force
Write-Host "Backup written to $BackupDir ($stamp)"
