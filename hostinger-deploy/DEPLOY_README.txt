========================================
ADX Finance - Инструкция по деплою на Hostinger
========================================

СОЗДАНИЕ ZIP АРХИВА
-------------------
Перед загрузкой создайте ZIP архив:
- Windows: запустите create_deploy_zip.ps1 (PowerShell)
- Linux/Mac: запустите create_deploy_zip.sh (bash)
Архив adxfinance-hostinger-deploy-YYYYMMDD.zip будет создан в родительской папке.

ЗАГРУЗКА НА HOSTINGER
---------------------
1. Войдите в панель Hostinger (hPanel)
2. Перейдите в "Файлы" -> "File Manager"
3. Откройте папку public_html
4. Загрузите ZIP архив
5. Распакуйте архив (Extract)
6. Удалите ZIP файл после распаковки

НАСТРОЙКА БАЗЫ ДАННЫХ
---------------------
Новая установка:
- Создайте базу данных MySQL в панели Hostinger
- Импортируйте database.sql через phpMyAdmin

Обновление существующей БД:
- Выполните migrate_trading_schema.sql через phpMyAdmin
- При ошибке "Duplicate column" - колонка уже существует, пропустите

НАСТРОЙКА ПЕРЕМЕННЫХ ОКРУЖЕНИЯ
------------------------------
1. Создайте файл .env в корне public_html
2. Скопируйте содержимое из env.example
3. Заполните реальными данными:
   - DB_HOST, DB_NAME, DB_USER, DB_PASS - данные MySQL
   - SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY - если используете админ панель
   - WEBHOOK_URL, WEBHOOK_SECRET - для синхронизации с админкой
   - ADMIN_API_KEY - для api/admin_deposits.php (одобрение депозитов)

ПРОВЕРКА
--------
1. Откройте https://ваш-домен.com - должна загрузиться главная страница
2. Откройте https://ваш-домен.com/api/health.php - должен вернуться JSON с mysql: true
3. После проверки удалите check_db.php с сервера

Подробнее: DATABASE_SETUP.md, HOSTINGER_DEPLOY.md (в корне проекта)
