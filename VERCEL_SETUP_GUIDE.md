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
   - Значение: `https://ваш-проект.supabase.co`
   - Окружения: Production, Preview, Development

2. **SUPABASE_SERVICE_ROLE_KEY**
   - Значение: ваш Service Role Key из Supabase
   - Окружения: Production, Preview, Development

3. **NEXTAUTH_URL**
   - Значение: `https://ваш-проект.vercel.app` (будет доступен после первого деплоя)
   - Окружения: Production, Preview, Development
   - **Примечание**: После первого деплоя обновите на реальный URL

4. **NEXTAUTH_SECRET**
   - Значение: сгенерируйте случайную строку (можно использовать: `openssl rand -base64 32`)
   - Окружения: Production, Preview, Development

5. **GITHUB_CLIENT_ID**
   - Значение: Client ID из GitHub OAuth App
   - Окружения: Production, Preview, Development

6. **GITHUB_CLIENT_SECRET**
   - Значение: Client Secret из GitHub OAuth App
   - Окружения: Production, Preview, Development

7. **ADMIN_EMAILS**
   - Значение: `ваш-email@gmail.com,другой-email@gmail.com` (через запятую)
   - Окружения: Production, Preview, Development

8. **WEBHOOK_URL**
   - Значение: `https://ваш-домен.com/api/webhook.php` (URL вашего PHP API на Hostinger)
   - Окружения: Production, Preview, Development

9. **WEBHOOK_SECRET**
   - Значение: тот же секрет, что используется в `.env` на Hostinger
   - Окружения: Production, Preview, Development

### Шаг 4: Настройка GitHub OAuth App

Перед деплоем нужно создать GitHub OAuth App:

1. Перейдите на [GitHub Settings → Developer settings → OAuth Apps](https://github.com/settings/developers)
2. Нажмите **"New OAuth App"**
3. Заполните форму:
   - **Application name**: `ADX Finance Admin`
   - **Homepage URL**: `https://ваш-проект.vercel.app` (обновите после первого деплоя)
   - **Authorization callback URL**: `https://ваш-проект.vercel.app/api/auth/callback/github` (обновите после первого деплоя)
4. Нажмите **"Register application"**
5. Скопируйте **Client ID**
6. Нажмите **"Generate a new client secret"** и скопируйте **Client Secret**

**Важно**: После первого деплоя обновите Homepage URL и Authorization callback URL на реальный URL Vercel.

### Шаг 5: Деплой

1. После добавления всех переменных окружения нажмите **"Deploy"**
2. Дождитесь завершения деплоя (обычно 2-3 минуты)
3. После деплоя Vercel предоставит URL вида: `https://ваш-проект.vercel.app`

### Шаг 6: Обновление настроек после первого деплоя

После первого деплоя:

1. Обновите `NEXTAUTH_URL` в переменных окружения Vercel на реальный URL
2. Обновите GitHub OAuth App:
   - Homepage URL: `https://ваш-проект.vercel.app`
   - Authorization callback URL: `https://ваш-проект.vercel.app/api/auth/callback/github`
3. Передеплойте проект (или подождите автоматического деплоя)

### Шаг 7: Проверка

1. Откройте URL вашего деплоя
2. Должна открыться страница входа в админ панель
3. Попробуйте войти через GitHub (используя email из `ADMIN_EMAILS`)
4. Проверьте, что админ панель работает

## Генерация NEXTAUTH_SECRET

Если нужно сгенерировать секретный ключ:

**Windows (PowerShell):**
```powershell
[Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes([System.Guid]::NewGuid().ToString() + [System.Guid]::NewGuid().ToString()))
```

**Linux/Mac:**
```bash
openssl rand -base64 32
```

**Или онлайн:** https://generate-secret.vercel.app/32

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
