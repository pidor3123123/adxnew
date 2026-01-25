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
   - Значение: `https://adx.finance/admin`
   - Окружения: Production, Preview, Development
   - ✅ **Просто скопируйте это значение и вставьте**
   - **Примечание**: Если домен еще не настроен, временно используйте `https://ваш-проект.vercel.app/admin` (замените `ваш-проект` на реальное имя проекта из Vercel), а после настройки домена обновите на `https://adx.finance/admin`

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
   - **Homepage URL**: `https://adx.finance/admin`
   - **Authorization callback URL**: `https://adx.finance/admin/api/auth/callback/github`
4. Если домен еще не настроен, временно используйте URL из Vercel (после первого деплоя)

### Шаг 5: Деплой

1. После добавления всех переменных окружения нажмите **"Deploy"**
2. Дождитесь завершения деплоя (обычно 2-3 минуты)
3. После деплоя Vercel предоставит URL вида: `https://ваш-проект.vercel.app`

### Шаг 6: Обновление настроек после первого деплоя

После первого деплоя:

1. Обновите `NEXTAUTH_URL` в переменных окружения Vercel на `https://adx.finance/admin` (после настройки кастомного домена)
2. Обновите GitHub OAuth App:
   - Homepage URL: `https://adx.finance/admin`
   - Authorization callback URL: `https://adx.finance/admin/api/auth/callback/github`
3. Настройте кастомный домен в Vercel (см. `GITHUB_HOSTINGER_INTEGRATION.md`)
4. Передеплойте проект (или подождите автоматического деплоя)

### Шаг 7: Проверка

1. Откройте URL вашего деплоя
2. Должна открыться страница входа в админ панель
3. Попробуйте войти через GitHub (используя email из `ADMIN_EMAILS`)
4. Проверьте, что админ панель работает

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

### Supabase connection error
- Проверьте `NEXT_PUBLIC_SUPABASE_URL` и `SUPABASE_SERVICE_ROLE_KEY`
- Убедитесь, что ключи правильные из Supabase dashboard

## Готово!

После успешного деплоя у вас будет:
- ✅ Админ панель работает на Vercel
- ✅ Автоматический деплой при каждом push в main
- ✅ Preview деплои для Pull Request'ов
