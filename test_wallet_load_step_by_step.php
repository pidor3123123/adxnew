<?php
/**
 * ADX Finance - Пошаговая проверка загрузки файлов
 * Загружает файлы по одному, чтобы найти проблемный
 * 
 * ВАЖНО: Удалите этот файл после диагностики!
 */

// Включаем буферизацию
if (!ob_get_level()) {
    @ob_start();
}

ini_set('display_errors', 0);
error_reporting(E_ALL);

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
    'tests' => []
];

// Функция для безопасной загрузки файла
function safeLoadFile($filePath, $fileName) {
    $result = [
        'file' => $fileName,
        'path' => $filePath,
        'exists' => @file_exists($filePath),
        'readable' => @is_readable($filePath),
        'loaded' => false,
        'error' => null,
        'output' => '',
        'functions_after' => []
    ];
    
    if (!$result['exists'] || !$result['readable']) {
        $result['error'] = 'File not found or not readable';
        return $result;
    }
    
    // Пробуем загрузить файл
    ob_start();
    $loadError = null;
    
    // Временный обработчик ошибок
    set_error_handler(function($severity, $message, $file, $line) use (&$loadError) {
        if (in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR])) {
            $loadError = [
                'severity' => $severity,
                'message' => $message,
                'file' => basename($file),
                'line' => $line
            ];
        }
        return true;
    }, E_ALL);
    
    try {
        @include_once $filePath;
        $result['loaded'] = ($loadError === null);
    } catch (ParseError $e) {
        $loadError = [
            'type' => 'ParseError',
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ];
    } catch (Throwable $e) {
        $loadError = [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ];
    }
    
    restore_error_handler();
    $output = ob_get_clean();
    
    if ($loadError !== null) {
        $result['error'] = $loadError;
    }
    
    $result['output'] = substr($output, 0, 500);
    
    // Проверяем доступные функции после загрузки
    $result['functions_after'] = [
        'getDB' => function_exists('getDB'),
        'getSupabaseClient' => function_exists('getSupabaseClient'),
        'getAuthUser' => function_exists('getAuthUser'),
        'setCorsHeaders' => function_exists('setCorsHeaders')
    ];
    
    return $result;
}

// Тест 1: Загрузка database.php
$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
$results['tests']['load_database'] = safeLoadFile($dbPath, 'database.php');

// Тест 2: Загрузка supabase.php (после database.php)
$sbPath = $_SERVER['DOCUMENT_ROOT'] . '/config/supabase.php';
$results['tests']['load_supabase'] = safeLoadFile($sbPath, 'supabase.php');

// Тест 3: Загрузка auth.php (после config файлов)
$authPath = $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';
$results['tests']['load_auth'] = safeLoadFile($authPath, 'auth.php');

// Итоговый статус
$results['summary'] = [
    'database_loaded' => $results['tests']['load_database']['loaded'] ?? false,
    'supabase_loaded' => $results['tests']['load_supabase']['loaded'] ?? false,
    'auth_loaded' => $results['tests']['load_auth']['loaded'] ?? false,
    'all_loaded' => ($results['tests']['load_database']['loaded'] ?? false) && 
                   ($results['tests']['load_supabase']['loaded'] ?? false) && 
                   ($results['tests']['load_auth']['loaded'] ?? false),
    'problematic_file' => null,
    'recommendations' => []
];

// Определяем проблемный файл
if (!$results['summary']['database_loaded']) {
    $results['summary']['problematic_file'] = 'database.php';
    $results['summary']['recommendations'][] = 'Проверьте синтаксис и зависимости в database.php';
} elseif (!$results['summary']['supabase_loaded']) {
    $results['summary']['problematic_file'] = 'supabase.php';
    $results['summary']['recommendations'][] = 'Проверьте синтаксис и зависимости в supabase.php';
} elseif (!$results['summary']['auth_loaded']) {
    $results['summary']['problematic_file'] = 'auth.php';
    $results['summary']['recommendations'][] = 'Проверьте синтаксис и зависимости в auth.php';
}

// Очищаем буфер
while (ob_get_level() > 0) {
    @ob_end_clean();
}

// Устанавливаем заголовки
if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
}

// Выводим результат
echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
