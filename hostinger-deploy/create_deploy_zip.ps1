# ADX Finance - Создание ZIP архива для деплоя на Hostinger
# Запустить из папки hostinger-deploy
# Создаёт adxfinance-hostinger-deploy-YYYYMMDD.zip в родительской папке

$ErrorActionPreference = "Stop"
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$parentDir = Split-Path -Parent $scriptDir
$ts = Get-Date -Format "yyyyMMdd"
$zipName = "adxfinance-hostinger-deploy-$ts.zip"
$zipPath = Join-Path $parentDir $zipName

if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Push-Location $scriptDir
try {
    $items = Get-ChildItem -Path . -Force | Where-Object {
        $_.Name -ne "create_deploy_zip.ps1" -and
        $_.Name -notmatch "\.zip$"
    }
    Compress-Archive -Path $items.FullName -DestinationPath $zipPath -Force
    Write-Host "ZIP создан: $zipPath"
    Write-Host "Размер: $([math]::Round((Get-Item $zipPath).Length / 1KB, 2)) KB"
} finally {
    Pop-Location
}
