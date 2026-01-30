<?php
/**
 * ADX Finance - Health Check API
 * Проверка доступности соединений с MySQL и Supabase
 */

// Включаем буферизацию вывода
ob_start();

// Загружаем необходимые файлы
try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/supabase.php';
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    setCorsHeaders();
    echo json_encode([
        'mysql' => false,
        'supabase' => false,
        'error' => 'Configuration error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Устанавливаем заголовки
header('Content-Type: application/json');
setCorsHeaders();

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

// Очищаем буфер и выводим результат
ob_end_clean();
echo json_encode($result, JSON_UNESCAPED_UNICODE);
