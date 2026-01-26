<?php
/**
 * ADX Finance - Тест синхронизации с Supabase
 * Диагностический скрипт для проверки синхронизации пользователей
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тест синхронизации Supabase - ADX Finance</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #1a1a1f;
            color: #fff;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #2a2a32;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }
        h1 {
            color: #6366f1;
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .test-item {
            background: #1e1e24;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 4px solid #6366f1;
        }
        .success {
            border-left-color: #22c55e;
            background: #1a2e1a;
        }
        .error {
            border-left-color: #ef4444;
            background: #2e1a1a;
        }
        .warning {
            border-left-color: #f59e0b;
            background: #2e2a1a;
        }
        .test-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: #e5e7eb;
        }
        .test-message {
            color: #9ca3af;
            font-size: 14px;
        }
        .test-details {
            margin-top: 10px;
            padding: 10px;
            background: #0a0a0f;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #d1d5db;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .summary {
            margin-top: 30px;
            padding: 20px;
            background: #1e1e24;
            border-radius: 8px;
            text-align: center;
        }
        .summary.success {
            background: #1a2e1a;
            border: 2px solid #22c55e;
        }
        .summary.error {
            background: #2e1a1a;
            border: 2px solid #ef4444;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Тест синхронизации Supabase</h1>

<?php
$results = [];
$overallStatus = 'success';

function addResult($test, $status, $message, $details = '') {
    global $results, $overallStatus;
    $results[] = [
        'test' => $test,
        'status' => $status,
        'message' => $message,
        'details' => $details
    ];
    if ($status === 'error') {
        $overallStatus = 'error';
    } elseif ($status === 'warning' && $overallStatus === 'success') {
        $overallStatus = 'warning';
    }
}

// 1. Проверка загрузки конфигурации базы данных
try {
    require_once __DIR__ . '/config/database.php';
    addResult('Загрузка config/database.php', 'success', 'Файл конфигурации базы данных загружен успешно.');
} catch (Throwable $e) {
    addResult('Загрузка config/database.php', 'error', 'Ошибка загрузки: ' . $e->getMessage(), $e->getTraceAsString());
    echo '<div class="summary error"><strong>Критическая ошибка:</strong> Невозможно продолжить без конфигурации БД.</div></div></body></html>';
    exit;
}

// 2. Проверка подключения к базе данных
$db = null;
try {
    $db = getDB();
    $db->query('SELECT 1');
    addResult('Подключение к базе данных', 'success', 'Подключение к базе данных успешно.');
} catch (Throwable $e) {
    addResult('Подключение к базе данных', 'error', 'Ошибка подключения: ' . $e->getMessage(), $e->getTraceAsString());
    echo '<div class="summary error"><strong>Критическая ошибка:</strong> Невозможно подключиться к базе данных.</div></div></body></html>';
    exit;
}

// 3. Проверка загрузки config/supabase.php
try {
    require_once __DIR__ . '/config/supabase.php';
    addResult('Загрузка config/supabase.php', 'success', 'Файл конфигурации Supabase загружен успешно.');
} catch (Throwable $e) {
    addResult('Загрузка config/supabase.php', 'error', 'Ошибка загрузки: ' . $e->getMessage(), $e->getTraceAsString());
}

// 4. Проверка конфигурации Supabase
try {
    $url = defined('SUPABASE_URL') ? SUPABASE_URL : '';
    $key = defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : '';
    
    if (empty($url)) {
        addResult('Конфигурация SUPABASE_URL', 'error', 'SUPABASE_URL не установлен или пуст.');
    } else {
        addResult('Конфигурация SUPABASE_URL', 'success', 'SUPABASE_URL установлен: ' . substr($url, 0, 30) . '...');
    }
    
    if (empty($key)) {
        addResult('Конфигурация SUPABASE_SERVICE_ROLE_KEY', 'error', 'SUPABASE_SERVICE_ROLE_KEY не установлен или пуст.');
    } else {
        addResult('Конфигурация SUPABASE_SERVICE_ROLE_KEY', 'success', 'SUPABASE_SERVICE_ROLE_KEY установлен (длина: ' . strlen($key) . ' символов)');
    }
} catch (Throwable $e) {
    addResult('Проверка конфигурации Supabase', 'error', 'Ошибка: ' . $e->getMessage(), $e->getTraceAsString());
}

// 5. Проверка инициализации Supabase клиента
try {
    if (function_exists('getSupabaseClient')) {
        $supabase = getSupabaseClient();
        addResult('Инициализация Supabase клиента', 'success', 'Supabase клиент успешно инициализирован.');
    } else {
        addResult('Инициализация Supabase клиента', 'error', 'Функция getSupabaseClient() не найдена.');
    }
} catch (Throwable $e) {
    addResult('Инициализация Supabase клиента', 'error', 'Ошибка инициализации: ' . $e->getMessage(), $e->getTraceAsString());
}

// 6. Проверка загрузки api/sync.php
try {
    if (file_exists(__DIR__ . '/api/sync.php')) {
        require_once __DIR__ . '/api/sync.php';
        addResult('Загрузка api/sync.php', 'success', 'Файл api/sync.php загружен успешно.');
    } else {
        addResult('Загрузка api/sync.php', 'error', 'Файл api/sync.php не найден.');
    }
} catch (Throwable $e) {
    addResult('Загрузка api/sync.php', 'error', 'Ошибка загрузки: ' . $e->getMessage(), $e->getTraceAsString());
}

// 7. Проверка доступности функции syncUserToSupabase
if (function_exists('syncUserToSupabase')) {
    addResult('Функция syncUserToSupabase()', 'success', 'Функция syncUserToSupabase() доступна.');
} else {
    addResult('Функция syncUserToSupabase()', 'error', 'Функция syncUserToSupabase() не найдена.');
}

// 8. Получение первого пользователя из MySQL для теста
try {
    $stmt = $db->query('SELECT id, email, first_name, last_name FROM users ORDER BY id LIMIT 1');
    $testUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testUser) {
        addResult('Получение тестового пользователя', 'success', 'Найден пользователь: ' . $testUser['email'] . ' (ID: ' . $testUser['id'] . ')');
        
        // 9. Попытка синхронизации тестового пользователя
        if (function_exists('syncUserToSupabase') && function_exists('getSupabaseClient')) {
            try {
                syncUserToSupabase((int)$testUser['id']);
                addResult('Тестовая синхронизация пользователя', 'success', 'Пользователь успешно синхронизирован с Supabase.');
            } catch (Throwable $e) {
                addResult('Тестовая синхронизация пользователя', 'error', 'Ошибка синхронизации: ' . $e->getMessage(), $e->getTraceAsString());
            }
        } else {
            addResult('Тестовая синхронизация пользователя', 'warning', 'Невозможно выполнить синхронизацию: функции недоступны.');
        }
    } else {
        addResult('Получение тестового пользователя', 'warning', 'В базе данных нет пользователей для теста.');
    }
} catch (Throwable $e) {
    addResult('Получение тестового пользователя', 'error', 'Ошибка: ' . $e->getMessage(), $e->getTraceAsString());
}

// Вывод результатов
foreach ($results as $result) {
    $class = $result['status'];
    echo '<div class="test-item ' . $class . '">';
    echo '<div class="test-title">' . htmlspecialchars($result['test']) . '</div>';
    echo '<div class="test-message">' . htmlspecialchars($result['message']) . '</div>';
    if (!empty($result['details'])) {
        echo '<div class="test-details">' . htmlspecialchars($result['details']) . '</div>';
    }
    echo '</div>';
}

// Итоговый статус
echo '<div class="summary ' . $overallStatus . '">';
if ($overallStatus === 'success') {
    echo '<strong>✅ Все проверки пройдены успешно!</strong><br>';
    echo 'Синхронизация должна работать корректно.';
} elseif ($overallStatus === 'warning') {
    echo '<strong>⚠️ Обнаружены предупреждения</strong><br>';
    echo 'Синхронизация может работать не полностью. Проверьте предупреждения выше.';
} else {
    echo '<strong>❌ Обнаружены ошибки</strong><br>';
    echo 'Исправьте ошибки выше, чтобы синхронизация работала корректно.';
}
echo '</div>';

echo '<div style="margin-top: 20px; padding: 15px; background: #1e1e24; border-radius: 8px; font-size: 12px; color: #9ca3af;">';
echo '<strong>Информация:</strong><br>';
echo 'Этот скрипт проверяет все компоненты синхронизации с Supabase.<br>';
echo 'После исправления ошибок удалите этот файл с сервера по соображениям безопасности.';
echo '</div>';
?>

    </div>
</body>
</html>
