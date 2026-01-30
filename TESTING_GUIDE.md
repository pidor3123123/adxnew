# Руководство по тестированию Wallet API

## Предварительные требования

1. SQL схема должна быть выполнена в Supabase
   - Запустите `supabase_wallet_schema.sql` в Supabase SQL Editor
   - Убедитесь, что все таблицы и функции созданы

2. Настройки Supabase должны быть корректными
   - Проверьте `config/supabase.php` - URL и API ключ

## Тестирование через браузер

### 1. Регистрация нового пользователя

1. Откройте https://adx.finance/register.html
2. Заполните форму регистрации:
   - Имя
   - Фамилия
   - Email (уникальный)
   - Пароль (минимум 8 символов)
3. Нажмите "Зарегистрироваться"
4. Проверьте, что регистрация прошла успешно

### 2. Проверка баланса

1. Войдите в систему на https://adx.finance/login.html
2. Перейдите на страницу кошелька: https://adx.finance/wallet.html
3. Проверьте, что баланс отображается (должен быть 0 для нового пользователя)
4. Откройте консоль браузера (F12) и проверьте:
   - Нет ошибок 500
   - Запросы к `/api/wallet.php?action=balances` возвращают JSON
   - Баланс корректно отображается

### 3. Тестирование пополнения (через админ-панель)

1. Войдите в админ-панель: https://admin.adx.finance/
2. Найдите пользователя по email
3. Пополните баланс (например, +100 USD)
4. Проверьте на сайте adx.finance:
   - Баланс обновился
   - Появилось уведомление о пополнении
   - В истории транзакций есть запись

### 4. Тестирование через API напрямую

Используйте Postman или curl для тестирования:

```bash
# Получение балансов (нужен токен авторизации)
curl -X GET "https://adx.finance/api/wallet.php?action=balances" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"

# Пополнение баланса
curl -X POST "https://adx.finance/api/wallet.php?action=deposit" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"currency": "USD", "amount": 100}'

# Получение истории транзакций
curl -X GET "https://adx.finance/api/wallet.php?action=transactions&limit=10" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

## Тестирование через PHP скрипт

1. Запустите `test_wallet_api.php` на сервере:
```bash
php test_wallet_api.php
```

2. Скрипт проверит:
   - Инициализацию Supabase клиента
   - Получение баланса
   - Получение всех балансов
   - Применение транзакции
   - Защиту от double spend
   - Получение истории транзакций
   - Защиту от отрицательного баланса

## Проверка в Supabase

1. Откройте Supabase Dashboard
2. Перейдите в Table Editor
3. Проверьте таблицы:
   - `wallets` - должны быть записи с балансами
   - `transactions` - должны быть записи о транзакциях
4. Проверьте, что:
   - Баланс в `wallets` соответствует сумме транзакций
   - Idempotency keys уникальны
   - Нет отрицательных балансов

## Проверка RPC функций

В Supabase SQL Editor выполните:

```sql
-- Тест получения баланса
SELECT * FROM get_wallet_balance('USER_UUID_HERE', 'USD');

-- Тест применения транзакции
SELECT * FROM apply_transaction(
    'USER_UUID_HERE',
    100.00,
    'admin_topup',
    'USD',
    'test_key_' || extract(epoch from now())::text,
    '{"description": "Test"}'::jsonb
);

-- Тест получения транзакций
SELECT * FROM get_transactions('USER_UUID_HERE', 'USD', 10, 0);
```

## Возможные проблемы и решения

### Ошибка 500 при получении баланса
- Проверьте, что SQL схема выполнена в Supabase
- Проверьте логи сервера для деталей ошибки
- Убедитесь, что пользователь синхронизирован с Supabase

### Баланс не обновляется
- Проверьте, что триггер `trigger_update_wallet_balance` создан
- Проверьте, что RPC функция `apply_transaction` работает
- Проверьте логи Supabase для ошибок

### Ошибка "User not found in Supabase"
- Пользователь должен быть синхронизирован с Supabase
- Проверьте функцию `syncUserToSupabase()` в `api/sync.php`
- Убедитесь, что пользователь существует в таблице `users` в Supabase

## Чек-лист тестирования

- [ ] SQL схема выполнена в Supabase
- [ ] Новый пользователь может зарегистрироваться
- [ ] Баланс отображается на странице кошелька
- [ ] Пополнение через админ-панель работает
- [ ] Уведомления о пополнении появляются
- [ ] История транзакций отображается
- [ ] Защита от double spend работает
- [ ] Защита от отрицательного баланса работает
- [ ] Все RPC функции работают корректно
