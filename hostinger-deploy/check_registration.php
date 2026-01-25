<?php
/**
 * ADX Finance - Диагностический скрипт для проверки регистрации
 * Используйте этот скрипт для проверки всех условий перед регистрацией
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Проверка регистрации - ADX Finance</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #1a1a1f;
            color: #f5f5f7;
        }
        .check-item {
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 4px solid;
        }
        .success {
            background: rgba(34, 197, 94, 0.1);
            border-color: #22c55e;
        }
        .error {
            background: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
        }
        .warning {
            background: rgba(245, 158, 11, 0.1);
            border-color: #f59e0b;
        }
        h1 {
            color: #6366f1;
        }
        .error-details {
            margin-top: 10px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <h1>🔍 Диагностика регистрации ADX Finance</h1>
    
    <?php
    $checks = [];
    $allPassed = true;
    
    // Проверка 1: Загрузка конфигурации базы данных
    echo '<div class="check-item ';
    try {
        require_once __DIR__ . '/config/database.php';
        echo 'success">✅ Конфигурация базы данных загружена успешно';
        $checks[] = ['name' => 'Конфигурация БД', 'status' => true];
    } catch (Exception $e) {
        echo 'error">❌ Ошибка загрузки конфигурации базы данных';
        echo '<div class="error-details">' . htmlspecialchars($e->getMessage()) . '</div>';
        $checks[] = ['name' => 'Конфигурация БД', 'status' => false, 'error' => $e->getMessage()];
        $allPassed = false;
    }
    echo '</div>';
    
    // Проверка 2: Подключение к базе данных
    echo '<div class="check-item ';
    try {
        $db = getDB();
        $db->query('SELECT 1');
        echo 'success">✅ Подключение к базе данных успешно';
        $checks[] = ['name' => 'Подключение к БД', 'status' => true];
    } catch (Exception $e) {
        echo 'error">❌ Ошибка подключения к базе данных';
        echo '<div class="error-details">' . htmlspecialchars($e->getMessage()) . '</div>';
        $checks[] = ['name' => 'Подключение к БД', 'status' => false, 'error' => $e->getMessage()];
        $allPassed = false;
    }
    echo '</div>';
    
    // Проверка 3: Существование таблицы users
    echo '<div class="check-item ';
    try {
        $db = getDB();
        $stmt = $db->prepare("SHOW TABLES LIKE 'users'");
        $stmt->execute();
        if ($stmt->fetch()) {
            echo 'success">✅ Таблица "users" существует';
            $checks[] = ['name' => 'Таблица users', 'status' => true];
        } else {
            echo 'error">❌ Таблица "users" не существует';
            echo '<div class="error-details">Запустите setup.php для создания таблиц</div>';
            $checks[] = ['name' => 'Таблица users', 'status' => false];
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo 'error">❌ Ошибка проверки таблицы "users"';
        echo '<div class="error-details">' . htmlspecialchars($e->getMessage()) . '</div>';
        $checks[] = ['name' => 'Таблица users', 'status' => false, 'error' => $e->getMessage()];
        $allPassed = false;
    }
    echo '</div>';
    
    // Проверка 4: Существование таблицы user_sessions
    echo '<div class="check-item ';
    try {
        $db = getDB();
        $stmt = $db->prepare("SHOW TABLES LIKE 'user_sessions'");
        $stmt->execute();
        if ($stmt->fetch()) {
            echo 'success">✅ Таблица "user_sessions" существует';
            $checks[] = ['name' => 'Таблица user_sessions', 'status' => true];
        } else {
            echo 'error">❌ Таблица "user_sessions" не существует';
            echo '<div class="error-details">Запустите setup.php для создания таблиц</div>';
            $checks[] = ['name' => 'Таблица user_sessions', 'status' => false];
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo 'error">❌ Ошибка проверки таблицы "user_sessions"';
        echo '<div class="error-details">' . htmlspecialchars($e->getMessage()) . '</div>';
        $checks[] = ['name' => 'Таблица user_sessions', 'status' => false, 'error' => $e->getMessage()];
        $allPassed = false;
    }
    echo '</div>';
    
    // Проверка 5: Существование таблицы balances
    echo '<div class="check-item ';
    try {
        $db = getDB();
        $stmt = $db->prepare("SHOW TABLES LIKE 'balances'");
        $stmt->execute();
        if ($stmt->fetch()) {
            echo 'success">✅ Таблица "balances" существует';
            $checks[] = ['name' => 'Таблица balances', 'status' => true];
        } else {
            echo 'error">❌ Таблица "balances" не существует';
            echo '<div class="error-details">Запустите setup.php для создания таблиц</div>';
            $checks[] = ['name' => 'Таблица balances', 'status' => false];
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo 'error">❌ Ошибка проверки таблицы "balances"';
        echo '<div class="error-details">' . htmlspecialchars($e->getMessage()) . '</div>';
        $checks[] = ['name' => 'Таблица balances', 'status' => false, 'error' => $e->getMessage()];
        $allPassed = false;
    }
    echo '</div>';
    
    // Проверка 6: Структура таблицы users
    echo '<div class="check-item ';
    try {
        $db = getDB();
        $stmt = $db->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $requiredColumns = ['id', 'email', 'password', 'first_name', 'last_name'];
        $missingColumns = array_diff($requiredColumns, $columns);
        
        if (empty($missingColumns)) {
            echo 'success">✅ Таблица "users" имеет все необходимые колонки';
            $checks[] = ['name' => 'Структура users', 'status' => true];
        } else {
            echo 'warning">⚠️ В таблице "users" отсутствуют колонки: ' . implode(', ', $missingColumns);
            echo '<div class="error-details">Запустите setup.php для обновления структуры таблиц</div>';
            $checks[] = ['name' => 'Структура users', 'status' => false];
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo 'error">❌ Ошибка проверки структуры таблицы "users"';
        echo '<div class="error-details">' . htmlspecialchars($e->getMessage()) . '</div>';
        $checks[] = ['name' => 'Структура users', 'status' => false, 'error' => $e->getMessage()];
        $allPassed = false;
    }
    echo '</div>';
    
    // Проверка 7: Файл auth.php доступен
    echo '<div class="check-item ';
    if (file_exists(__DIR__ . '/api/auth.php')) {
        if (is_readable(__DIR__ . '/api/auth.php')) {
            echo 'success">✅ Файл api/auth.php доступен для чтения';
            $checks[] = ['name' => 'Файл auth.php', 'status' => true];
        } else {
            echo 'error">❌ Файл api/auth.php недоступен для чтения';
            echo '<div class="error-details">Проверьте права доступа к файлу</div>';
            $checks[] = ['name' => 'Файл auth.php', 'status' => false];
            $allPassed = false;
        }
    } else {
        echo 'error">❌ Файл api/auth.php не найден';
        $checks[] = ['name' => 'Файл auth.php', 'status' => false];
        $allPassed = false;
    }
    echo '</div>';
    
    // Итоговый результат
    echo '<div class="check-item ' . ($allPassed ? 'success' : 'error') . '" style="margin-top: 30px; font-size: 18px; font-weight: bold;">';
    if ($allPassed) {
        echo '✅ Все проверки пройдены! Регистрация должна работать корректно.';
    } else {
        echo '❌ Обнаружены проблемы. Исправьте ошибки выше перед использованием регистрации.';
        echo '<div style="margin-top: 15px; font-size: 14px; font-weight: normal;">';
        echo '<strong>Рекомендации:</strong><br>';
        echo '1. Если таблицы не существуют, запустите setup.php<br>';
        echo '2. Если ошибка подключения к БД, проверьте настройки в config/database.php<br>';
        echo '3. Проверьте логи ошибок PHP на сервере';
        echo '</div>';
    }
    echo '</div>';
    ?>
    
    <div style="margin-top: 30px; padding: 15px; background: rgba(99, 102, 241, 0.1); border-radius: 8px; border-left: 4px solid #6366f1;">
        <strong>Информация:</strong><br>
        Этот скрипт проверяет все необходимые условия для работы регистрации.<br>
        После исправления ошибок удалите этот файл с сервера по соображениям безопасности.
    </div>
</body>
</html>
