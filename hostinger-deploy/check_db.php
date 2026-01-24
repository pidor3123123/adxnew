<?php
/**
 * Скрипт проверки подключения к базе данных
 * 
 * ВАЖНО: Удалите этот файл после проверки подключения!
 */

// Показываем ошибки только для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Проверка подключения к БД</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .success {
            color: #4CAF50;
            background: #e8f5e9;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .error {
            color: #f44336;
            background: #ffebee;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .info {
            color: #2196F3;
            background: #e3f2fd;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .warning {
            color: #ff9800;
            background: #fff3e0;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .section {
            margin: 20px 0;
            padding: 15px;
            background: #fafafa;
            border-left: 4px solid #2196F3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Проверка конфигурации ADX Finance</h1>
        
        <?php
        $errors = [];
        $warnings = [];
        $success = [];
        
        // Проверка 1: Файл конфигурации
        echo '<div class="section">';
        echo '<h2>1. Проверка файла конфигурации</h2>';
        
        if (file_exists(__DIR__ . '/config/database.php')) {
            require_once __DIR__ . '/config/database.php';
            $success[] = 'Файл config/database.php найден';
            echo '<div class="success">✓ Файл config/database.php найден</div>';
        } else {
            $errors[] = 'Файл config/database.php не найден';
            echo '<div class="error">✗ Файл config/database.php не найден</div>';
        }
        echo '</div>';
        
        // Проверка 2: Переменные окружения
        echo '<div class="section">';
        echo '<h2>2. Проверка переменных окружения</h2>';
        
        $requiredVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
        $missingVars = [];
        
        foreach ($requiredVars as $var) {
            $value = getenv($var);
            if (empty($value)) {
                $missingVars[] = $var;
                echo '<div class="error">✗ Переменная <code>' . $var . '</code> не установлена</div>';
            } else {
                // Скрываем пароль
                $displayValue = ($var === 'DB_PASS') ? '***скрыто***' : $value;
                echo '<div class="success">✓ <code>' . $var . '</code> = ' . htmlspecialchars($displayValue) . '</div>';
            }
        }
        
        if (!empty($missingVars)) {
            $errors[] = 'Не установлены переменные окружения: ' . implode(', ', $missingVars);
            echo '<div class="warning">⚠ Убедитесь, что файл .env создан и содержит все необходимые переменные</div>';
        }
        echo '</div>';
        
        // Проверка 3: Подключение к базе данных
        echo '<div class="section">';
        echo '<h2>3. Проверка подключения к базе данных</h2>';
        
        if (empty($missingVars)) {
            try {
                $db = getDB();
                $success[] = 'Подключение к базе данных успешно';
                echo '<div class="success">✓ Подключение к базе данных успешно</div>';
                
                // Проверка таблиц
                $stmt = $db->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $requiredTables = ['users', 'balances', 'orders', 'transactions', 'assets', 'markets'];
                $missingTables = [];
                
                foreach ($requiredTables as $table) {
                    if (in_array($table, $tables)) {
                        echo '<div class="success">✓ Таблица <code>' . $table . '</code> существует</div>';
                    } else {
                        $missingTables[] = $table;
                        echo '<div class="error">✗ Таблица <code>' . $table . '</code> не найдена</div>';
                    }
                }
                
                if (!empty($missingTables)) {
                    $errors[] = 'Отсутствуют таблицы: ' . implode(', ', $missingTables);
                    echo '<div class="warning">⚠ Необходимо импортировать database.sql в базу данных</div>';
                } else {
                    $success[] = 'Все необходимые таблицы существуют';
                }
                
                // Проверка данных
                $stmt = $db->query("SELECT COUNT(*) as count FROM users");
                $userCount = $stmt->fetch()['count'];
                echo '<div class="info">ℹ Пользователей в базе: <code>' . $userCount . '</code></div>';
                
                $stmt = $db->query("SELECT COUNT(*) as count FROM assets");
                $assetCount = $stmt->fetch()['count'];
                echo '<div class="info">ℹ Активов в базе: <code>' . $assetCount . '</code></div>';
                
            } catch (PDOException $e) {
                $errors[] = 'Ошибка подключения к БД: ' . $e->getMessage();
                echo '<div class="error">✗ Ошибка подключения: ' . htmlspecialchars($e->getMessage()) . '</div>';
                echo '<div class="warning">⚠ Проверьте правильность данных подключения в .env файле</div>';
            }
        } else {
            echo '<div class="warning">⚠ Пропущено: не установлены переменные окружения</div>';
        }
        echo '</div>';
        
        // Проверка 4: Файлы API
        echo '<div class="section">';
        echo '<h2>4. Проверка файлов API</h2>';
        
        $apiFiles = [
            'api/auth.php',
            'api/trading.php',
            'api/wallet.php',
            'api/market.php',
            'api/user.php',
            'api/webhook.php'
        ];
        
        foreach ($apiFiles as $file) {
            if (file_exists(__DIR__ . '/' . $file)) {
                echo '<div class="success">✓ ' . $file . '</div>';
            } else {
                $errors[] = 'Файл не найден: ' . $file;
                echo '<div class="error">✗ ' . $file . ' не найден</div>';
            }
        }
        echo '</div>';
        
        // Итоговый результат
        echo '<div class="section">';
        echo '<h2>📊 Итоговый результат</h2>';
        
        if (empty($errors)) {
            echo '<div class="success" style="font-size: 18px; font-weight: bold;">';
            echo '✅ Все проверки пройдены успешно!';
            echo '</div>';
            echo '<div class="info" style="margin-top: 15px;">';
            echo '<strong>Важно:</strong> Удалите файл <code>check_db.php</code> с сервера после проверки!';
            echo '</div>';
        } else {
            echo '<div class="error" style="font-size: 18px; font-weight: bold;">';
            echo '❌ Обнаружены проблемы:';
            echo '</div>';
            echo '<ul>';
            foreach ($errors as $error) {
                echo '<li style="color: #f44336; margin: 5px 0;">' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
        ?>
        
        <div class="section">
            <h2>📝 Следующие шаги</h2>
            <ol>
                <li>Если есть ошибки - исправьте их согласно сообщениям выше</li>
                <li>Убедитесь, что файл <code>.env</code> создан и заполнен</li>
                <li>Импортируйте <code>database.sql</code> в базу данных через phpMyAdmin</li>
                <li>Проверьте права доступа к файлам (644 для файлов, 755 для папок)</li>
                <li><strong>Удалите этот файл (check_db.php) с сервера!</strong></li>
            </ol>
        </div>
    </div>
</body>
</html>
