<?php
/**
 * ADX Finance - Диагностика API endpoints
 * Проверяет доступность и корректность работы всех API файлов
 * 
 * ВАЖНО: Удалите этот файл после диагностики!
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Diagnostics - ADX Finance</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f0f0f;
            color: #e0e0e0;
            padding: 20px;
            line-height: 1.6;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #333;
        }
        h1 { color: #fff; margin-bottom: 10px; }
        .test-section {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #333;
        }
        .test-title { font-weight: 600; color: #fff; margin-bottom: 15px; font-size: 18px; }
        .test-item {
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
            background: #252525;
            border-left: 3px solid #667eea;
        }
        .test-item.success { border-left-color: #28a745; }
        .test-item.error { border-left-color: #dc3545; background: #2a1a1a; }
        .test-item.warning { border-left-color: #ffc107; background: #2a2a1a; }
        .status { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; margin-right: 10px; }
        .status.success { background: #28a745; color: white; }
        .status.error { background: #dc3545; color: white; }
        .status.warning { background: #ffc107; color: #000; }
        .details { margin-top: 10px; padding: 10px; background: #0f0f0f; border-radius: 4px; font-family: monospace; font-size: 12px; }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 API Diagnostics - ADX Finance</h1>
            <p style="color: #999;">Проверка доступности и корректности работы всех API endpoints</p>
        </div>
        
        <?php
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $apiDir = __DIR__ . '/api';
        
        // Тест 1: Проверка существования файлов
        echo '<div class="test-section">';
        echo '<div class="test-title">Тест 1: Проверка существования API файлов</div>';
        
        $requiredFiles = [
            'api/wallet.php',
            'api/health.php',
            'api/trading.php',
            'api/portfolio.php',
            'api/auth.php',
            'config/database.php',
            'config/supabase.php'
        ];
        
        $allFilesExist = true;
        foreach ($requiredFiles as $file) {
            $fullPath = __DIR__ . '/' . $file;
            $exists = file_exists($fullPath);
            $allFilesExist = $allFilesExist && $exists;
            
            echo '<div class="test-item ' . ($exists ? 'success' : 'error') . '">';
            echo '<span class="status ' . ($exists ? 'success' : 'error') . '">' . ($exists ? '✓' : '✗') . '</span>';
            echo '<strong>' . htmlspecialchars($file) . '</strong>';
            if (!$exists) {
                echo '<div class="details">Файл не найден: ' . htmlspecialchars($fullPath) . '</div>';
            } else {
                echo '<div class="details">Размер: ' . number_format(filesize($fullPath)) . ' байт</div>';
            }
            echo '</div>';
        }
        echo '</div>';
        
        // Тест 2: Проверка синтаксиса PHP файлов
        echo '<div class="test-section">';
        echo '<div class="test-title">Тест 2: Проверка синтаксиса PHP файлов</div>';
        
        $phpFiles = [
            'api/wallet.php',
            'api/health.php',
            'api/trading.php',
            'api/portfolio.php'
        ];
        
        foreach ($phpFiles as $file) {
            $fullPath = __DIR__ . '/' . $file;
            if (!file_exists($fullPath)) {
                continue;
            }
            
            $output = [];
            $returnVar = 0;
            exec("php -l " . escapeshellarg($fullPath) . " 2>&1", $output, $returnVar);
            
            $isValid = $returnVar === 0;
            echo '<div class="test-item ' . ($isValid ? 'success' : 'error') . '">';
            echo '<span class="status ' . ($isValid ? 'success' : 'error') . '">' . ($isValid ? '✓' : '✗') . '</span>';
            echo '<strong>' . htmlspecialchars($file) . '</strong>';
            if (!$isValid) {
                echo '<div class="details">' . htmlspecialchars(implode("\n", $output)) . '</div>';
            } else {
                echo '<div class="details">Синтаксис корректен</div>';
            }
            echo '</div>';
        }
        echo '</div>';
        
        // Тест 3: Проверка HTTP запросов к API endpoints
        echo '<div class="test-section">';
        echo '<div class="test-title">Тест 3: Проверка HTTP запросов к API endpoints</div>';
        
        $endpoints = [
            '/api/health.php' => ['method' => 'GET'],
            '/api/wallet.php?action=balances' => ['method' => 'GET'],
        ];
        
        foreach ($endpoints as $endpoint => $config) {
            $url = $baseUrl . $endpoint;
            
            echo '<div class="test-item">';
            echo '<strong>' . htmlspecialchars($endpoint) . '</strong>';
            echo '<div class="details">';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                echo '<span class="status error">✗ Ошибка cURL</span>';
                echo '<div>Ошибка: ' . htmlspecialchars($curlError) . '</div>';
            } elseif ($httpCode == 200) {
                // Проверяем Content-Type
                $isJson = strpos($contentType, 'application/json') !== false;
                if ($isJson) {
                    echo '<span class="status success">✓ HTTP 200 OK (JSON)</span>';
                } else {
                    echo '<span class="status warning">⚠ HTTP 200 OK (но не JSON)</span>';
                    echo '<div>Content-Type: ' . htmlspecialchars($contentType) . '</div>';
                }
                
                // Пытаемся распарсить JSON
                $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $body = substr($response, $headerSize);
                $json = json_decode($body, true);
                if ($json !== null) {
                    echo '<div>JSON валиден</div>';
                } else {
                    echo '<div>JSON невалиден или пустой</div>';
                    echo '<div style="margin-top: 5px;">Ответ: ' . htmlspecialchars(substr($body, 0, 200)) . '</div>';
                }
            } else {
                echo '<span class="status error">✗ HTTP ' . $httpCode . '</span>';
                echo '<div>Content-Type: ' . htmlspecialchars($contentType ?: 'не определен') . '</div>';
            }
            
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        
        // Тест 4: Проверка require_once путей
        echo '<div class="test-section">';
        echo '<div class="test-title">Тест 4: Проверка require_once путей в API файлах</div>';
        
        foreach ($phpFiles as $file) {
            $fullPath = __DIR__ . '/' . $file;
            if (!file_exists($fullPath)) {
                continue;
            }
            
            $content = file_get_contents($fullPath);
            preg_match_all('/require_once\s+[\'"]?([^\'"\s]+)[\'"]?/', $content, $matches);
            
            echo '<div class="test-item">';
            echo '<strong>' . htmlspecialchars($file) . '</strong>';
            echo '<div class="details">';
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $requirePath) {
                    // Преобразуем относительный путь в абсолютный
                    $resolvedPath = $requirePath;
                    if (strpos($requirePath, '__DIR__') !== false) {
                        // Это сложный путь с __DIR__, пропускаем
                        continue;
                    } elseif (strpos($requirePath, '../') === 0 || strpos($requirePath, './') === 0) {
                        $resolvedPath = realpath(dirname($fullPath) . '/' . $requirePath);
                    } else {
                        $resolvedPath = realpath(__DIR__ . '/' . $requirePath);
                    }
                    
                    $exists = file_exists($resolvedPath);
                    echo '<div style="margin-top: 5px;">';
                    echo '<span class="status ' . ($exists ? 'success' : 'error') . '">' . ($exists ? '✓' : '✗') . '</span>';
                    echo htmlspecialchars($requirePath);
                    if (!$exists) {
                        echo ' → <span style="color: #dc3545;">Не найден</span>';
                    }
                    echo '</div>';
                }
            } else {
                echo '<div>require_once не найдены</div>';
            }
            
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        ?>
        
        <div class="warning-box">
            <strong>⚠️ ВАЖНО:</strong>
            Этот файл предназначен только для диагностики. Удалите его после использования для безопасности!
        </div>
    </div>
</body>
</html>
