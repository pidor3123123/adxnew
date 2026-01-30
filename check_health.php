<?php
/**
 * ADX Finance - Health Check Verification Script
 * Визуальная проверка доступности /api/health.php endpoint
 * 
 * ВАЖНО: Удалите этот файл после проверки!
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Check Verification - ADX Finance</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .test-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .test-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .test-url {
            background: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            word-break: break-all;
            margin-bottom: 15px;
            color: #495057;
        }
        
        .status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .status.success {
            background: #d4edda;
            color: #155724;
        }
        
        .status.error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status.loading {
            background: #fff3cd;
            color: #856404;
        }
        
        .response {
            margin-top: 15px;
            padding: 15px;
            background: white;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .response.success {
            border-color: #28a745;
            background: #f8fff9;
        }
        
        .response.error {
            border-color: #dc3545;
            background: #fff5f5;
        }
        
        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-top: 30px;
            color: #856404;
        }
        
        .warning strong {
            display: block;
            margin-bottom: 5px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 20px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #5568d3;
        }
        
        .info {
            margin-top: 20px;
            padding: 15px;
            background: #e7f3ff;
            border-radius: 8px;
            border-left: 4px solid #2196F3;
            font-size: 14px;
            color: #0c5460;
        }
        
        .info strong {
            display: block;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Health Check Verification</h1>
        <p class="subtitle">Проверка доступности /api/health.php endpoint</p>
        
        <div class="test-section">
            <div class="test-title">Тест 1: Проверка файла api/health.php</div>
            <div class="test-url"><?php echo htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/api/health.php'); ?></div>
            
            <?php
            $healthUrl = '/api/health.php';
            $healthFile = __DIR__ . $healthUrl;
            $fileExists = file_exists($healthFile);
            
            if ($fileExists) {
                $fileSize = filesize($healthFile);
                echo '<div class="status success">✓ Файл существует (' . number_format($fileSize) . ' байт)</div>';
            } else {
                echo '<div class="status error">✗ Файл НЕ найден!</div>';
                echo '<div class="response error">Путь: ' . htmlspecialchars($healthFile) . '</div>';
            }
            ?>
        </div>
        
        <div class="test-section">
            <div class="test-title">Тест 2: HTTP запрос к /api/health.php</div>
            <div class="test-url"><?php echo htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/api/health.php'); ?></div>
            
            <?php
            $fullUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/api/health.php';
            
            // Используем cURL для проверки
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $fullUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                echo '<div class="status error">✗ Ошибка cURL: ' . htmlspecialchars($curlError) . '</div>';
                echo '<div class="response error">Не удалось выполнить запрос к endpoint</div>';
            } elseif ($httpCode == 404) {
                echo '<div class="status error">✗ 404 Not Found</div>';
                echo '<div class="response error">Файл api/health.php не найден на сервере. Убедитесь, что файл загружен в папку public_html/api/</div>';
            } elseif ($httpCode == 200) {
                $jsonData = json_decode($response, true);
                if ($jsonData) {
                    echo '<div class="status success">✓ HTTP 200 OK</div>';
                    echo '<div class="response success">' . htmlspecialchars(json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</div>';
                    
                    // Проверяем содержимое ответа
                    if (isset($jsonData['mysql']) && isset($jsonData['supabase'])) {
                        echo '<div style="margin-top: 15px;">';
                        echo '<div class="status ' . ($jsonData['mysql'] ? 'success' : 'error') . '">MySQL: ' . ($jsonData['mysql'] ? '✓ Connected' : '✗ Disconnected') . '</div>';
                        echo '<div class="status ' . ($jsonData['supabase'] ? 'success' : 'error') . '" style="margin-left: 10px;">Supabase: ' . ($jsonData['supabase'] ? '✓ Connected' : '✗ Disconnected') . '</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="status error">✗ Неверный формат ответа (не JSON)</div>';
                    echo '<div class="response error">' . htmlspecialchars(substr($response, 0, 500)) . '</div>';
                }
            } else {
                echo '<div class="status error">✗ HTTP ' . $httpCode . '</div>';
                echo '<div class="response error">' . htmlspecialchars(substr($response, 0, 500)) . '</div>';
            }
            ?>
        </div>
        
        <div class="info">
            <strong>ℹ️ Информация:</strong>
            <ul style="margin-top: 10px; margin-left: 20px;">
                <li>Если файл существует, но возвращает 404, проверьте права доступа (должны быть 644)</li>
                <li>Если возвращается ошибка MySQL/Supabase, проверьте настройки в .env файле</li>
                <li>После успешной проверки удалите этот файл (check_health.php) с сервера</li>
            </ul>
        </div>
        
        <div class="warning">
            <strong>⚠️ ВАЖНО:</strong>
            Этот файл предназначен только для проверки после деплоя. Удалите его после использования для безопасности!
        </div>
        
        <button class="btn" onclick="location.reload()">🔄 Обновить проверку</button>
    </div>
</body>
</html>
