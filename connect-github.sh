#!/bin/bash
# Скрипт для подключения локального репозитория к GitHub
# Использование: ./connect-github.sh

echo "========================================"
echo "Подключение к GitHub"
echo "========================================"
echo ""

read -p "Введите ваш GitHub username: " GITHUB_USERNAME
read -p "Введите название репозитория (например, adx-finance): " REPO_NAME

echo ""
echo "Подключение к https://github.com/$GITHUB_USERNAME/$REPO_NAME.git"
echo ""

git remote add origin https://github.com/$GITHUB_USERNAME/$REPO_NAME.git

if [ $? -eq 0 ]; then
    echo "✓ Remote origin добавлен успешно"
    echo ""
    echo "Отправка кода на GitHub..."
    git push -u origin main
    
    if [ $? -eq 0 ]; then
        echo ""
        echo "========================================"
        echo "✓ Успешно! Код загружен на GitHub"
        echo "========================================"
        echo ""
        echo "Теперь переходите к деплою на Vercel:"
        echo "См. файл VERCEL_SETUP_GUIDE.md"
    else
        echo ""
        echo "✗ Ошибка при отправке кода"
        echo "Проверьте, что репозиторий создан на GitHub и у вас есть права доступа"
    fi
else
    echo ""
    echo "✗ Ошибка: Remote origin уже существует"
    echo "Если нужно изменить URL, выполните:"
    echo "  git remote set-url origin https://github.com/$GITHUB_USERNAME/$REPO_NAME.git"
    echo "  git push -u origin main"
fi
