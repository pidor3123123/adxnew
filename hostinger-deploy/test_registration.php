<?php
/**
 * ADX Finance - Тестовый скрипт регистрации
 * Используйте этот скрипт для проверки всех этапов регистрации
 */

// Включаем отображение всех ошибок
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тест регистрации - ADX Finance</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
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
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .test-data {
            margin-top: 10px;
            padding: 10px;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <h1>🧪 Тест регистрации ADX Finance</h1>
    
    <?php
    $allPassed = true;
    $testEmail = 'test_' . time() . '@test.com';
    $testPassword = 'TestPassword123!';
    $testFirstName = 'Test';
    $testLastName = 'User';
    $testUserId = null;
    $testToken = null;
    
    // Проверка 1: Загрузка конфигурации базы данных
    echo '<div class="check-item ';
    try {
        require_once __DIR__ . '/config/database.php';
        echo 'success">✅ Конфигурация базы данных загружена успешно';
    } catch (Throwable $e) {
        echo 'error">❌ Ошибка загрузки конфигурации базы данных';
        echo '<div class="error-details">' . htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getTraceAsString()) . '</div>';
        $allPassed = false;
    }
    echo '</div>';
    
    // Проверка 2: Подключение к базе данных
    echo '<div class="check-item ';
    try {
        $db = getDB();
        $db->query('SELECT 1');
        echo 'success">✅ Подключение к базе данных успешно';
    } catch (Throwable $e) {
        echo 'error">❌ Ошибка подключения к базе данных';
        echo '<div class="error-details">' . htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getTraceAsString()) . '</div>';
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
        } else {
            echo 'error">❌ Таблица "users" не существует';
            echo '<div class="error-details">Запустите setup.php для создания таблиц</div>';
            $allPassed = false;
        }
    } catch (Throwable $e) {
        echo 'error">❌ Ошибка проверки таблицы "users"';
        echo '<div class="error-details">' . htmlspecialchars($e->getMessage()) . '</div>';
        $allPassed = false;
    }
    echo '</div>';
    
    // Проверка 4: Загрузка totp.php
    echo '<div class="check-item ';
    try {
        if (file_exists(__DIR__ . '/api/totp.php')) {
            require_once __DIR__ . '/api/totp.php';
            echo 'success">✅ Файл api/totp.php загружен успешно';
        } else {
            echo 'warning">⚠️ Файл api/totp.php не найден (не критично)';
        }
    } catch (Throwable $e) {
        echo 'error">❌ Ошибка загрузки api/totp.php';
        echo '<div class="error-details">' . htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getTraceAsString()) . '</div>';
        $allPassed = false;
    }
    echo '</div>';
    
    // Проверка 5: Загрузка функций из auth.php (ПЕРЕД загрузкой других файлов, которые могут зависеть от них)
    echo '<div class="check-item ';
    try {
        // Загружаем только функции, не выполняем основной код
        $authFile = __DIR__ . '/api/auth.php';
        if (!file_exists($authFile)) {
            throw new Exception('Файл api/auth.php не найден');
        }
        
        // Сохраняем оригинальное значение SCRIPT_FILENAME
        $originalScriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? null;
        
        // Временно меняем SCRIPT_FILENAME, чтобы auth.php не выполнял основной код
        $_SERVER['SCRIPT_FILENAME'] = __FILE__;
        
        // Загружаем файл (основной код не выполнится из-за проверки basename($_SERVER['SCRIPT_FILENAME']))
        require_once $authFile;
        
        // Возвращаем оригинальное значение
        if ($originalScriptFilename !== null) {
            $_SERVER['SCRIPT_FILENAME'] = $originalScriptFilename;
        } else {
            unset($_SERVER['SCRIPT_FILENAME']);
        }
        
        // Проверяем наличие необходимых функций
        $requiredFunctions = ['hashPassword', 'createSession', 'createInitialBalances', 'checkDatabaseConnection', 'checkRequiredTables'];
        $missingFunctions = [];
        foreach ($requiredFunctions as $func) {
            if (!function_exists($func)) {
                $missingFunctions[] = $func;
            }
        }
        
        if (!empty($missingFunctions)) {
            throw new Exception('Отсутствуют функции: ' . implode(', ', $missingFunctions));
        }
        
        echo 'success">✅ Файл api/auth.php загружен, все функции доступны';
    } catch (Throwable $e) {
        echo 'error">❌ Ошибка загрузки или проверки api/auth.php';
        echo '<div class="error-details">' . htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getTraceAsString()) . '</div>';
        $allPassed = false;
    }
    echo '</div>';
    
    // Проверка 6: Загрузка supabase.php (опционально)
    echo '<div class="check-item ';
    try {
        if (file_exists(__DIR__ . '/config/supabase.php')) {
            require_once __DIR__ . '/config/supabase.php';
            echo 'success">✅ Файл config/supabase.php загружен успешно';
        } else {
            echo 'warning">⚠️ Файл config/supabase.php не найден (не критично, Supabase опционален)';
        }
    } catch (Throwable $e) {
        echo 'warning">⚠️ Ошибка загрузки config/supabase.php (не критично)';
        echo '<div class="error-details">' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    echo '</div>';
    
    // Проверка 7: Загрузка sync.php (опционально, но требует auth.php)
    echo '<div class="check-item ';
    try {
        if (file_exists(__DIR__ . '/api/sync.php')) {
            // Сохраняем SCRIPT_FILENAME перед загрузкой sync.php
            $originalScriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? null;
            $_SERVER['SCRIPT_FILENAME'] = __FILE__;
            
            require_once __DIR__ . '/api/sync.php';
            
            // Возвращаем оригинальное значение
            if ($originalScriptFilename !== null) {
                $_SERVER['SCRIPT_FILENAME'] = $originalScriptFilename;
            } else {
                unset($_SERVER['SCRIPT_FILENAME']);
            }
            
            echo 'success">✅ Файл api/sync.php загружен успешно';
        } else {
            echo 'warning">⚠️ Файл api/sync.php не найден (не критично)';
        }
    } catch (Throwable $e) {
        echo 'warning">⚠️ Ошибка загрузки api/sync.php (не критично)';
        echo '<div class="error-details">' . htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getTraceAsString()) . '</div>';
    }
    echo '</div>';
    
    // Проверка 8: Проверка функций checkDatabaseConnection и checkRequiredTables
    echo '<div class="check-item ';
    try {
        if (function_exists('checkDatabaseConnection')) {
            checkDatabaseConnection();
            echo 'success">✅ Функция checkDatabaseConnection() работает корректно';
        } else {
            throw new Exception('Функция checkDatabaseConnection() не найдена');
        }
    } catch (Throwable $e) {
        echo 'error">❌ Ошибка в функции checkDatabaseConnection()';
        echo '<div class="error-details">' . htmlspecialchars($e->getMessage()) . '</div>';
        $allPassed = false;
    }
    echo '</div>';
    
    echo '<div class="check-item ';
    try {
        if (function_exists('checkRequiredTables')) {
            checkRequiredTables();
            echo 'success">✅ Функция checkRequiredTables() работает корректно';
        } else {
            throw new Exception('Функция checkRequiredTables() не найдена');
        }
    } catch (Throwable $e) {
        echo 'error">❌ Ошибка в функции checkRequiredTables()';
        echo '<div class="error-details">' . htmlspecialchars($e->getMessage()) . '</div>';
        $allPassed = false;
    }
    echo '</div>';
    
    // Проверка 9: Тестовая регистрация
    if ($allPassed) {
        echo '<div class="check-item ';
        try {
            $db = getDB();
            
            // Удаляем тестового пользователя, если он существует
            $stmt = $db->prepare('DELETE FROM users WHERE email = ?');
            $stmt->execute([$testEmail]);
            
            // Проверяем, что email не занят
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$testEmail]);
            if ($stmt->fetch()) {
                throw new Exception('Email уже существует (не удалось удалить тестового пользователя)');
            }
            
            // Создаем пользователя
            if (!function_exists('hashPassword')) {
                throw new Exception('Функция hashPassword() не найдена');
            }
            
            $stmt = $db->prepare('
                INSERT INTO users (email, password, first_name, last_name)
                VALUES (?, ?, ?, ?)
            ');
            
            $stmt->execute([
                $testEmail,
                hashPassword($testPassword),
                $testFirstName,
                $testLastName
            ]);
            
            $testUserId = (int) $db->lastInsertId();
            
            if ($testUserId === 0) {
                throw new Exception('Не удалось создать пользователя (lastInsertId вернул 0)');
            }
            
            echo 'success">✅ Тестовая регистрация пользователя прошла успешно';
            echo '<div class="test-data">Email: ' . htmlspecialchars($testEmail) . "\nUser ID: $testUserId</div>";
            
        } catch (Throwable $e) {
            echo 'error">❌ Ошибка при тестовой регистрации пользователя';
            echo '<div class="error-details">' . htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getTraceAsString()) . '</div>';
            $allPassed = false;
        }
        echo '</div>';
        
        // Проверка 10: Создание балансов
        if ($testUserId) {
            echo '<div class="check-item ';
            try {
                if (!function_exists('createInitialBalances')) {
                    throw new Exception('Функция createInitialBalances() не найдена');
                }
                
                createInitialBalances($testUserId);
                
                // Проверяем, что балансы созданы
                $stmt = $db->prepare('SELECT COUNT(*) FROM balances WHERE user_id = ?');
                $stmt->execute([$testUserId]);
                $balanceCount = $stmt->fetchColumn();
                
                if ($balanceCount > 0) {
                    echo 'success">✅ Балансы созданы успешно (' . $balanceCount . ' валют)';
                } else {
                    echo 'warning">⚠️ Балансы не были созданы (функция выполнилась без ошибок, но записи не появились)';
                }
                
            } catch (Throwable $e) {
                echo 'error">❌ Ошибка при создании балансов';
                echo '<div class="error-details">' . htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getTraceAsString()) . '</div>';
                $allPassed = false;
            }
            echo '</div>';
            
            // Проверка 11: Создание сессии
            echo '<div class="check-item ';
            try {
                if (!function_exists('createSession')) {
                    throw new Exception('Функция createSession() не найдена');
                }
                
                $testToken = createSession($testUserId, true);
                
                if (empty($testToken)) {
                    throw new Exception('Токен сессии пустой');
                }
                
                // Проверяем, что сессия создана в БД
                $stmt = $db->prepare('SELECT id FROM user_sessions WHERE user_id = ? AND token = ?');
                $stmt->execute([$testUserId, $testToken]);
                if (!$stmt->fetch()) {
                    throw new Exception('Сессия не найдена в базе данных');
                }
                
                echo 'success">✅ Сессия создана успешно';
                echo '<div class="test-data">Token: ' . htmlspecialchars(substr($testToken, 0, 20)) . '...</div>';
                
            } catch (Throwable $e) {
                echo 'error">❌ Ошибка при создании сессии';
                echo '<div class="error-details">' . htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getTraceAsString()) . '</div>';
                $allPassed = false;
            }
            echo '</div>';
            
            // Очистка: удаляем тестового пользователя
            if ($testUserId) {
                try {
                    $db->prepare('DELETE FROM user_sessions WHERE user_id = ?')->execute([$testUserId]);
                    $db->prepare('DELETE FROM balances WHERE user_id = ?')->execute([$testUserId]);
                    $db->prepare('DELETE FROM users WHERE id = ?')->execute([$testUserId]);
                } catch (Exception $e) {
                    // Игнорируем ошибки очистки
                }
            }
        }
    }
    
    // Итоговый результат
    echo '<div class="check-item ' . ($allPassed ? 'success' : 'error') . '" style="margin-top: 30px; font-size: 18px; font-weight: bold;">';
    if ($allPassed) {
        echo '✅ Все тесты пройдены! Регистрация должна работать корректно.';
    } else {
        echo '❌ Обнаружены проблемы. Исправьте ошибки выше.';
        echo '<div style="margin-top: 15px; font-size: 14px; font-weight: normal;">';
        echo '<strong>Рекомендации:</strong><br>';
        echo '1. Проверьте логи ошибок PHP на сервере (hPanel → Логи → PHP Error Log)<br>';
        echo '2. Убедитесь, что все файлы загружены на сервер<br>';
        echo '3. Проверьте права доступа к файлам (должны быть 644 для файлов, 755 для папок)<br>';
        echo '4. Убедитесь, что таблицы созданы через setup.php';
        echo '</div>';
    }
    echo '</div>';
    ?>
    
    <div style="margin-top: 30px; padding: 15px; background: rgba(99, 102, 241, 0.1); border-radius: 8px; border-left: 4px solid #6366f1;">
        <strong>Информация:</strong><br>
        Этот скрипт проверяет все этапы процесса регистрации.<br>
        После исправления ошибок удалите этот файл с сервера по соображениям безопасности.
    </div>
</body>
</html>
