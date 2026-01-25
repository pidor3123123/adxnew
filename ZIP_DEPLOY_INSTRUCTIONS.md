# Готовый ZIP файл для деплоя на Hostinger

## Файл: `adx-finance-hostinger.zip`

Этот ZIP архив содержит все необходимые файлы для деплоя PHP API и фронтенда на Hostinger.

## Что внутри:

- ✅ Все PHP API файлы (`api/`)
- ✅ Конфигурационные файлы (`config/`)
- ✅ HTML страницы (index.html, login.html, register.html и т.д.)
- ✅ CSS и JavaScript файлы
- ✅ База данных SQL (`database.sql`)
- ✅ Файл `.htaccess` для Apache
- ✅ Скрипты установки (`setup.php`, `check_db.php`)
- ✅ Документация

## Как использовать:

### Шаг 1: Загрузка на Hostinger

1. Войдите в панель Hostinger (hPanel)
2. Перейдите в **"Файлы"** → **"File Manager"**
3. Откройте папку `public_html`
4. Нажмите **"Загрузить"** (Upload)
5. Выберите файл `adx-finance-hostinger.zip`
6. Дождитесь загрузки
7. Выделите ZIP файл и нажмите **"Распаковать"** (Extract)
8. Удалите ZIP файл после распаковки

### Шаг 2: Настройка базы данных

1. Создайте базу данных MySQL в панели Hostinger
2. Импортируйте `database.sql` через phpMyAdmin
3. Запишите данные подключения

### Шаг 3: Создание .env файла

1. В File Manager создайте файл `.env` в корне `public_html`
2. Скопируйте содержимое из `env.example.txt` (в корне проекта)
3. Заполните реальными данными:

```env
DB_HOST=localhost
DB_NAME=ваше_имя_базы_данных
DB_USER=ваш_пользователь_базы_данных
DB_PASS=ваш_пароль_базы_данных
DB_CHARSET=utf8mb4

SUPABASE_URL=https://ваш-проект.supabase.co
SUPABASE_SERVICE_ROLE_KEY=ваш_ключ_supabase

WEBHOOK_URL=https://adx.finance/api/webhook.php
WEBHOOK_SECRET=сгенерируйте_сложный_секретный_ключ

ALLOWED_ORIGINS=https://adx.finance,https://adx.finance/admin
```

### Шаг 4: Проверка

1. Откройте `https://adx.finance/check_db.php`
2. Проверьте, что все проверки успешны
3. **ВАЖНО**: После проверки удалите `check_db.php`!

### Шаг 5: Проверка работы сайта

1. Откройте `https://adx.finance`
2. Попробуйте зарегистрироваться
3. Проверьте работу API

## Важные замечания:

- ⚠️ Убедитесь, что PHP версия 7.4 или выше
- ⚠️ Установите права доступа: файлы 644, папки 755
- ⚠️ Файл `.env` должен иметь права 600 (только владелец)
- ⚠️ После проверки удалите `check_db.php` с сервера

## Что дальше?

После успешного деплоя PHP части:
1. Задеплойте админ панель на Vercel (см. `VERCEL_SETUP_GUIDE.md`)
2. Настройте админ панель на `https://adx.finance/admin` (см. `GITHUB_HOSTINGER_INTEGRATION.md`)

## Обновление файлов

Для обновления файлов на Hostinger:

1. **Вручную**: Загрузите новые файлы через File Manager или FTP
2. **Через GitHub Actions**: Настройте автоматический деплой (см. `GITHUB_HOSTINGER_INTEGRATION.md`)

## Готово!

После выполнения всех шагов ваш сайт будет работать на `https://adx.finance`
