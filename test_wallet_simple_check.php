<?php
/**
 * ADX Finance - Простая проверка wallet.php
 * Только проверка файлов, БЕЗ загрузки
 * 
 * ВАЖНО: Удалите этот файл после диагностики!
 */

// Включаем буферизацию
if (!ob_get_level()) {
    @ob_start();
}

// Простейшая обработка ошибок
ini_set('display_errors', 0);
error_reporting(E_ALL);

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
    $realPath = @realpath($path);
    $results['tests']['config_files'][$file] = [
        'path' => $path,
        'realpath' => $realPath ?: false,
        'exists' => @file_exists($path),
        'readable' => @is_readable($path),
        'size' => @file_exists($path) ? @filesize($path) : 0,
        'permissions' => @file_exists($path) ? substr(sprintf('%o', @fileperms($path)), -4) : 'N/A'
    ];
}

// Тест 2: Проверка auth.php
$authPaths = [
    __DIR__ . '/auth.php',
    $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php'
];
$results['tests']['auth_file'] = [];
foreach ($authPaths as $path) {
    $realPath = @realpath($path);
    $results['tests']['auth_file'][] = [
        'path' => $path,
        'realpath' => $realPath ?: false,
        'exists' => @file_exists($path),
        'readable' => @is_readable($path),
        'size' => @file_exists($path) ? @filesize($path) : 0,
        'permissions' => @file_exists($path) ? substr(sprintf('%o', @fileperms($path)), -4) : 'N/A'
    ];
}

// Тест 3: Симуляция findConfigFile (только поиск, без загрузки)
$results['tests']['findConfigFile_simulation'] = [];
function simpleFindConfigFile($filename) {
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $currentDir = __DIR__;
    $parentDir = @dirname($currentDir);
    
    $possiblePaths = [
        $currentDir . '/../config/' . $filename,
        $currentDir . '/../../config/' . $filename,
        $docRoot . '/config/' . $filename,
        $docRoot . '/../config/' . $filename,
        $parentDir . '/config/' . $filename,
        @dirname($parentDir) . '/config/' . $filename,
    ];
    
    foreach ($possiblePaths as $path) {
        $realPath = @realpath($path);
        if ($realPath && @file_exists($realPath) && @is_readable($realPath)) {
            return $realPath;
        }
    }
    return null;
}

$results['tests']['findConfigFile_simulation']['database'] = [
    'found' => simpleFindConfigFile('database.php') !== null,
    'path' => simpleFindConfigFile('database.php')
];

$results['tests']['findConfigFile_simulation']['supabase'] = [
    'found' => simpleFindConfigFile('supabase.php') !== null,
    'path' => simpleFindConfigFile('supabase.php')
];

// Тест 4: Проверка папки config
$configDir = $_SERVER['DOCUMENT_ROOT'] . '/config';
$results['tests']['config_directory'] = [
    'path' => $configDir,
    'exists' => @is_dir($configDir),
    'readable' => @is_readable($configDir),
    'writable' => @is_writable($configDir),
    'permissions' => @is_dir($configDir) ? substr(sprintf('%o', @fileperms($configDir)), -4) : 'N/A'
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
    'findConfigFile_works' => ($results['tests']['findConfigFile_simulation']['database']['found'] ?? false) && 
                            ($results['tests']['findConfigFile_simulation']['supabase']['found'] ?? false),
    'recommendations' => []
];

if (!$allConfigFilesExist) {
    $results['summary']['recommendations'][] = 'Проверьте права доступа к файлам config/ (должны быть 644)';
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
