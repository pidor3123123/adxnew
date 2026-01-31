$ErrorActionPreference = "Stop"
$rootDir = "C:\Users\agyzo\OneDrive\Desktop\nova"
Set-Location $rootDir
$ts = Get-Date -Format "yyyyMMdd-HHmmss"
$zipFile = "adxfinance-wallet-complete-$ts.zip"
$zipPath = Join-Path $rootDir $zipFile
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    function Add-ToZip {
        param($path)
        if (Test-Path $path) {
            if (Test-Path $path -PathType Container) {
                Get-ChildItem -Path $path -Recurse -File | ForEach-Object {
                    $relativePath = $_.FullName.Substring($rootDir.Length + 1)
                    $relativePath = $relativePath.Replace('\', '/')
                    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $relativePath) | Out-Null
                }
            } else {
                $relativePath = $path.Substring($rootDir.Length + 1)
                $relativePath = $relativePath.Replace('\', '/')
                [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $path, $relativePath) | Out-Null
            }
        }
    }
    Write-Host "Adding folders..."
    Add-ToZip (Join-Path $rootDir "api")
    Add-ToZip (Join-Path $rootDir "config")
    Add-ToZip (Join-Path $rootDir "css")
    Add-ToZip (Join-Path $rootDir "js")
    if (Test-Path (Join-Path $rootDir "app")) { Add-ToZip (Join-Path $rootDir "app") }
    Write-Host "Adding HTML files..."
    Get-ChildItem -Path $rootDir -Filter "*.html" -File | ForEach-Object { Add-ToZip $_.FullName }
    Write-Host "Adding SQL files..."
    Get-ChildItem -Path $rootDir -Filter "*.sql" -File | ForEach-Object { Add-ToZip $_.FullName }
    Write-Host "Adding MD files..."
    Get-ChildItem -Path $rootDir -Filter "*.md" -File | ForEach-Object { Add-ToZip $_.FullName }
    Write-Host "Adding individual files..."
    $files = @('.htaccess', 'setup.php', 'diagnose_wallet.php', 'test_wallet_api.php', 'check_health.php', 'diagnose_api.php', 'test_api.php', 'test_wallet_simple.php', 'test_wallet_api_page.html', 'check_server_structure.php')
    foreach ($f in $files) {
        $filePath = Join-Path $rootDir $f
        if (Test-Path $filePath) { Add-ToZip $filePath }
    }
    Write-Host "ZIP created successfully!"
} finally {
    $zip.Dispose()
}
Write-Host ""
Write-Host "Verifying ZIP contents..."
$zipRead = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
try {
    $configFiles = $zipRead.Entries | Where-Object { $_.FullName -like "*config/*" }
    if ($configFiles) {
        Write-Host "Config files found:"
        $configFiles | ForEach-Object { Write-Host "  - $($_.FullName)" }
        Write-Host "Total: $($configFiles.Count) config files"
    } else {
        Write-Host "WARNING: No config files found!"
    }
} finally {
    $zipRead.Dispose()
}
$file = Get-Item $zipPath
Write-Host ""
Write-Host "SUCCESS: Created $($file.Name)"
Write-Host "Size: $([math]::Round($file.Length/1MB, 2)) MB"
Write-Host "Path: $($file.FullName)"
