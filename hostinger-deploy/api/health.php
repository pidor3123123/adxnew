<?php
/**
 * ADX Finance - Health Check API
 * Проверка доступности соединений с MySQL и Supabase
 */

// ВАЖНО: Включаем буферизацию вывода ПЕРВЫМ делом
if (!ob_get_level()) {
    ob_start();
}

// Функция для безопасного вывода JSON ответа
function outputHealthJson($data, $code = 200) {
    // Очищаем все уровни буфера
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Устанавливаем заголовки если еще не установлены
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code($code);
        
        // Устанавливаем CORS заголовки
        if (function_exists('setCorsHeaders')) {
            setCorsHeaders();
        } else {
            header('Access-Control-Allow-Origin: *');
        }
    }
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Загружаем необходимые файлы с проверкой существования
$requiredFiles = [
    __DIR__ . '/../config/database.php',
    __DIR__ . '/../config/supabase.php'
];

foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        error_log("Required file not found in health.php: $file");
        outputHealthJson([
            'mysql' => false,
            'supabase' => false,
            'error' => 'Configuration file not found: ' . basename($file),
            'timestamp' => date('Y-m-d H:i:s')
        ], 500);
    }
    
    try {
        require_once $file;
    } catch (Throwable $e) {
        error_log("Error loading file in health.php: $file - " . $e->getMessage());
        outputHealthJson([
            'mysql' => false,
            'supabase' => false,
            'error' => 'Configuration error: ' . $e->getMessage() . ' in ' . basename($file),
            'timestamp' => date('Y-m-d H:i:s')
        ], 500);
    }
}

// Устанавливаем заголовки после загрузки всех файлов
if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
    
    // Устанавливаем CORS заголовки если функция доступна
    if (function_exists('setCorsHeaders')) {
        setCorsHeaders();
    } else {
        header('Access-Control-Allow-Origin: *');
    }
}

// Устанавливаем таймаут для быстрого ответа
set_time_limit(5);

$result = [
    'mysql' => false,
    'supabase' => false,
    'timestamp' => date('Y-m-d H:i:s')
];

// Проверка MySQL
try {
    $db = getDB();
    if ($db) {
        // Простой запрос для проверки соединения
        $stmt = $db->query('SELECT 1 as test');
        if ($stmt !== false) {
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            $result['mysql'] = ($test && isset($test['test']) && $test['test'] == 1);
        }
    }
} catch (Exception $e) {
    error_log('MySQL health check failed: ' . $e->getMessage());
    $result['mysql'] = false;
    $result['mysql_error'] = $e->getMessage();
}

// Проверка Supabase
try {
    $supabase = getSupabaseClient();
    if ($supabase) {
        // Простой запрос для проверки соединения (получаем одну запись)
        $test = $supabase->select('users', 'id', [], 1);
        $result['supabase'] = (is_array($test));
    }
} catch (Exception $e) {
    error_log('Supabase health check failed: ' . $e->getMessage());
    $result['supabase'] = false;
    $result['supabase_error'] = $e->getMessage();
}

// Выводим результат через безопасную функцию
outputHealthJson($result, 200);
