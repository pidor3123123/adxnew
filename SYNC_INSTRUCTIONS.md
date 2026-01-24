# Инструкция по синхронизации сайта, БД и админ панели

## Текущая архитектура

### Админ панель
- Использует NextAuth с GitHub провайдером для входа админов
- Использует Supabase для работы с данными пользователей
- Читает данные из таблицы `users` в Supabase

### База данных Supabase
- Таблица `auth.users` - пользователи Supabase Auth
- Таблица `public.users` - пользователи для админ панели
- SQL триггер для автоматической синхронизации

## Что нужно для полной синхронизации

### 1. Установить SQL триггер в Supabase
**Файл:** `supabase_trigger.sql`

**Инструкция:**
1. Откройте Supabase Dashboard → SQL Editor
2. Скопируйте содержимое файла `supabase_trigger.sql`
3. Выполните SQL скрипт
4. Проверьте, что триггер создан: Database → Triggers → `on_auth_user_created`

**Что делает триггер:**
- Автоматически создает запись в `users` при регистрации в `auth.users`
- Автоматически создает запись в `user_security`
- Извлекает данные из `raw_user_meta_data` при регистрации

### 2. Настроить основной сайт для использования Supabase Auth

**Требования:**
- Основной сайт должен использовать Supabase Auth для регистрации пользователей
- При регистрации передавать метаданные (first_name, last_name, country) в `user_metadata`

**Пример кода регистрации на основном сайте:**
```typescript
import { createClient } from '@supabase/supabase-js'

const supabase = createClient(
  process.env.NEXT_PUBLIC_SUPABASE_URL!,
  process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY!
)

const { data, error } = await supabase.auth.signUp({
  email: email,
  password: password,
  options: {
    data: {
      first_name: firstName,
      last_name: lastName,
      country: country,
    }
  }
})
```

**Важно:** Метаданные должны передаваться в `options.data`, чтобы триггер мог их извлечь.

### 3. Синхронизировать существующих пользователей

**Если пользователи были созданы до установки триггера:**
1. Откройте админ панель → Users
2. Нажмите кнопку "Синхронизировать пользователей"
3. Это создаст записи в `users` для всех пользователей из `auth.users`

**Что происходит при синхронизации:**
- Проверяются все пользователи из `auth.users`
- Для каждого пользователя проверяется наличие записи в `users`
- Если записи нет, создается новая запись с данными из `auth.users`
- Также создается запись в `user_security`

### 4. Проверить переменные окружения

**В админ панели должны быть установлены:**
```env
NEXT_PUBLIC_SUPABASE_URL=https://your-project.supabase.co
SUPABASE_SERVICE_ROLE_KEY=your-service-role-key
NEXTAUTH_SECRET=your-nextauth-secret
GITHUB_CLIENT_ID=your-github-client-id
GITHUB_CLIENT_SECRET=your-github-client-secret
```

**На основном сайте должны быть установлены:**
```env
NEXT_PUBLIC_SUPABASE_URL=https://your-project.supabase.co
NEXT_PUBLIC_SUPABASE_ANON_KEY=your-anon-key
```

**Где найти ключи:**
- `NEXT_PUBLIC_SUPABASE_URL` и `NEXT_PUBLIC_SUPABASE_ANON_KEY` - в Supabase Dashboard → Settings → API
- `SUPABASE_SERVICE_ROLE_KEY` - в Supabase Dashboard → Settings → API (Service Role Key, используйте осторожно!)

## Поток синхронизации

### При регистрации нового пользователя на основном сайте:

```
1. Основной сайт
   ↓
   supabase.auth.signUp() с метаданными
   ↓
2. Supabase Auth
   ↓
   Создает пользователя в auth.users
   ↓
3. SQL триггер (on_auth_user_created)
   ↓
   Автоматически создает запись в users
   Автоматически создает запись в user_security
   ↓
4. Админ панель
   ↓
   Читает данные из users
   ↓
   Новый пользователь виден сразу
```

### Если триггер не сработал:

```
1. Админ панель
   ↓
   Кнопка "Синхронизировать пользователей"
   ↓
2. API синхронизации (/api/admin/users/sync)
   ↓
   Проверяет всех пользователей из auth.users
   ↓
3. Для каждого пользователя:
   - Проверяет наличие в users
   - Если нет - создает запись
   - Создает запись в user_security
   ↓
4. Возвращает результат синхронизации
```

## Чеклист для проверки

- [ ] SQL триггер установлен в Supabase
- [ ] Триггер активен (проверить в Database → Triggers)
- [ ] Основной сайт использует Supabase Auth для регистрации
- [ ] При регистрации передаются метаданные (first_name, last_name, country) в `options.data`
- [ ] Переменные окружения настроены на обоих сайтах
- [ ] Существующие пользователи синхронизированы через админ панель
- [ ] Новый пользователь появляется в админ панели после регистрации
- [ ] Метаданные пользователя корректно отображаются в админ панели

## Возможные проблемы и решения

### Проблема: Новый пользователь не появляется в админ панели

**Причины и решения:**

1. **SQL триггер не установлен или не активен**
   - Проверьте в Supabase Dashboard → Database → Triggers
   - Убедитесь, что триггер `on_auth_user_created` существует и активен
   - Если триггера нет, выполните SQL скрипт из `supabase_trigger.sql`

2. **Основной сайт не использует Supabase Auth**
   - Убедитесь, что регистрация происходит через `supabase.auth.signUp()`
   - Проверьте, что используется правильный Supabase клиент

3. **Триггер не сработал (пользователь создан до установки триггера)**
   - Используйте кнопку "Синхронизировать пользователей" в админ панели
   - Это создаст записи для всех пользователей из `auth.users`

4. **Ошибки в логах Supabase**
   - Проверьте логи в Supabase Dashboard → Logs → Postgres Logs
   - Ищите ошибки, связанные с триггером или функцией `handle_new_user()`

### Проблема: Метаданные не сохраняются

**Причины и решения:**

1. **Метаданные не передаются при регистрации**
   - Убедитесь, что при регистрации используется `options.data`
   - Проверьте структуру данных:
     ```typescript
     options: {
       data: {
         first_name: firstName,
         last_name: lastName,
         country: country,
       }
     }
     ```

2. **Триггер неправильно извлекает данные**
   - Проверьте SQL функцию `handle_new_user()` в `supabase_trigger.sql`
   - Убедитесь, что она использует `NEW.raw_user_meta_data->>'first_name'` и т.д.

3. **Структура метаданных не соответствует ожидаемой**
   - Проверьте в Supabase Dashboard → Authentication → Users
   - Откройте пользователя и проверьте поле `raw_user_meta_data`
   - Убедитесь, что данные там есть и в правильном формате

### Проблема: Синхронизация не работает

**Причины и решения:**

1. **Ошибка доступа к Supabase Admin API**
   - Проверьте, что `SUPABASE_SERVICE_ROLE_KEY` установлен правильно
   - Убедитесь, что используется Service Role Key, а не Anon Key

2. **Ошибки при создании записей**
   - Проверьте структуру таблиц `users` и `user_security`
   - Убедитесь, что все обязательные поля заполнены
   - Проверьте ограничения (constraints) в таблицах

3. **Пользователь уже существует**
   - Это нормально - синхронизация пропускает существующих пользователей
   - Проверьте сообщение о результате синхронизации

## Дополнительная информация

### Структура данных

**Таблица `users`:**
- `id` (UUID) - связь с `auth.users.id`
- `email` - email пользователя
- `first_name` - имя
- `last_name` - фамилия
- `country` - страна
- `is_verified` - статус верификации
- `kyc_status` - статус KYC (pending, approved, rejected, under_review)
- `kyc_verified` - KYC верифицирован
- `created_at` - дата создания

**Таблица `user_security`:**
- `user_id` (UUID) - связь с `users.id`
- `two_fa_enabled` - включена ли 2FA
- `two_fa_type` - тип 2FA (sms, email, app)
- `last_login_at` - последний вход
- `last_login_ip` - IP последнего входа
- `failed_login_attempts` - количество неудачных попыток входа
- `account_locked_until` - дата разблокировки аккаунта

### Тестирование синхронизации

1. **Создайте тестового пользователя на основном сайте:**
   ```typescript
   const { data, error } = await supabase.auth.signUp({
     email: 'test@example.com',
     password: 'testpassword123',
     options: {
       data: {
         first_name: 'Test',
         last_name: 'User',
         country: 'US',
       }
     }
   })
   ```

2. **Проверьте в Supabase Dashboard:**
   - Authentication → Users - должен быть новый пользователь
   - Table Editor → users - должна быть новая запись
   - Table Editor → user_security - должна быть новая запись

3. **Проверьте в админ панели:**
   - Users - новый пользователь должен быть виден
   - Детали пользователя должны отображаться корректно

## Поддержка

Если у вас возникли проблемы с синхронизацией:
1. Проверьте все пункты из чеклиста
2. Проверьте логи Supabase на наличие ошибок
3. Используйте функцию синхронизации в админ панели для исправления проблем
4. Проверьте структуру данных в Supabase Dashboard
