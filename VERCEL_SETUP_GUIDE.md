# Быстрая инструкция по деплою на Vercel

## После того как проект загружен на GitHub

### Шаг 1: Подключение к Vercel

1. Перейдите на [vercel.com](https://vercel.com)
2. Войдите через GitHub (нажмите **"Continue with GitHub"**)
3. Нажмите **"Add New..."** → **"Project"**
4. Найдите и выберите ваш репозиторий `adx-finance` (или как вы его назвали)
5. Если репозиторий не виден, нажмите **"Adjust GitHub App Permissions"** и дайте Vercel доступ к репозиторию

### Шаг 2: Настройка проекта

Vercel автоматически определит Next.js проект. Проверьте настройки:

- **Framework Preset**: Next.js (должен определиться автоматически)
- **Root Directory**: `./` (корень проекта)
- **Build Command**: `npm run build` (по умолчанию)
- **Output Directory**: `.next` (по умолчанию)

### Шаг 3: Добавление переменных окружения

**ВАЖНО**: Перед деплоем добавьте все переменные окружения!

Нажмите **"Environment Variables"** и добавьте следующие переменные:

#### Обязательные переменные:

1. **NEXT_PUBLIC_SUPABASE_URL**
   - Значение: `https://teqnsfxvogniblyvsfun.supabase.co`
   - Окружения: Production, Preview, Development
   - ✅ **Просто скопируйте это значение и вставьте**

2. **SUPABASE_SERVICE_ROLE_KEY**
   - Значение: `sb_secret_1n5HZHAYXSXLg5wnanntrA_d_t82MzG`
   - Окружения: Production, Preview, Development
   - ✅ **Просто скопируйте это значение и вставьте**

3. **NEXTAUTH_URL**
   - Значение: `https://admin.adx.finance` (после настройки поддомена)
   - Или временно: `https://ваш-проект.vercel.app` (после первого деплоя)
   - Окружения: Production, Preview, Development
   - ✅ **Просто скопируйте это значение и вставьте**
   - **Примечание**: Используем поддомен `admin.adx.finance`, так как основной сайт уже работает на `adx.finance`

4. **NEXTAUTH_SECRET**
   - Значение: сгенерируйте случайную строку (см. команды ниже)
   - Окружения: Production, Preview, Development
   - **Windows (PowerShell):**
     ```powershell
     [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes([System.Guid]::NewGuid().ToString() + [System.Guid]::NewGuid().ToString()))
     ```
   - **Linux/Mac:**
     ```bash
     openssl rand -base64 32
     ```
   - **Или онлайн:** https://generate-secret.vercel.app/32
   - Скопируйте сгенерированное значение и вставьте

5. **GITHUB_CLIENT_ID**
   - Значение: `Ov23liX30oJJAQWpKXY3`
   - Окружения: Production, Preview, Development
   - ✅ **Просто скопируйте это значение и вставьте**

6. **GITHUB_CLIENT_SECRET**
   - Значение: `ed71ae551cc8a0d5b8bcc7f613c97191f5cbe3cd`
   - Окружения: Production, Preview, Development
   - ✅ **Просто скопируйте это значение и вставьте**

7. **ADMIN_EMAILS**
   - Значение: `gabelatraffick@gmail.com`
   - Окружения: Production, Preview, Development
   - ✅ **Просто скопируйте это значение и вставьте**
   - **Примечание**: Если нужно добавить несколько email, разделите их запятой: `email1@gmail.com,email2@gmail.com`

8. **WEBHOOK_URL**
   - Значение: `https://adx.finance/api/webhook.php`
   - Окружения: Production, Preview, Development
   - ✅ **Просто скопируйте это значение и вставьте**

9. **WEBHOOK_SECRET**
   - Значение: `novatrade-webhook-secret-2024`
   - Окружения: Production, Preview, Development
   - ✅ **Просто скопируйте это значение и вставьте**

### Шаг 4: Проверка GitHub OAuth App

✅ **GitHub OAuth App уже настроен!** Используйте следующие значения:

- **Client ID**: `Ov23liX30oJJAQWpKXY3`
- **Client Secret**: `ed71ae551cc8a0d5b8bcc7f613c97191f5cbe3cd`

**Если нужно обновить настройки OAuth App:**
1. Перейдите на [GitHub Settings → Developer settings → OAuth Apps](https://github.com/settings/developers)
2. Найдите ваше приложение "ADX Finance Admin"
3. Убедитесь, что настройки правильные:
   - **Homepage URL**: `https://admin.adx.finance`
   - **Authorization callback URL**: `https://admin.adx.finance/api/auth/callback/github`
4. Если поддомен еще не настроен, временно используйте URL из Vercel (после первого деплоя)

### Шаг 5: Деплой

1. После добавления всех переменных окружения нажмите **"Deploy"**
2. Дождитесь завершения деплоя (обычно 2-3 минуты)
3. После деплоя Vercel предоставит URL вида: `https://ваш-проект.vercel.app`

**⚠️ ВАЖНО**: После деплоя админ панель будет доступна по:
- Временно: `https://ваш-проект.vercel.app/admin` (до настройки поддомена)
- После настройки поддомена: `https://admin.adx.finance/admin`

### Шаг 6: Подключение поддомена admin.adx.finance

**⚠️ КРИТИЧЕСКИ ВАЖНО**: Так как основной сайт уже работает на `adx.finance`, мы используем поддомен `admin.adx.finance` для админ панели.
Это позволяет обоим сайтам работать независимо друг от друга.

#### 6.1. Создание поддомена в Hostinger

1. Войдите в панель Hostinger (hPanel)
2. Перейдите в **"Домены"** → выберите `adx.finance` → **"Поддомены"**
3. Нажмите **"Создать поддомен"** или **"Добавить поддомен"**
4. Введите имя поддомена: `admin` (без точки и без домена)
5. Нажмите **"Создать"** или **"Добавить"**
6. Поддомен `admin.adx.finance` будет создан

#### 6.2. Подключение поддомена в Vercel

1. В Vercel Dashboard откройте ваш проект
2. Перейдите в **Settings** → **Domains**
3. Нажмите **"Add Domain"** или **"Add"**
4. Введите поддомен: `admin.adx.finance` (полный поддомен)
5. Нажмите **"Add"** или **"Continue"**
6. Vercel покажет инструкции по настройке DNS

#### 6.3. Настройка DNS для поддомена в Hostinger

Vercel покажет, какие DNS записи нужно добавить. Обычно это CNAME запись:

1. В Hostinger → **"Домены"** → `adx.finance` → **"DNS / Зона"**
2. Добавьте новую DNS запись:
   - **Тип**: CNAME
   - **Имя/Хост**: `admin` (только имя поддомена, без точки)
   - **Значение/Указывает на**: значение, которое покажет Vercel (обычно `cname.vercel-dns.com` или похожее)
   - **TTL**: 3600 (или Auto)
3. Нажмите **"Добавить"** или **"Сохранить"**

**Пример:**
```
Тип: CNAME
Имя: admin
Значение: cname.vercel-dns.com
TTL: 3600
```

#### 6.4. Ожидание активации поддомена

После добавления DNS записей:

1. **Вернитесь в Vercel** → Settings → Domains
2. Статус поддомена будет:
   - **"Pending"** - DNS записи добавлены, ожидается проверка
   - **"Configuring"** - Vercel проверяет DNS записи
   - **"Valid Configuration"** ✅ - поддомен готов к использованию!
3. **Время активации**: обычно 5-60 минут, максимум 24 часа
4. Можно проверить статус в реальном времени в Vercel Dashboard

**Если статус долго не меняется:**
- Проверьте, что DNS записи добавлены правильно в Hostinger
- Убедитесь, что имя поддомена `admin` (без точки)
- Подождите еще немного (DNS может распространяться медленно)

### Шаг 7: Обновление настроек после подключения поддомена

После того, как поддомен `admin.adx.finance` активирован (статус "Valid Configuration" в Vercel):

#### 7.1. Обновление переменных окружения в Vercel

1. В Vercel Dashboard → ваш проект → **Settings** → **Environment Variables**
2. Найдите переменную `NEXTAUTH_URL`
3. Нажмите на нее для редактирования
4. Измените значение на: `https://admin.adx.finance`
5. Убедитесь, что выбраны окружения: **Production**, **Preview**, **Development**
6. Нажмите **"Save"**
7. **Передеплойте проект**:
   - Перейдите в **Deployments**
   - Найдите последний деплой
   - Нажмите три точки (⋯) → **Redeploy**
   - Или сделайте новый commit и push в GitHub (автоматический деплой)

#### 7.2. Обновление GitHub OAuth App

1. Перейдите на [GitHub Settings → Developer settings → OAuth Apps](https://github.com/settings/developers)
2. Найдите ваше приложение "ADX Finance Admin" (Client ID: `Ov23liX30oJJAQWpKXY3`)
3. Нажмите на приложение для редактирования
4. Обновите настройки:
   - **Homepage URL**: `https://admin.adx.finance`
   - **Authorization callback URL**: `https://admin.adx.finance/api/auth/callback/github`
5. Нажмите **"Update application"**
6. Готово! Теперь вход через GitHub будет работать с новым поддоменом

### Шаг 8: Проверка работы админ панели

После подключения поддомена и обновления настроек:

1. **Откройте админ панель:**
   - `https://admin.adx.finance/admin` (если поддомен подключен)
   - Или временно: `https://ваш-проект.vercel.app/admin` (если поддомен еще не подключен)
   - ⚠️ **ВАЖНО**: Не забудьте `/admin` в конце URL!

2. **Проверьте страницу входа:**
   - Должна открыться страница входа в админ панель
   - Должна быть кнопка "Sign in with GitHub"

3. **Попробуйте войти:**
   - Нажмите "Sign in with GitHub"
   - Авторизуйтесь через GitHub
   - Используйте email: `gabelatraffick@gmail.com` (должен быть в `ADMIN_EMAILS`)

4. **Проверьте работу админ панели:**
   - После входа должна открыться главная страница админ панели
   - Проверьте навигацию (Users, Balances, Documents и т.д.)
   - Убедитесь, что все ссылки работают корректно

## Генерация NEXTAUTH_SECRET

Если еще не сгенерировали NEXTAUTH_SECRET, используйте одну из команд:

**Windows (PowerShell):**
```powershell
[Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes([System.Guid]::NewGuid().ToString() + [System.Guid]::NewGuid().ToString()))
```

**Linux/Mac:**
```bash
openssl rand -base64 32
```

**Или онлайн:** https://generate-secret.vercel.app/32

Скопируйте сгенерированное значение и вставьте в переменную `NEXTAUTH_SECRET` в Vercel.

## Решение проблем

### Build failed
- Проверьте логи сборки в Vercel
- Убедитесь, что все зависимости в `package.json`
- Проверьте ошибки TypeScript

### Cannot access admin panel
- Проверьте, что ваш email в `ADMIN_EMAILS`
- Убедитесь, что GitHub OAuth настроен правильно
- Проверьте `NEXTAUTH_URL` и `NEXTAUTH_SECRET`

### Проблема: 404 ошибка при открытии админ панели

**Причина:** Админ панель доступна только по пути `/admin`, корень домена/поддомена не работает.

**Решение:**
1. ✅ **Всегда используйте `/admin` в конце URL:**
   - Правильно: `https://admin.adx.finance/admin`
   - Правильно: `https://ваш-проект.vercel.app/admin`
   - Неправильно: `https://admin.adx.finance` (будет редирект на `/admin`)
   - Неправильно: `https://ваш-проект.vercel.app` (будет редирект на `/admin`)

2. ✅ **Проверьте, что поддомен подключен в Vercel:**
   - Settings → Domains → должен быть поддомен `admin.adx.finance` со статусом "Valid Configuration"

3. ✅ **Проверьте переменные окружения:**
   - `NEXTAUTH_URL` должен быть `https://admin.adx.finance` (если поддомен подключен)
   - Или временно `https://ваш-проект.vercel.app` (если поддомен еще не подключен)

4. ✅ **Передеплойте проект после изменений:**
   - В Vercel → Deployments → Redeploy последний деплой
   - Или сделайте новый commit в GitHub

## Готово!

После успешного деплоя у вас будет:
- ✅ Админ панель работает на Vercel
- ✅ Автоматический деплой при каждом push в main
- ✅ Preview деплои для Pull Request'ов
