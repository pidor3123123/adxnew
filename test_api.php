<?php
/**
 * ADX Finance - Тестовый API endpoint
 * Проверяет работу PHP, обработку ошибок и возврат JSON
 * 
 * ВАЖНО: Удалите этот файл после проверки!
 */

// Включаем буферизацию ПЕРВЫМ делом
if (!ob_get_level()) {
    @ob_start();
}

// Функция для безопасного вывода JSON
function outputJson($data, $code = 200) {
    // Отключаем обработчик ошибок, чтобы избежать рекурсии
    restore_error_handler();
    
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code($code);
        header('Access-Control-Allow-Origin: *');
    }
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Обработка ошибок (только для критических ошибок)
set_error_handler(function($severity, $message, $file, $line) {
    // Игнорируем ошибки, которые не являются критическими
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    // Только для критических ошибок
    if (in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR])) {
        // Отключаем обработчик, чтобы избежать рекурсии
        restore_error_handler();
        
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(500);
            header('Access-Control-Allow-Origin: *');
        }
        
        echo json_encode([
            'success' => false,
            'error' => 'PHP Error: ' . $message . ' in ' . basename($file) . ':' . $line,
            'type' => 'error_handler',
            'severity' => $severity
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    return false;
}, E_ALL & ~E_NOTICE & ~E_WARNING);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR])) {
        // Отключаем обработчик ошибок
        restore_error_handler();
        
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(500);
            header('Access-Control-Allow-Origin: *');
        }
        
        echo json_encode([
            'success' => false,
            'error' => 'Fatal Error: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line'],
            'type' => 'fatal_error',
            'error_type' => $error['type']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

// Проверяем параметры
$test = $_GET['test'] ?? 'basic';

try {
    switch ($test) {
        case 'basic':
            // Базовый тест
            outputJson([
                'success' => true,
                'message' => 'API test endpoint is working',
                'php_version' => PHP_VERSION,
                'server_time' => date('Y-m-d H:i:s'),
                'timestamp' => time()
            ]);
            
        case 'headers':
            // Проверка заголовков
            $headers = [];
            // getallheaders() может быть недоступна в некоторых окружениях
            if (function_exists('getallheaders')) {
                foreach (getallheaders() ?: [] as $name => $value) {
                    $headers[$name] = $value;
                }
            } else {
                // Альтернативный способ получения заголовков
                foreach ($_SERVER as $key => $value) {
                    if (strpos($key, 'HTTP_') === 0) {
                        $headerName = str_replace('_', '-', substr($key, 5));
                        $headers[$headerName] = $value;
                    }
                }
            }
            
            outputJson([
                'success' => true,
                'headers' => $headers,
                'content_type' => headers_sent() ? 'headers_already_sent' : 'not_sent_yet'
            ]);
            
        case 'error':
            // Тест обработки ошибки
            throw new Exception('Test error handling');
            
        case 'file_check':
            // Проверка существования файлов
            $files = [
                'api/wallet.php',
                'api/health.php',
                'api/trading.php',
                'config/database.php',
                'config/supabase.php'
            ];
            
            $results = [];
            foreach ($files as $file) {
                $fullPath = __DIR__ . '/' . $file;
                $results[$file] = [
                    'exists' => file_exists($fullPath),
                    'readable' => is_readable($fullPath),
                    'size' => file_exists($fullPath) ? filesize($fullPath) : 0
                ];
            }
            
            outputJson([
                'success' => true,
                'files' => $results
            ]);
            
        case 'version':
            // Проверка версии wallet.php
            $walletFile = __DIR__ . '/api/wallet.php';
            if (file_exists($walletFile)) {
                $content = file_get_contents($walletFile);
                preg_match('/Версия:\s*([^\n]+)/', $content, $matches);
                $version = $matches[1] ?? 'unknown';
                
                outputJson([
                    'success' => true,
                    'wallet_version' => trim($version),
                    'file_exists' => true,
                    'file_size' => filesize($walletFile)
                ]);
            } else {
                outputJson([
                    'success' => false,
                    'error' => 'wallet.php not found',
                    'file_exists' => false
                ], 404);
            }
            
        default:
            outputJson([
                'success' => false,
                'error' => 'Unknown test parameter',
                'available_tests' => ['basic', 'headers', 'error', 'file_check', 'version']
            ], 400);
    }
} catch (Exception $e) {
    outputJson([
        'success' => false,
        'error' => $e->getMessage(),
        'type' => 'exception',
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
} catch (Throwable $e) {
    outputJson([
        'success' => false,
        'error' => $e->getMessage(),
        'type' => 'throwable',
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
}
