# Create deployment ZIP archive for ADX Finance
# Includes all necessary files for the website

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$zipName = "adxfinance-mobile-adaptive-$timestamp.zip"
$tempDir = "deploy_temp"

Write-Host "Creating deployment ZIP archive..." -ForegroundColor Cyan
Write-Host "File name: $zipName" -ForegroundColor Yellow

# Remove old temp directory if exists
if (Test-Path $tempDir) {
    Remove-Item $tempDir -Recurse -Force
}

# Create temp directory
New-Item -ItemType Directory -Path $tempDir | Out-Null

Write-Host "`nCopying files..." -ForegroundColor Cyan

# HTML files (main pages)
$htmlFiles = @(
    "index.html",
    "login.html",
    "register.html",
    "trade.html",
    "wallet.html",
    "portfolio.html",
    "positions.html",
    "profile.html",
    "history.html"
)

Write-Host "  - HTML files..." -ForegroundColor Gray
foreach ($file in $htmlFiles) {
    if (Test-Path $file) {
        Copy-Item $file -Destination $tempDir -Force
        Write-Host "    OK $file" -ForegroundColor Green
    }
}

# CSS files
Write-Host "  - CSS files..." -ForegroundColor Gray
if (Test-Path "css") {
    Copy-Item "css" -Destination $tempDir -Recurse -Force
    Write-Host "    OK css/" -ForegroundColor Green
}

# JS files
Write-Host "  - JS files..." -ForegroundColor Gray
if (Test-Path "js") {
    Copy-Item "js" -Destination $tempDir -Recurse -Force
    Write-Host "    OK js/" -ForegroundColor Green
}

# API files
Write-Host "  - API files..." -ForegroundColor Gray
if (Test-Path "api") {
    Copy-Item "api" -Destination $tempDir -Recurse -Force
    Write-Host "    OK api/" -ForegroundColor Green
}

# Config files
Write-Host "  - Config files..." -ForegroundColor Gray
$configDirs = @("config", "lib")
foreach ($dir in $configDirs) {
    if (Test-Path $dir) {
        Copy-Item $dir -Destination $tempDir -Recurse -Force
        Write-Host "    OK $dir/" -ForegroundColor Green
    }
}

# Resources
Write-Host "  - Resources..." -ForegroundColor Gray
$resourceDirs = @("assets", "components", "public", "app")
foreach ($dir in $resourceDirs) {
    if (Test-Path $dir) {
        Copy-Item $dir -Destination $tempDir -Recurse -Force
        Write-Host "    OK $dir/" -ForegroundColor Green
    }
}

# PHP files
Write-Host "  - PHP files..." -ForegroundColor Gray
$phpFiles = @("setup.php", "check_health.php")
foreach ($file in $phpFiles) {
    if (Test-Path $file) {
        Copy-Item $file -Destination $tempDir -Force
        Write-Host "    OK $file" -ForegroundColor Green
    }
}

# SQL files
Write-Host "  - SQL files..." -ForegroundColor Gray
$sqlFiles = @("database.sql", "supabase_wallet_schema.sql", "supabase_trigger.sql")
foreach ($file in $sqlFiles) {
    if (Test-Path $file) {
        Copy-Item $file -Destination $tempDir -Force
        Write-Host "    OK $file" -ForegroundColor Green
    }
}

# Remove old ZIP if exists
if (Test-Path $zipName) {
    Remove-Item $zipName -Force
    Write-Host "`nRemoved old ZIP file" -ForegroundColor Yellow
}

# Create ZIP archive
Write-Host "`nCreating ZIP archive..." -ForegroundColor Cyan
Compress-Archive -Path "$tempDir\*" -DestinationPath $zipName -Force

if (Test-Path $zipName) {
    $file = Get-Item $zipName
    $sizeMB = [math]::Round($file.Length / 1MB, 2)
    $sizeKB = [math]::Round($file.Length / 1KB, 2)
    
    Write-Host "`nSUCCESS: ZIP archive created!" -ForegroundColor Green
    Write-Host "  Name: $($file.Name)" -ForegroundColor White
    Write-Host "  Size: $sizeMB MB ($sizeKB KB)" -ForegroundColor White
    Write-Host "  Path: $($file.FullName)" -ForegroundColor White
    
    # Show contents
    Write-Host "`nArchive contents (first 20 files):" -ForegroundColor Cyan
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip = [System.IO.Compression.ZipFile]::OpenRead($file.FullName)
    $entries = $zip.Entries | Select-Object -First 20 FullName
    foreach ($entry in $entries) {
        Write-Host "  - $($entry.FullName)" -ForegroundColor Gray
    }
    if ($zip.Entries.Count -gt 20) {
        Write-Host "  ... and $($zip.Entries.Count - 20) more files" -ForegroundColor Gray
    }
    $zip.Dispose()
} else {
    Write-Host "`nERROR: Failed to create ZIP archive!" -ForegroundColor Red
    exit 1
}

# Cleanup temp directory
Write-Host "`nCleaning up..." -ForegroundColor Cyan
Remove-Item $tempDir -Recurse -Force

Write-Host "`nDONE! ZIP archive is ready for deployment." -ForegroundColor Green
