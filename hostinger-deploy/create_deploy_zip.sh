#!/bin/bash
# ADX Finance - Создание ZIP архива для деплоя на Hostinger
# Запустить из папки hostinger-deploy
# Создаёт adxfinance-hostinger-deploy-YYYYMMDD.zip в родительской папке

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARENT_DIR="$(dirname "$SCRIPT_DIR")"
TS=$(date +%Y%m%d)
ZIP_NAME="adxfinance-hostinger-deploy-$TS.zip"
ZIP_PATH="$PARENT_DIR/$ZIP_NAME"

cd "$SCRIPT_DIR" || exit 1
zip -r "$ZIP_PATH" . -x "create_deploy_zip.ps1" -x "create_deploy_zip.sh" -x "*.zip"

echo "ZIP создан: $ZIP_PATH"
echo "Размер: $(du -h "$ZIP_PATH" | cut -f1)"
