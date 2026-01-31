<?php
/**
 * ADX Finance - Детальная диагностика wallet.php
 * Проверяет каждый шаг загрузки файлов и инициализации
 * 
 * ВАЖНО: Удалите этот файл после диагностики!
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
    'current_dir' => __DIR__,
    'tests' => []
];

// Тест 1: Проверка существования config файлов
$results['tests']['config_files'] = [];
$configFiles = ['database.php', 'supabase.php', 'webhook.php'];
foreach ($configFiles as $file) {
    $path = $_SERVER['DOCUMENT_ROOT'] . '/config/' . $file;
    $realPath = realpath($path);
    $results['tests']['config_files'][$file] = [
        'path' => $path,
        'realpath' => $realPath,
        'exists' => file_exists($path),
        'readable' => is_readable($path),
        'size' => file_exists($path) ? filesize($path) : 0,
        'permissions' => file_exists($path) ? substr(sprintf('%o', fileperms($path)), -4) : 'N/A'
    ];
}

// Тест 2: Проверка auth.php
$authPaths = [
    __DIR__ . '/auth.php',
    $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php'
];
$results['tests']['auth_file'] = [];
foreach ($authPaths as $path) {
    $realPath = realpath($path);
    $results['tests']['auth_file'][] = [
        'path' => $path,
        'realpath' => $realPath,
        'exists' => file_exists($path),
        'readable' => is_readable($path),
        'size' => file_exists($path) ? filesize($path) : 0,
        'permissions' => file_exists($path) ? substr(sprintf('%o', fileperms($path)), -4) : 'N/A'
    ];
}

// Тест 3: Попытка загрузить database.php
$results['tests']['load_database'] = [];
try {
    $dbPath = $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    if (file_exists($dbPath) && is_readable($dbPath)) {
        // Проверяем синтаксис без выполнения
        $syntaxCheck = shell_exec("php -l " . escapeshellarg($dbPath) . " 2>&1");
        $results['tests']['load_database']['syntax_check'] = trim($syntaxCheck);
        $results['tests']['load_database']['syntax_valid'] = strpos($syntaxCheck, 'No syntax errors') !== false;
        
        // Пробуем загрузить
        ob_start();
        $errorOccurred = false;
        set_error_handler(function($severity, $message, $file, $line) use (&$errorOccurred, &$results) {
            $errorOccurred = true;
            $results['tests']['load_database']['load_error'] = "$message in $file:$line";
            return true;
        }, E_ALL);
        
        require_once $dbPath;
        restore_error_handler();
        $output = ob_get_clean();
        
        $results['tests']['load_database']['loaded'] = !$errorOccurred;
        $results['tests']['load_database']['output'] = $output;
        $results['tests']['load_database']['functions_available'] = [
            'getDB' => function_exists('getDB'),
            'setCorsHeaders' => function_exists('setCorsHeaders')
        ];
    } else {
        $results['tests']['load_database']['error'] = 'File not found or not readable';
    }
} catch (Throwable $e) {
    $results['tests']['load_database']['exception'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
}

// Тест 4: Попытка загрузить supabase.php
$results['tests']['load_supabase'] = [];
try {
    $sbPath = $_SERVER['DOCUMENT_ROOT'] . '/config/supabase.php';
    if (file_exists($sbPath) && is_readable($sbPath)) {
        // Проверяем синтаксис
        $syntaxCheck = shell_exec("php -l " . escapeshellarg($sbPath) . " 2>&1");
        $results['tests']['load_supabase']['syntax_check'] = trim($syntaxCheck);
        $results['tests']['load_supabase']['syntax_valid'] = strpos($syntaxCheck, 'No syntax errors') !== false;
        
        // Пробуем загрузить
        ob_start();
        $errorOccurred = false;
        set_error_handler(function($severity, $message, $file, $line) use (&$errorOccurred, &$results) {
            $errorOccurred = true;
            $results['tests']['load_supabase']['load_error'] = "$message in $file:$line";
            return true;
        }, E_ALL);
        
        require_once $sbPath;
        restore_error_handler();
        $output = ob_get_clean();
        
        $results['tests']['load_supabase']['loaded'] = !$errorOccurred;
        $results['tests']['load_supabase']['output'] = $output;
        $results['tests']['load_supabase']['functions_available'] = [
            'getSupabaseClient' => function_exists('getSupabaseClient')
        ];
    } else {
        $results['tests']['load_supabase']['error'] = 'File not found or not readable';
    }
} catch (Throwable $e) {
    $results['tests']['load_supabase']['exception'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
}

// Тест 5: Симуляция функции findConfigFile из wallet.php
$results['tests']['simulate_findConfigFile'] = [];
function testFindConfigFile($filename) {
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
    
    $results = [];
    foreach ($possiblePaths as $path) {
        $realPath = realpath($path);
        $found = $realPath && file_exists($realPath) && is_readable($realPath);
        $results[] = [
            'path' => $path,
            'realpath' => $realPath,
            'found' => $found
        ];
        if ($found) {
            return ['found' => true, 'path' => $realPath, 'searched' => $results];
        }
    }
    
    return ['found' => false, 'searched' => $results];
}

$testResult = testFindConfigFile('database.php');
$results['tests']['simulate_findConfigFile']['database'] = $testResult;

$testResult = testFindConfigFile('supabase.php');
$results['tests']['simulate_findConfigFile']['supabase'] = $testResult;

// Тест 6: Проверка прав доступа к папке config
$configDir = $_SERVER['DOCUMENT_ROOT'] . '/config';
$results['tests']['config_directory'] = [
    'path' => $configDir,
    'exists' => is_dir($configDir),
    'readable' => is_readable($configDir),
    'writable' => is_writable($configDir),
    'permissions' => is_dir($configDir) ? substr(sprintf('%o', fileperms($configDir)), -4) : 'N/A'
];

// Итоговый статус
$allConfigFilesExist = true;
foreach ($results['tests']['config_files'] as $file => $info) {
    if (!$info['exists'] || !$info['readable']) {
        $allConfigFilesExist = false;
        break;
    }
}

$results['summary'] = [
    'all_config_files_ok' => $allConfigFilesExist,
    'database_loaded' => $results['tests']['load_database']['loaded'] ?? false,
    'supabase_loaded' => $results['tests']['load_supabase']['loaded'] ?? false,
    'findConfigFile_works' => ($results['tests']['simulate_findConfigFile']['database']['found'] ?? false) && 
                            ($results['tests']['simulate_findConfigFile']['supabase']['found'] ?? false),
    'recommendations' => []
];

// Рекомендации
if (!$allConfigFilesExist) {
    $results['summary']['recommendations'][] = 'Проверьте права доступа к файлам config/ (должны быть 644)';
}
if (!($results['tests']['load_database']['loaded'] ?? false)) {
    $results['summary']['recommendations'][] = 'Проверьте синтаксис database.php';
}
if (!($results['tests']['load_supabase']['loaded'] ?? false)) {
    $results['summary']['recommendations'][] = 'Проверьте синтаксис supabase.php';
}

echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
