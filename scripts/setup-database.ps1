<#
.SYNOPSIS
  Creates the Itizan PostgreSQL role and databases and writes the matching values into .env.

.DESCRIPTION
  * Connects as a PostgreSQL superuser (asks for the password; it is never stored).
  * Creates a least-privilege role "itizan" (LOGIN only) with a generated password.
  * Creates the databases "itizan" and "itizan_test" owned by that role.
  * Updates DB_* (and a webhook secret if empty) in .env. Existing roles/databases are left untouched.

  Run from the project root:  powershell -ExecutionPolicy Bypass -File scripts/setup-database.ps1
#>
param(
    [string]$PgHost = '127.0.0.1',
    [int]$Port = 5432,
    [string]$SuperUser = 'postgres',
    [string]$PgBin = 'C:\Program Files\PostgreSQL\18\bin',
    [string]$Role = 'itizan',
    [string]$Database = 'itizan',
    [string]$TestDatabase = 'itizan_test'
)

$ErrorActionPreference = 'Stop'
$psql = Join-Path $PgBin 'psql.exe'
if (-not (Test-Path $psql)) { throw "psql not found at $psql. Pass -PgBin with your PostgreSQL bin folder." }

function New-Secret([int]$Bytes) {
    $b = New-Object byte[] $Bytes
    $rng = New-Object Security.Cryptography.RNGCryptoServiceProvider   # works in Windows PowerShell 5.1 and PowerShell 7
    $rng.GetBytes($b)
    ([Convert]::ToBase64String($b) -replace '[^A-Za-z0-9]', '')
}

function Invoke-Psql([string]$Sql, [string]$Db = 'postgres', [string]$User = $SuperUser) {
    & $psql -U $User -h $PgHost -p $Port -d $Db -w -t -A -v ON_ERROR_STOP=1 -c $Sql
    if ($LASTEXITCODE -ne 0) { throw "psql failed: $Sql" }
}

$secure = Read-Host "Password for PostgreSQL user '$SuperUser'" -AsSecureString
$env:PGPASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))

try {
    $roleExists = Invoke-Psql "select 1 from pg_roles where rolname = '$Role'"
    $dbPassword = $null
    if ($roleExists) {
        Write-Host "Role '$Role' already exists: keeping it (DB_PASSWORD in .env is left as is)."
    } else {
        $dbPassword = New-Secret 24
        Invoke-Psql "CREATE ROLE $Role LOGIN PASSWORD '$dbPassword' NOSUPERUSER NOCREATEDB NOCREATEROLE" | Out-Null
        Write-Host "Created role '$Role'."
    }

    foreach ($name in @($Database, $TestDatabase)) {
        if (Invoke-Psql "select 1 from pg_database where datname = '$name'") {
            Write-Host "Database '$name' already exists: keeping it."
        } else {
            Invoke-Psql "CREATE DATABASE $name OWNER $Role ENCODING 'UTF8' TEMPLATE template0" | Out-Null
            Write-Host "Created database '$name'."
        }
    }
} finally {
    $env:PGPASSWORD = $null
}

# Write the connection into .env (create it from the example if needed).
$envPath = Join-Path (Get-Location) '.env'
if (-not (Test-Path $envPath)) { Copy-Item (Join-Path (Get-Location) '.env.example') $envPath }
$content = Get-Content $envPath -Raw
$set = {
    param($text, $key, $value)
    if ($text -match "(?m)^$key=") { $text -replace "(?m)^$key=.*$", "$key=$value" } else { $text + "`n$key=$value" }
}
$content = & $set $content 'DB_CONNECTION' 'pgsql'
$content = & $set $content 'DB_HOST' $PgHost
$content = & $set $content 'DB_PORT' "$Port"
$content = & $set $content 'DB_DATABASE' $Database
$content = & $set $content 'DB_USERNAME' $Role
if ($dbPassword) { $content = & $set $content 'DB_PASSWORD' $dbPassword }
if ($content -notmatch '(?m)^PAYMENT_WEBHOOK_SECRET=.+') { $content = & $set $content 'PAYMENT_WEBHOOK_SECRET' (New-Secret 32) }
[IO.File]::WriteAllText($envPath, $content, (New-Object Text.UTF8Encoding($false)))

Write-Host "`nDone. Next: php artisan migrate --seed"
Write-Host "Tests use the '$TestDatabase' database (see phpunit.xml)."
