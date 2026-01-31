<?php
/**
 * ADX Finance - Финальная проверка wallet.php
 * Проверяет доступность getAuthUser() и симулирует вызов как в wallet.php
 * 
 * ВАЖНО: Удалите этот файл после диагностики!
 */

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

// Функция поиска файлов (как в wallet.php)
function findConfigFile($filename) {
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $currentDir = __DIR__;
    $parentDir = dirname($currentDir);
    
    $possiblePaths = [
        $currentDir . '/../config/' . $filename,
        $currentDir . '/../../config/' . $filename,
        $docRoot . '/config/' . $filename,
        $docRoot . '/../config/' . $filename,
        $parentDir . '/config/' . $filename,
        dirname($parentDir) . '/config/' . $filename,
        realpath($docRoot . '/config/' . $filename) ?: null,
        realpath($parentDir . '/config/' . $filename) ?: null,
    ];
    
    $possiblePaths = array_filter($possiblePaths, function($path) {
        return $path !== null;
    });
    
    foreach ($possiblePaths as $path) {
        $realPath = realpath($path);
        if ($realPath && file_exists($realPath) && is_readable($realPath)) {
            return $realPath;
        }
    }
    return null;
}

function findAuthFile() {
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $currentDir = __DIR__;
    
    $possiblePaths = [
        $currentDir . '/auth.php',
        realpath($currentDir . '/auth.php') ?: null,
        $docRoot . '/api/auth.php',
        realpath($docRoot . '/api/auth.php') ?: null,
    ];
    
    $possiblePaths = array_filter($possiblePaths, function($path) {
        return $path !== null;
    });
    
    foreach ($possiblePaths as $path) {
        $realPath = realpath($path);
        if ($realPath && file_exists($realPath) && is_readable($realPath)) {
            return $realPath;
        }
    }
    return null;
}

// Тест 1: Загрузка файлов как в wallet.php
$results['tests']['load_files'] = [];

try {
    // Загружаем database.php
    $dbFile = findConfigFile('database.php');
    if ($dbFile) {
        @include_once $dbFile;
        $results['tests']['load_files']['database'] = [
            'loaded' => true,
            'path' => $dbFile,
            'getDB_exists' => function_exists('getDB')
        ];
    } else {
        $results['tests']['load_files']['database'] = ['loaded' => false, 'error' => 'File not found'];
    }
    
    // Загружаем supabase.php
    $sbFile = findConfigFile('supabase.php');
    if ($sbFile) {
        @include_once $sbFile;
        $results['tests']['load_files']['supabase'] = [
            'loaded' => true,
            'path' => $sbFile,
            'getSupabaseClient_exists' => function_exists('getSupabaseClient')
        ];
    } else {
        $results['tests']['load_files']['supabase'] = ['loaded' => false, 'error' => 'File not found'];
    }
    
    // Загружаем auth.php
    $authFile = findAuthFile();
    if ($authFile) {
        @include_once $authFile;
        $results['tests']['load_files']['auth'] = [
            'loaded' => true,
            'path' => $authFile,
            'getAuthUser_exists' => function_exists('getAuthUser'),
            'getAuthorizationToken_exists' => function_exists('getAuthorizationToken'),
            'getUserByToken_exists' => function_exists('getUserByToken')
        ];
    } else {
        $results['tests']['load_files']['auth'] = ['loaded' => false, 'error' => 'File not found'];
    }
    
} catch (Throwable $e) {
    $results['tests']['load_files']['exception'] = [
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ];
}

// Тест 2: Проверка функций после загрузки
$results['tests']['functions_check'] = [
    'getDB' => function_exists('getDB'),
    'getSupabaseClient' => function_exists('getSupabaseClient'),
    'getAuthUser' => function_exists('getAuthUser'),
    'getAuthorizationToken' => function_exists('getAuthorizationToken'),
    'getUserByToken' => function_exists('getUserByToken'),
    'setCorsHeaders' => function_exists('setCorsHeaders')
];

// Тест 3: Попытка вызвать getAuthUser() (как в wallet.php)
$results['tests']['call_getAuthUser'] = [];
try {
    if (function_exists('getAuthUser')) {
        ob_start();
        $errorOccurred = false;
        $errorMessage = '';
        
        set_error_handler(function($severity, $message, $file, $line) use (&$errorOccurred, &$errorMessage) {
            if (in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR])) {
                $errorOccurred = true;
                $errorMessage = "$message in $file:$line";
            }
            return true;
        }, E_ALL);
        
        $user = @getAuthUser();
        restore_error_handler();
        $output = ob_get_clean();
        
        $results['tests']['call_getAuthUser'] = [
            'called' => true,
            'error' => $errorOccurred ? $errorMessage : null,
            'user' => $user !== null ? ['id' => $user['id'] ?? 'N/A', 'email' => $user['email'] ?? 'N/A'] : null,
            'output' => substr($output, 0, 200)
        ];
    } else {
        $results['tests']['call_getAuthUser'] = [
            'called' => false,
            'error' => 'Function getAuthUser() does not exist'
        ];
    }
} catch (Throwable $e) {
    $results['tests']['call_getAuthUser'] = [
        'called' => false,
        'exception' => [
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ];
}

// Итоговый статус
$results['summary'] = [
    'all_files_loaded' => ($results['tests']['load_files']['database']['loaded'] ?? false) &&
                          ($results['tests']['load_files']['supabase']['loaded'] ?? false) &&
                          ($results['tests']['load_files']['auth']['loaded'] ?? false),
    'getAuthUser_available' => function_exists('getAuthUser'),
    'getAuthUser_callable' => !isset($results['tests']['call_getAuthUser']['error']) && 
                              !isset($results['tests']['call_getAuthUser']['exception']),
    'recommendations' => []
];

if (!($results['summary']['getAuthUser_available'])) {
    $results['summary']['recommendations'][] = 'Функция getAuthUser() не найдена. Проверьте, что auth.php загружен и содержит эту функцию.';
}

if (!($results['summary']['getAuthUser_callable'])) {
    $results['summary']['recommendations'][] = 'Функция getAuthUser() не может быть вызвана. Проверьте зависимости (getDB, getAuthorizationToken).';
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
