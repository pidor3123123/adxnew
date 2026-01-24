# Инструкция по настройке синхронизации MySQL ↔ Supabase

## Обзор

Система синхронизации обеспечивает двустороннюю синхронизацию данных между:
- **Основной сайт** (PHP + MySQL) - `monterramarket2.local`
- **Админ панель** (Next.js + Supabase) - `admin-dashboard`

## Настройка переменных окружения

### На основном сайте (PHP)

Добавьте в `config/database.php` или создайте `.env` файл:

```php
// В config/database.php добавьте после существующих констант:
define('SUPABASE_URL', 'https://your-project.supabase.co');
define('SUPABASE_SERVICE_ROLE_KEY', 'your-service-role-key');
define('WEBHOOK_SECRET', 'your-webhook-secret-key');
```

Или через переменные окружения:
```bash
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_SERVICE_ROLE_KEY=your-service-role-key
WEBHOOK_SECRET=your-webhook-secret-key
```

### В админ панели (Next.js)

Добавьте в `.env.local`:

```env
NEXT_PUBLIC_SUPABASE_URL=https://your-project.supabase.co
SUPABASE_SERVICE_ROLE_KEY=your-service-role-key
WEBHOOK_URL=http://monterramarket2.local/api/webhook.php
WEBHOOK_SECRET=your-webhook-secret-key
```

**Важно:** `WEBHOOK_SECRET` должен быть одинаковым на обоих сайтах!

## Где найти ключи Supabase

1. Откройте [Supabase Dashboard](https://app.supabase.com)
2. Выберите ваш проект
3. Перейдите в **Settings** → **API**
4. Скопируйте:
   - **Project URL** → `SUPABASE_URL`
   - **service_role key** → `SUPABASE_SERVICE_ROLE_KEY` (⚠️ используйте осторожно!)

## Первоначальная синхронизация

После настройки переменных окружения выполните массовую синхронизацию существующих данных:

```bash
cd /path/to/monterramarket2.local
php sync_to_supabase.php --all
```

Или по отдельности:
```bash
php sync_to_supabase.php --users      # Только пользователи
php sync_to_supabase.php --balances   # Только балансы
```

## Как работает синхронизация

### 1. Регистрация нового пользователя

```
Основной сайт (MySQL)
  ↓
Создание пользователя в MySQL
  ↓
Автоматическая синхронизация в Supabase (в фоне)
  ↓
Админ панель видит нового пользователя
```

### 2. Изменение баланса

```
Основной сайт (MySQL)
  ↓
Обновление баланса в MySQL
  ↓
Автоматическая синхронизация в Supabase (в фоне)
  ↓
Админ панель видит обновленный баланс
```

### 3. Изменение в админ панели

```
Админ панель (Supabase)
  ↓
Обновление данных в Supabase
  ↓
Отправка webhook на основной сайт
  ↓
Обновление данных в MySQL
```

## Проверка работы синхронизации

### Тест 1: Регистрация пользователя

1. Зарегистрируйте нового пользователя на основном сайте
2. Проверьте в админ панели → Users - должен появиться новый пользователь

### Тест 2: Изменение баланса

1. Пополните баланс пользователя на основном сайте
2. Проверьте в админ панели → Balances - баланс должен обновиться

### Тест 3: Блокировка пользователя

1. Заблокируйте пользователя в админ панели
2. Проверьте на основном сайте - пользователь не должен иметь возможность войти

### Тест 4: Изменение данных пользователя

1. Измените данные пользователя в админ панели
2. Проверьте на основном сайте - данные должны обновиться

## Устранение проблем

### Проблема: Пользователи не синхронизируются

**Решение:**
1. Проверьте переменные окружения `SUPABASE_URL` и `SUPABASE_SERVICE_ROLE_KEY`
2. Проверьте логи PHP на наличие ошибок
3. Запустите массовую синхронизацию: `php sync_to_supabase.php --users`

### Проблема: Балансы не синхронизируются

**Решение:**
1. Проверьте, что синхронизация вызывается после обновления баланса
2. Проверьте логи PHP
3. Запустите массовую синхронизацию: `php sync_to_supabase.php --balances`

### Проблема: Webhook не работает

**Решение:**
1. Проверьте, что `WEBHOOK_URL` в админ панели указывает на правильный адрес
2. Проверьте, что `WEBHOOK_SECRET` одинаковый на обоих сайтах
3. Проверьте доступность `api/webhook.php` с админ панели
4. Проверьте логи PHP на наличие ошибок

### Проблема: Ошибка "Unauthorized" в webhook

**Решение:**
1. Убедитесь, что `WEBHOOK_SECRET` установлен и одинаковый на обоих сайтах
2. Проверьте, что секретный ключ передается в webhook запросе

## Автоматическая синхронизация

Для автоматической синхронизации можно настроить cron:

```bash
# Синхронизация каждые 5 минут
*/5 * * * * cd /path/to/monterramarket2.local && php sync_to_supabase.php --all >> /var/log/sync.log 2>&1
```

## Безопасность

⚠️ **Важные рекомендации:**

1. **Service Role Key** - имеет полный доступ к Supabase, храните в секрете
2. **WEBHOOK_SECRET** - используйте сложный случайный ключ
3. Не коммитьте ключи в Git
4. Используйте HTTPS для webhook в продакшене
5. Ограничьте доступ к `api/webhook.php` по IP (опционально)

## Структура файлов

```
monterramarket2.local/
├── config/
│   ├── database.php          # Конфигурация MySQL
│   └── supabase.php          # Конфигурация Supabase (новый)
├── api/
│   ├── sync.php              # API синхронизации (новый)
│   ├── webhook.php           # Webhook endpoint (новый)
│   ├── auth.php              # Модифицирован для синхронизации
│   ├── wallet.php            # Модифицирован для синхронизации
│   └── trading.php           # Модифицирован для синхронизации
└── sync_to_supabase.php      # Скрипт массовой синхронизации (новый)

admin-dashboard/
└── lib/
    └── supabase-admin.ts     # Модифицирован для отправки webhook
```

## Поддержка

При возникновении проблем:
1. Проверьте логи PHP: `tail -f /var/log/php_errors.log`
2. Проверьте логи Supabase в Dashboard → Logs
3. Проверьте все переменные окружения
4. Убедитесь, что все файлы созданы и доступны
