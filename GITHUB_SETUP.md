# Инструкция по добавлению проекта на GitHub

## Шаг 1: Создание репозитория на GitHub

1. Перейдите на [GitHub.com](https://github.com) и войдите в свой аккаунт
2. Нажмите на кнопку **"+"** в правом верхнем углу → **"New repository"**
3. Заполните форму:
   - **Repository name**: `adx-finance` (или любое другое имя)
   - **Description**: `ADX Finance - Trading platform with admin panel`
   - **Visibility**: Выберите **Private** (рекомендуется) или **Public**
   - **НЕ** ставьте галочки на "Initialize this repository with README", "Add .gitignore" или "Choose a license" (у нас уже есть эти файлы)
4. Нажмите **"Create repository"**

## Шаг 2: Подключение локального репозитория к GitHub

После создания репозитория GitHub покажет вам инструкции. Выполните следующие команды в терминале:

```bash
git remote add origin https://github.com/ВАШ-USERNAME/ВАШ-РЕПОЗИТОРИЙ.git
git push -u origin main
```

**Важно**: Замените `ВАШ-USERNAME` и `ВАШ-РЕПОЗИТОРИЙ` на реальные значения из URL вашего репозитория.

Например, если ваш репозиторий: `https://github.com/john/adx-finance.git`, то команда будет:
```bash
git remote add origin https://github.com/john/adx-finance.git
git push -u origin main
```

## Шаг 3: Проверка

После выполнения команд проверьте на GitHub, что все файлы загружены.

## Что дальше?

После успешной загрузки на GitHub переходите к деплою на Vercel (см. файл `VERCEL_DEPLOY.md`).
