<?php
/**
 * ADX Finance - Тест webhook синхронизации
 * Диагностический скрипт для проверки работы webhook между админ панелью и основным сайтом
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тест Webhook - ADX Finance</title>
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
            max-width: 1000px;
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
            content: '🔗';
            font-size: 36px;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }
        
        .test-section {
            background: linear-gradient(135deg, #1e1e24 0%, #25252d 100%);
            padding: 25px;
            margin: 20px 0;
            border-radius: 12px;
            border-left: 5px solid #6366f1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .test-section h2 {
            color: #e5e7eb;
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        .test-item {
            background: rgba(30, 30, 36, 0.6);
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 3px solid #6366f1;
        }
        
        .test-item.success {
            border-left-color: #22c55e;
            background: rgba(34, 197, 94, 0.1);
        }
        
        .test-item.error {
            border-left-color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }
        
        .test-item.warning {
            border-left-color: #f59e0b;
            background: rgba(245, 158, 11, 0.1);
        }
        
        .test-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: #e5e7eb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .test-message {
            color: #9ca3af;
            font-size: 14px;
            margin-left: 28px;
        }
        
        button {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin: 10px 5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }
        
        button:hover {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.6);
        }
        
        button:disabled {
            background: #4b5563;
            cursor: not-allowed;
            transform: none;
        }
        
        .info-box {
            margin-top: 20px;
            padding: 15px;
            background: rgba(30, 30, 36, 0.6);
            border-radius: 8px;
            font-size: 13px;
            color: #9ca3af;
            border: 1px solid rgba(99, 102, 241, 0.1);
        }
        
        .info-box strong {
            color: #6366f1;
            display: block;
            margin-bottom: 8px;
        }
        
        .code-block {
            background: rgba(0, 0, 0, 0.3);
            padding: 12px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #d1d5db;
            margin-top: 10px;
            white-space: pre-wrap;
            word-break: break-all;
            overflow-x: auto;
        }
        
        select, input {
            background: rgba(30, 30, 36, 0.8);
            border: 1px solid rgba(99, 102, 241, 0.3);
            color: #fff;
            padding: 10px;
            border-radius: 6px;
            font-size: 14px;
            margin: 5px;
            min-width: 200px;
        }
        
        select:focus, input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .form-group {
            margin: 15px 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #e5e7eb;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Тест Webhook синхронизации</h1>

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

// Загрузка конфигурации
try {
    require_once __DIR__ . '/config/database.php';
    $db = getDB();
} catch (Throwable $e) {
    addResult('Загрузка конфигурации', 'error', 'Ошибка загрузки: ' . $e->getMessage());
    echo '<div class="test-item error"><div class="test-title">❌ Критическая ошибка</div><div class="test-message">Невозможно продолжить без конфигурации БД.</div></div></div></body></html>';
    exit;
}

// Загрузка конфигурации webhook
try {
    if (file_exists(__DIR__ . '/config/webhook.php')) {
        require_once __DIR__ . '/config/webhook.php';
        addResult('Загрузка config/webhook.php', 'success', 'Файл конфигурации webhook загружен');
    } else {
        addResult('Загрузка config/webhook.php', 'warning', 'Файл config/webhook.php не найден, используется getenv()');
    }
} catch (Throwable $e) {
    addResult('Загрузка config/webhook.php', 'warning', 'Ошибка загрузки: ' . $e->getMessage() . ', используется getenv()');
}

// Проверка конфигурации
$webhookSecret = defined('WEBHOOK_SECRET') ? WEBHOOK_SECRET : getenv('WEBHOOK_SECRET');
if (empty($webhookSecret)) {
    addResult('WEBHOOK_SECRET', 'error', 'WEBHOOK_SECRET не установлен. Проверьте config/webhook.php или переменные окружения');
} else {
    addResult('WEBHOOK_SECRET', 'success', 'WEBHOOK_SECRET установлен (длина: ' . strlen($webhookSecret) . ' символов)');
}

$webhookUrl = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/api/webhook.php';
addResult('Webhook URL', 'success', 'URL webhook: ' . $webhookUrl);

// Проверка доступности webhook endpoint
if (file_exists(__DIR__ . '/api/webhook.php')) {
    addResult('Файл webhook.php', 'success', 'Файл api/webhook.php найден');
} else {
    addResult('Файл webhook.php', 'error', 'Файл api/webhook.php не найден');
}

// Обработка тестовых запросов
$action = $_GET['action'] ?? '';
$testResult = null;

if ($action === 'test_balance') {
    $userId = $_GET['user_id'] ?? null;
    $currency = $_GET['currency'] ?? 'USD';
    
    if (!$userId) {
        $testResult = ['status' => 'error', 'message' => 'Не указан user_id'];
    } else {
        // Получаем данные пользователя из MySQL
        try {
            $stmt = $db->prepare('SELECT id, email FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $testResult = ['status' => 'error', 'message' => 'Пользователь не найден в MySQL'];
            } else {
                // Получаем текущий баланс
                $stmt = $db->prepare('SELECT available, reserved FROM balances WHERE user_id = ? AND currency = ?');
                $stmt->execute([$userId, $currency]);
                $oldBalance = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Генерируем тестовые значения
                $testAvailable = 1000.50;
                $testLocked = 50.25;
                
                // Отправляем webhook
                $webhookData = [
                    'type' => 'balance_updated',
                    'payload' => [
                        'email' => $user['email'],
                        'currency' => $currency,
                        'available_balance' => $testAvailable,
                        'locked_balance' => $testLocked
                    ]
                ];
                
                $ch = curl_init($webhookUrl);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($webhookData),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'X-Webhook-Secret: ' . $webhookSecret
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                if ($curlError) {
                    $testResult = ['status' => 'error', 'message' => 'Ошибка cURL: ' . $curlError];
                } elseif ($httpCode !== 200) {
                    $testResult = ['status' => 'error', 'message' => "HTTP $httpCode: " . $response];
                } else {
                    $responseData = json_decode($response, true);
                    if ($responseData && isset($responseData['success']) && $responseData['success']) {
                        // Проверяем, что баланс обновился
                        $stmt = $db->prepare('SELECT available, reserved FROM balances WHERE user_id = ? AND currency = ?');
                        $stmt->execute([$userId, $currency]);
                        $newBalance = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($newBalance && abs($newBalance['available'] - $testAvailable) < 0.01) {
                            $testResult = [
                                'status' => 'success',
                                'message' => "Баланс успешно обновлен! Было: " . ($oldBalance ? $oldBalance['available'] : 0) . ", Стало: " . $newBalance['available']
                            ];
                        } else {
                            $testResult = ['status' => 'warning', 'message' => 'Webhook выполнен, но баланс не обновился в MySQL'];
                        }
                    } else {
                        $testResult = ['status' => 'error', 'message' => 'Webhook вернул ошибку: ' . ($responseData['error'] ?? 'Unknown')];
                    }
                }
            }
        } catch (Throwable $e) {
            $testResult = ['status' => 'error', 'message' => 'Ошибка: ' . $e->getMessage()];
        }
    }
}

// Вывод результатов проверки конфигурации
foreach ($results as $result) {
    $class = $result['status'];
    $icon = getStatusIcon($result['status']);
    echo '<div class="test-item ' . $class . '">';
    echo '<div class="test-title">' . $icon . ' ' . htmlspecialchars($result['test']) . '</div>';
    echo '<div class="test-message">' . htmlspecialchars($result['message']) . '</div>';
    if (!empty($result['details'])) {
        echo '<div class="code-block">' . htmlspecialchars($result['details']) . '</div>';
    }
    echo '</div>';
}

// Форма для тестирования баланса
if ($overallStatus === 'success') {
    echo '<div class="test-section">';
    echo '<h2>🧪 Тест синхронизации баланса</h2>';
    
    if ($testResult) {
        $icon = getStatusIcon($testResult['status']);
        $class = $testResult['status'];
        echo '<div class="test-item ' . $class . '">';
        echo '<div class="test-title">' . $icon . ' Результат теста</div>';
        echo '<div class="test-message">' . htmlspecialchars($testResult['message']) . '</div>';
        echo '</div>';
    }
    
    // Получаем список пользователей
    try {
        $stmt = $db->query('SELECT id, email, first_name, last_name FROM users ORDER BY id LIMIT 20');
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($users)) {
            echo '<form method="GET" action="">';
            echo '<input type="hidden" name="action" value="test_balance">';
            echo '<div class="form-group">';
            echo '<label>Выберите пользователя:</label>';
            echo '<select name="user_id" required>';
            foreach ($users as $user) {
                $name = ($user['first_name'] ? $user['first_name'] . ' ' : '') . ($user['last_name'] ?? '');
                $display = $name ? "$name ({$user['email']})" : $user['email'];
                echo '<option value="' . $user['id'] . '">' . htmlspecialchars($display) . '</option>';
            }
            echo '</select>';
            echo '</div>';
            echo '<div class="form-group">';
            echo '<label>Валюта:</label>';
            echo '<select name="currency">';
            echo '<option value="USD">USD</option>';
            echo '<option value="EUR">EUR</option>';
            echo '<option value="BTC">BTC</option>';
            echo '<option value="ETH">ETH</option>';
            echo '</select>';
            echo '</div>';
            echo '<button type="submit">🚀 Отправить тестовый webhook для баланса</button>';
            echo '</form>';
        } else {
            echo '<div class="test-item warning">В базе данных нет пользователей для тестирования.</div>';
        }
    } catch (Throwable $e) {
        echo '<div class="test-item error">Ошибка получения списка пользователей: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    
    echo '</div>';
}

echo '<div class="info-box">';
echo '<strong>💡 Полезная информация</strong>';
echo 'Этот скрипт проверяет работу webhook между админ панелью и основным сайтом.<br>';
echo 'При отправке тестового webhook баланс пользователя будет обновлен на тестовые значения.<br><br>';
echo '<strong>⚠️ Важно:</strong> После проверки удалите этот файл с сервера по соображениям безопасности.';
echo '</div>';
?>

    </div>
</body>
</html>
