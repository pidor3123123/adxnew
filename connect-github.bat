@echo off
REM Скрипт для подключения локального репозитория к GitHub
REM Использование: замените YOUR-USERNAME и YOUR-REPO на реальные значения

echo ========================================
echo Подключение к GitHub
echo ========================================
echo.

set /p GITHUB_USERNAME="Введите ваш GitHub username: "
set /p REPO_NAME="Введите название репозитория (например, adx-finance): "

echo.
echo Подключение к https://github.com/%GITHUB_USERNAME%/%REPO_NAME%.git
echo.

git remote add origin https://github.com/%GITHUB_USERNAME%/%REPO_NAME%.git

if %ERRORLEVEL% EQU 0 (
    echo ✓ Remote origin добавлен успешно
    echo.
    echo Отправка кода на GitHub...
    git push -u origin main
    
    if %ERRORLEVEL% EQU 0 (
        echo.
        echo ========================================
        echo ✓ Успешно! Код загружен на GitHub
        echo ========================================
        echo.
        echo Теперь переходите к деплою на Vercel:
        echo См. файл VERCEL_SETUP_GUIDE.md
    ) else (
        echo.
        echo ✗ Ошибка при отправке кода
        echo Проверьте, что репозиторий создан на GitHub и у вас есть права доступа
    )
) else (
    echo.
    echo ✗ Ошибка: Remote origin уже существует
    echo Если нужно изменить URL, выполните:
    echo   git remote set-url origin https://github.com/%GITHUB_USERNAME%/%REPO_NAME%.git
    echo   git push -u origin main
)

pause
