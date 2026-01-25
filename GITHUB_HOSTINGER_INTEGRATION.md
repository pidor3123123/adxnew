# GitHub интеграция с Hostinger

## Обзор

Hostinger поддерживает несколько способов интеграции с GitHub для автоматического деплоя. Однако, для вашего проекта рекомендуется использовать комбинированный подход.

## Рекомендуемая архитектура

### Разделение компонентов:

1. **PHP API + Frontend** → Hostinger
   - Основной сайт: `https://adx.finance`
   - PHP файлы из папки `hostinger-deploy/`
   - Обновляется вручную или через GitHub Actions

2. **Next.js Админ панель** → Vercel
   - Админ панель: `https://adx.finance/admin`
   - Автоматический деплой из GitHub при каждом push
   - Настроен basePath `/admin` в `next.config.ts`

## Варианты интеграции GitHub с Hostinger

### Вариант 1: GitHub Actions (Рекомендуется)

Создайте файл `.github/workflows/deploy-hostinger.yml`:

```yaml
name: Deploy to Hostinger

on:
  push:
    branches:
      - main
    paths:
      - 'hostinger-deploy/**'
      - 'api/**'
      - '.github/workflows/deploy-hostinger.yml'

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      
      - name: Deploy to Hostinger via FTP
        uses: SamKirkland/FTP-Deploy-Action@4.3.0
        with:
          server: ${{ secrets.HOSTINGER_FTP_HOST }}
          username: ${{ secrets.HOSTINGER_FTP_USER }}
          password: ${{ secrets.HOSTINGER_FTP_PASSWORD }}
          local-dir: ./hostinger-deploy/
          server-dir: /public_html/
          exclude: |
            **/.git*
            **/.git*/**
            **/node_modules/**
            **/README.md
            **/DATABASE_SETUP.md
```

**Настройка секретов в GitHub:**
1. Перейдите в Settings → Secrets and variables → Actions
2. Добавьте:
   - `HOSTINGER_FTP_HOST` - FTP хост (например, `ftp.adx.finance`)
   - `HOSTINGER_FTP_USER` - FTP пользователь
   - `HOSTINGER_FTP_PASSWORD` - FTP пароль

### Вариант 2: Git через SSH (Ручной)

Если у вас есть SSH доступ к Hostinger:

1. **Подключитесь к серверу по SSH:**
```bash
ssh username@your-server.hostinger.com
```

2. **Клонируйте репозиторий:**
```bash
cd ~/domains/adx.finance/public_html
git clone https://github.com/ваш-username/adx-finance.git .
```

3. **Настройте автоматический pull:**
Создайте файл `.git/hooks/post-receive`:
```bash
#!/bin/bash
cd ~/domains/adx.finance/public_html
git pull origin main
```

4. **Сделайте скрипт исполняемым:**
```bash
chmod +x .git/hooks/post-receive
```

5. **Настройте GitHub webhook:**
   - Перейдите в Settings → Webhooks → Add webhook
   - Payload URL: `https://ваш-сервер.hostinger.com/git-pull.php`
   - Content type: `application/json`
   - Events: `Just the push event`

### Вариант 3: Ручное обновление (Простой способ)

1. **Клонируйте репозиторий локально:**
```bash
git clone https://github.com/ваш-username/adx-finance.git
```

2. **После изменений:**
```bash
git pull origin main
cd hostinger-deploy
# Заархивируйте файлы и загрузите через File Manager или FTP
```

## Настройка для работы админ панели на /admin

### 1. Настройка Vercel

После деплоя на Vercel:

1. **Добавьте кастомный домен:**
   - В Vercel: Settings → Domains → Add Domain
   - Введите: `adx.finance`
   - Настройте DNS записи (CNAME на Vercel)

2. **Настройте Rewrite в Vercel:**
Создайте файл `vercel.json` в корне проекта:
```json
{
  "rewrites": [
    {
      "source": "/admin/:path*",
      "destination": "/admin/:path*"
    }
  ]
}
```

### 2. Настройка Hostinger (.htaccess)

Добавьте в `.htaccess` на Hostinger (в корне `public_html`):

```apache
# Проксирование запросов к /admin на Vercel
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Проксирование админ панели на Vercel
    RewriteCond %{REQUEST_URI} ^/admin
    RewriteRule ^admin/(.*)$ https://ваш-проект.vercel.app/admin/$1 [P,L]
    
    # Или если используете кастомный домен на Vercel:
    # RewriteRule ^admin/(.*)$ https://adx.finance/admin/$1 [P,L]
</IfModule>
```

**Важно:** Для работы проксирования нужен модуль `mod_proxy` и `mod_proxy_http` на Hostinger.

### 3. Альтернатива: Поддомен

Если проксирование не работает, используйте поддомен:

1. **Создайте поддомен в Hostinger:**
   - `admin.adx.finance` → указывает на Vercel

2. **Настройте DNS:**
   - CNAME: `admin` → `cname.vercel-dns.com`

3. **Обновите переменные окружения:**
   - `NEXTAUTH_URL=https://admin.adx.finance`
   - Уберите `basePath` из `next.config.ts`

## Переменные окружения для Vercel

После настройки basePath обновите переменные:

```env
NEXTAUTH_URL=https://adx.finance/admin
NEXT_PUBLIC_SUPABASE_URL=https://ваш-проект.supabase.co
SUPABASE_SERVICE_ROLE_KEY=ваш_ключ
GITHUB_CLIENT_ID=ваш_client_id
GITHUB_CLIENT_SECRET=ваш_client_secret
ADMIN_EMAILS=ваш-email@gmail.com
WEBHOOK_URL=https://adx.finance/api/webhook.php
WEBHOOK_SECRET=ваш_секрет
```

## GitHub OAuth App настройка

Обновите GitHub OAuth App:

1. Перейдите в [GitHub Settings → Developer settings → OAuth Apps](https://github.com/settings/developers)
2. Найдите ваше приложение
3. Обновите:
   - **Homepage URL**: `https://adx.finance/admin`
   - **Authorization callback URL**: `https://adx.finance/admin/api/auth/callback/github`

## Автоматический деплой

### Для PHP файлов (Hostinger):

**Через GitHub Actions:**
- Автоматически при push в `main` ветку
- Обновляет только файлы в `hostinger-deploy/`

**Через Git SSH:**
- Автоматически при push через webhook
- Требует SSH доступ

### Для Next.js (Vercel):

- Автоматически при каждом push в `main`
- Настроено по умолчанию при подключении GitHub репозитория

## Проверка работы

После настройки проверьте:

1. ✅ Основной сайт: `https://adx.finance` - работает
2. ✅ Админ панель: `https://adx.finance/admin` - открывается
3. ✅ Вход через GitHub работает
4. ✅ Все ссылки в админ панели работают корректно
5. ✅ API endpoints доступны

## Решение проблем

### Проблема: Админ панель не открывается на /admin

**Решение:**
1. Проверьте, что `basePath: '/admin'` установлен в `next.config.ts`
2. Проверьте настройки проксирования в `.htaccess`
3. Убедитесь, что Vercel проект задеплоен с правильным доменом

### Проблема: GitHub OAuth не работает

**Решение:**
1. Проверьте callback URL в GitHub OAuth App
2. Убедитесь, что `NEXTAUTH_URL` правильный
3. Проверьте `GITHUB_CLIENT_ID` и `GITHUB_CLIENT_SECRET`

### Проблема: Проксирование не работает

**Решение:**
1. Проверьте, включен ли `mod_proxy` на Hostinger
2. Используйте альтернативу с поддоменом
3. Или настройте через Cloudflare (если используется)

## Рекомендации

1. **Используйте GitHub Actions** для автоматического деплоя PHP файлов
2. **Vercel автоматически деплоит** Next.js при каждом push
3. **Используйте поддомен** если проксирование не работает
4. **Храните секреты** в GitHub Secrets и Vercel Environment Variables

## Готово!

Теперь у вас:
- ✅ Автоматический деплой Next.js на Vercel
- ✅ Автоматический деплой PHP на Hostinger (через GitHub Actions)
- ✅ Админ панель доступна на `https://adx.finance/admin`
- ✅ Один репозиторий для всего проекта
