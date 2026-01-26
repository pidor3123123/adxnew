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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #1a1a1f 0%, #2a2a32 100%);
            color: #fff;
            padding: 20px;
            line-height: 1.6;
            min-height: 100vh;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(42, 42, 50, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        
        h1 {
            color: #6366f1;
            margin: 0 0 30px 0;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 32px;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(99, 102, 241, 0.3);
        }
        
        h1::before {
            content: '🔍';
            font-size: 36px;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }
        
        .test-item {
            background: linear-gradient(135deg, #1e1e24 0%, #25252d 100%);
            padding: 20px;
            margin: 15px 0;
            border-radius: 12px;
            border-left: 5px solid #6366f1;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .test-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: currentColor;
            transition: width 0.3s ease;
        }
        
        .test-item:hover {
            transform: translateX(5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }
        
        .test-item.success {
            border-left-color: #22c55e;
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1) 0%, #1a2e1a 100%);
        }
        
        .test-item.success::before {
            background: #22c55e;
        }
        
        .test-item.error {
            border-left-color: #ef4444;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, #2e1a1a 100%);
        }
        
        .test-item.error::before {
            background: #ef4444;
        }
        
        .test-item.warning {
            border-left-color: #f59e0b;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, #2e2a1a 100%);
        }
        
        .test-item.warning::before {
            background: #f59e0b;
        }
        
        .test-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        
        .test-icon {
            font-size: 24px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            flex-shrink: 0;
        }
        
        .test-item.success .test-icon {
            background: rgba(34, 197, 94, 0.2);
        }
        
        .test-item.error .test-icon {
            background: rgba(239, 68, 68, 0.2);
        }
        
        .test-item.warning .test-icon {
            background: rgba(245, 158, 11, 0.2);
        }
        
        .test-title {
            font-weight: 600;
            font-size: 16px;
            color: #e5e7eb;
            flex: 1;
        }
        
        .test-message {
            color: #9ca3af;
            font-size: 14px;
            margin-left: 44px;
            line-height: 1.5;
        }
        
        .test-details-toggle {
            margin-top: 12px;
            margin-left: 44px;
        }
        
        .test-details-btn {
            background: rgba(99, 102, 241, 0.2);
            border: 1px solid rgba(99, 102, 241, 0.3);
            color: #a5b4fc;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s ease;
        }
        
        .test-details-btn:hover {
            background: rgba(99, 102, 241, 0.3);
            border-color: rgba(99, 102, 241, 0.5);
        }
        
        .test-details {
            margin-top: 12px;
            margin-left: 44px;
            padding: 12px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            font-family: 'Courier New', 'Consolas', monospace;
            font-size: 11px;
            color: #d1d5db;
            white-space: pre-wrap;
            word-break: break-all;
            display: none;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        
        .test-details.show {
            display: block;
        }
        
        .summary {
            margin-top: 40px;
            padding: 30px;
            border-radius: 16px;
            text-align: center;
            background: linear-gradient(135deg, #1e1e24 0%, #25252d 100%);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .summary.success {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.15) 0%, #1a2e1a 100%);
            border-color: #22c55e;
            box-shadow: 0 8px 24px rgba(34, 197, 94, 0.2);
        }
        
        .summary.error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, #2e1a1a 100%);
            border-color: #ef4444;
            box-shadow: 0 8px 24px rgba(239, 68, 68, 0.2);
        }
        
        .summary.warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, #2e2a1a 100%);
            border-color: #f59e0b;
            box-shadow: 0 8px 24px rgba(245, 158, 11, 0.2);
        }
        
        .summary-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .summary-message {
            font-size: 16px;
            color: #d1d5db;
            line-height: 1.6;
        }
        
        .info-box {
            margin-top: 30px;
            padding: 20px;
            background: rgba(30, 30, 36, 0.6);
            border-radius: 12px;
            font-size: 13px;
            color: #9ca3af;
            border: 1px solid rgba(99, 102, 241, 0.1);
        }
        
        .info-box strong {
            color: #6366f1;
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .progress-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }
        
        .test-item.success .progress-indicator {
            background: #22c55e;
        }
        
        .test-item.error .progress-indicator {
            background: #ef4444;
        }
        
        .test-item.warning .progress-indicator {
            background: #f59e0b;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Тест синхронизации Supabase</h1>

<?php
$results = [];
$overallStatus = 'success';

function getStatusIcon($status) {
    switch ($status) {
        case 'success':
            return '✅';
        case 'error':
            return '❌';
        case 'warning':
            return '⚠️';
        default:
            return 'ℹ️';
    }
}

function getFriendlyTestName($test) {
    $names = [
        'Загрузка config/database.php' => 'Конфигурация базы данных',
        'Подключение к базе данных' => 'Подключение к базе данных',
        'Загрузка config/supabase.php' => 'Конфигурация Supabase',
        'Конфигурация SUPABASE_URL' => 'URL Supabase',
        'Конфигурация SUPABASE_SERVICE_ROLE_KEY' => 'Ключ доступа Supabase',
        'Инициализация Supabase клиента' => 'Подключение к Supabase',
        'Загрузка api/sync.php' => 'Модуль синхронизации',
        'Функция syncUserToSupabase()' => 'Функция синхронизации',
        'Получение тестового пользователя' => 'Поиск пользователя',
        'Тестовая синхронизация пользователя' => 'Тест синхронизации'
    ];
    return $names[$test] ?? $test;
}

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
    addResult('Загрузка config/database.php', 'success', 'Конфигурация базы данных загружена и готова к использованию.');
} catch (Throwable $e) {
    addResult('Загрузка config/database.php', 'error', 'Не удалось загрузить конфигурацию базы данных: ' . $e->getMessage(), $e->getTraceAsString());
    echo '<div class="summary error"><div class="summary-title">❌ Критическая ошибка</div><div class="summary-message">Невозможно продолжить без конфигурации базы данных. Проверьте файл config/database.php</div></div></div></body></html>';
    exit;
}

// 2. Проверка подключения к базе данных
$db = null;
try {
    $db = getDB();
    $db->query('SELECT 1');
    addResult('Подключение к базе данных', 'success', 'Успешно подключено к базе данных MySQL.');
} catch (Throwable $e) {
    addResult('Подключение к базе данных', 'error', 'Не удалось подключиться к базе данных: ' . $e->getMessage(), $e->getTraceAsString());
    echo '<div class="summary error"><div class="summary-title">❌ Критическая ошибка</div><div class="summary-message">Невозможно подключиться к базе данных. Проверьте настройки подключения.</div></div></div></body></html>';
    exit;
}

// 3. Проверка загрузки config/supabase.php
try {
    require_once __DIR__ . '/config/supabase.php';
    addResult('Загрузка config/supabase.php', 'success', 'Конфигурация Supabase загружена успешно.');
} catch (Throwable $e) {
    addResult('Загрузка config/supabase.php', 'error', 'Ошибка загрузки конфигурации Supabase: ' . $e->getMessage(), $e->getTraceAsString());
}

// 4. Проверка конфигурации Supabase
try {
    $url = defined('SUPABASE_URL') ? SUPABASE_URL : '';
    $key = defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : '';
    
    if (empty($url)) {
        addResult('Конфигурация SUPABASE_URL', 'error', 'URL Supabase не установлен. Укажите адрес вашего проекта Supabase в config/supabase.php');
    } else {
        addResult('Конфигурация SUPABASE_URL', 'success', 'URL Supabase настроен: ' . substr($url, 0, 35) . '...');
    }
    
    if (empty($key)) {
        addResult('Конфигурация SUPABASE_SERVICE_ROLE_KEY', 'error', 'Ключ доступа Supabase не установлен. Укажите Service Role Key в config/supabase.php');
    } else {
        addResult('Конфигурация SUPABASE_SERVICE_ROLE_KEY', 'success', 'Ключ доступа установлен и готов к использованию');
    }
} catch (Throwable $e) {
    addResult('Проверка конфигурации Supabase', 'error', 'Ошибка при проверке конфигурации: ' . $e->getMessage(), $e->getTraceAsString());
}

// 5. Проверка инициализации Supabase клиента
try {
    if (function_exists('getSupabaseClient')) {
        $supabase = getSupabaseClient();
        addResult('Инициализация Supabase клиента', 'success', 'Подключение к Supabase установлено и работает.');
    } else {
        addResult('Инициализация Supabase клиента', 'error', 'Функция подключения к Supabase не найдена. Проверьте файл config/supabase.php');
    }
} catch (Throwable $e) {
    addResult('Инициализация Supabase клиента', 'error', 'Не удалось подключиться к Supabase: ' . $e->getMessage(), $e->getTraceAsString());
}

// 6. Проверка загрузки api/sync.php
try {
    if (file_exists(__DIR__ . '/api/sync.php')) {
        require_once __DIR__ . '/api/sync.php';
        addResult('Загрузка api/sync.php', 'success', 'Модуль синхронизации загружен и готов к работе.');
    } else {
        addResult('Загрузка api/sync.php', 'error', 'Файл модуля синхронизации не найден. Убедитесь, что api/sync.php существует.');
    }
} catch (Throwable $e) {
    addResult('Загрузка api/sync.php', 'error', 'Ошибка при загрузке модуля синхронизации: ' . $e->getMessage(), $e->getTraceAsString());
}

// 7. Проверка доступности функции syncUserToSupabase
if (function_exists('syncUserToSupabase')) {
    addResult('Функция syncUserToSupabase()', 'success', 'Функция синхронизации доступна и готова к использованию.');
} else {
    addResult('Функция syncUserToSupabase()', 'error', 'Функция синхронизации не найдена. Проверьте файл api/sync.php');
}

// 8. Получение первого пользователя из MySQL для теста
try {
    $stmt = $db->query('SELECT id, email, first_name, last_name FROM users ORDER BY id LIMIT 1');
    $testUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testUser) {
        $userName = !empty($testUser['first_name']) ? $testUser['first_name'] . ' ' . $testUser['last_name'] : $testUser['email'];
        addResult('Получение тестового пользователя', 'success', 'Найден пользователь: ' . htmlspecialchars($userName) . ' (' . htmlspecialchars($testUser['email']) . ')');
        
        // 9. Попытка синхронизации тестового пользователя
        if (function_exists('syncUserToSupabase') && function_exists('getSupabaseClient')) {
            try {
                syncUserToSupabase((int)$testUser['id']);
                addResult('Тестовая синхронизация пользователя', 'success', 'Пользователь успешно синхронизирован с Supabase! Теперь он должен появиться в админ панели.');
            } catch (Throwable $e) {
                $errorMsg = $e->getMessage();
                // Упрощаем сообщение об ошибке для пользователя
                if (strpos($errorMsg, 'foreign key constraint') !== false) {
                    $errorMsg = 'Ошибка связи с базой данных Supabase. Возможно, пользователь уже существует или требуется обновление структуры таблиц.';
                }
                addResult('Тестовая синхронизация пользователя', 'error', $errorMsg, $e->getTraceAsString());
            }
        } else {
            addResult('Тестовая синхронизация пользователя', 'warning', 'Невозможно выполнить синхронизацию: необходимые функции недоступны.');
        }
    } else {
        addResult('Получение тестового пользователя', 'warning', 'В базе данных пока нет пользователей для тестирования. Зарегистрируйте пользователя на основном сайте.');
    }
} catch (Throwable $e) {
    addResult('Получение тестового пользователя', 'error', 'Ошибка при поиске пользователя: ' . $e->getMessage(), $e->getTraceAsString());
}

// Вывод результатов
$resultIndex = 0;
foreach ($results as $result) {
    $class = $result['status'];
    $icon = getStatusIcon($result['status']);
    $friendlyName = getFriendlyTestName($result['test']);
    $hasDetails = !empty($result['details']);
    $detailsId = 'details-' . $resultIndex;
    
    echo '<div class="test-item ' . $class . '">';
    echo '<div class="test-header">';
    echo '<div class="test-icon">' . $icon . '</div>';
    echo '<div class="test-title">' . htmlspecialchars($friendlyName) . '</div>';
    echo '</div>';
    echo '<div class="test-message">' . htmlspecialchars($result['message']) . '</div>';
    
    if ($hasDetails) {
        echo '<div class="test-details-toggle">';
        echo '<button class="test-details-btn" onclick="toggleDetails(\'' . $detailsId . '\')">Показать технические детали</button>';
        echo '</div>';
        echo '<div class="test-details" id="' . $detailsId . '">' . htmlspecialchars($result['details']) . '</div>';
    }
    
    echo '</div>';
    $resultIndex++;
}

// Итоговый статус
$summaryIcon = getStatusIcon($overallStatus);
$summaryTitle = '';
$summaryMessage = '';

if ($overallStatus === 'success') {
    $summaryTitle = '✅ Все проверки пройдены успешно!';
    $summaryMessage = 'Синхронизация настроена и работает корректно. Пользователи с основного сайта будут автоматически появляться в админ панели.';
} elseif ($overallStatus === 'warning') {
    $summaryTitle = '⚠️ Обнаружены предупреждения';
    $summaryMessage = 'Синхронизация может работать не полностью. Проверьте предупреждения выше и при необходимости исправьте их.';
} else {
    $summaryTitle = '❌ Обнаружены ошибки';
    $summaryMessage = 'Для работы синхронизации необходимо исправить ошибки, указанные выше. После исправления запустите тест снова.';
}

echo '<div class="summary ' . $overallStatus . '">';
echo '<div class="summary-title">' . $summaryTitle . '</div>';
echo '<div class="summary-message">' . $summaryMessage . '</div>';
echo '</div>';

echo '<div class="info-box">';
echo '<strong>💡 Полезная информация</strong>';
echo 'Этот скрипт проверяет все компоненты синхронизации между основным сайтом и админ панелью.<br>';
echo 'Если все проверки пройдены успешно, пользователи будут автоматически синхронизироваться с Supabase при регистрации.<br><br>';
echo '<strong>⚠️ Важно:</strong> После проверки удалите этот файл с сервера по соображениям безопасности.';
echo '</div>';
?>

    </div>
    
    <script>
        function toggleDetails(id) {
            const details = document.getElementById(id);
            const btn = details.previousElementSibling.querySelector('.test-details-btn');
            
            if (details.classList.contains('show')) {
                details.classList.remove('show');
                btn.textContent = 'Показать технические детали';
            } else {
                details.classList.add('show');
                btn.textContent = 'Скрыть технические детали';
            }
        }
    </script>
</body>
</html>
