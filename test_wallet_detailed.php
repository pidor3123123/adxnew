<?php
/**
 * ADX Finance - Детальная диагностика wallet.php
 * Проверяет каждый шаг загрузки файлов и инициализации
 * 
 * ВАЖНО: Удалите этот файл после диагностики!
 */

// Включаем буферизацию ПЕРВЫМ делом
if (!ob_get_level()) {
    @ob_start();
}

// Устанавливаем обработчик ошибок для перехвата всех ошибок
$globalError = null;
set_error_handler(function($severity, $message, $file, $line) use (&$globalError) {
    if (in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR])) {
        $globalError = [
            'severity' => $severity,
            'message' => $message,
            'file' => basename($file),
            'line' => $line
        ];
        return true; // Подавляем стандартную обработку
    }
    return false;
}, E_ALL);

register_shutdown_function(function() use (&$globalError) {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR])) {
        $globalError = [
            'severity' => $error['type'],
            'message' => $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ];
    }
});

try {
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
} catch (Throwable $e) {
    // Если заголовки уже отправлены, продолжаем
}

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
        // Пробуем загрузить напрямую
        ob_start();
        $errorOccurred = false;
        $errorMessage = '';
        $errorFile = '';
        $errorLine = 0;
        
        set_error_handler(function($severity, $message, $file, $line) use (&$errorOccurred, &$errorMessage, &$errorFile, &$errorLine) {
            $errorOccurred = true;
            $errorMessage = $message;
            $errorFile = $file;
            $errorLine = $line;
            return true; // Подавляем вывод ошибки
        }, E_ALL);
        
        $loaded = false;
        try {
            require_once $dbPath;
            $loaded = true;
        } catch (Throwable $e) {
            $errorOccurred = true;
            $errorMessage = $e->getMessage();
            $errorFile = $e->getFile();
            $errorLine = $e->getLine();
        }
        
        restore_error_handler();
        $output = ob_get_clean();
        
        $results['tests']['load_database']['loaded'] = $loaded && !$errorOccurred;
        $results['tests']['load_database']['output'] = $output;
        if ($errorOccurred) {
            $results['tests']['load_database']['load_error'] = [
                'message' => $errorMessage,
                'file' => $errorFile,
                'line' => $errorLine
            ];
        }
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
        // Пробуем загрузить напрямую с изоляцией ошибок
        $loadError = null;
        $loadOutput = '';
        
        // Создаем изолированный контекст для загрузки
        $loadTest = function() use ($sbPath, &$loadError, &$loadOutput) {
            ob_start();
            $localError = null;
            
            // Временный обработчик ошибок только для этого файла
            set_error_handler(function($severity, $message, $file, $line) use (&$localError) {
                if (in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR])) {
                    $localError = [
                        'severity' => $severity,
                        'message' => $message,
                        'file' => basename($file),
                        'line' => $line
                    ];
                }
                return true; // Подавляем вывод
            }, E_ALL);
            
            try {
                @include_once $sbPath; // Используем @ для подавления предупреждений
            } catch (ParseError $e) {
                $localError = [
                    'type' => 'ParseError',
                    'message' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ];
            } catch (Throwable $e) {
                $localError = [
                    'type' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ];
            }
            
            restore_error_handler();
            $loadOutput = ob_get_clean();
            return $localError;
        };
        
        $loadError = $loadTest();
        
        $results['tests']['load_supabase']['loaded'] = ($loadError === null);
        $results['tests']['load_supabase']['output'] = substr($loadOutput, 0, 500); // Ограничиваем размер
        if ($loadError !== null) {
            $results['tests']['load_supabase']['load_error'] = $loadError;
        }
        $results['tests']['load_supabase']['functions_available'] = [
            'getSupabaseClient' => function_exists('getSupabaseClient')
        ];
    } else {
        $results['tests']['load_supabase']['error'] = 'File not found or not readable';
    }
} catch (Throwable $e) {
    $results['tests']['load_supabase']['exception'] = [
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
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

// Тест 7: Симуляция полной загрузки как в wallet.php
$results['tests']['simulate_wallet_load'] = [];
try {
    // Используем ту же логику, что и в wallet.php
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
        
        foreach ($possiblePaths as $path) {
            $realPath = realpath($path);
            if ($realPath && file_exists($realPath) && is_readable($realPath)) {
                return $realPath;
            }
        }
        return null;
    }
    
    function testFindAuthFile() {
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
    
    $requiredFiles = [
        'database' => testFindConfigFile('database.php'),
        'supabase' => testFindConfigFile('supabase.php'),
        'auth' => testFindAuthFile()
    ];
    
    $results['tests']['simulate_wallet_load']['files_found'] = [];
    foreach ($requiredFiles as $name => $file) {
        $results['tests']['simulate_wallet_load']['files_found'][$name] = [
            'found' => $file !== null,
            'path' => $file
        ];
    }
    
    // Пробуем загрузить все файлы как в wallet.php
    $loadResults = [];
    foreach ($requiredFiles as $name => $file) {
        if ($file === null) {
            $loadResults[$name] = ['error' => 'File not found'];
            continue;
        }
        
        if (!is_readable($file)) {
            $loadResults[$name] = ['error' => 'File not readable'];
            continue;
        }
        
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
        
        try {
            require_once $file;
            $loadResults[$name] = ['loaded' => true];
        } catch (ParseError $e) {
            $errorOccurred = true;
            $errorMessage = "Parse error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
        } catch (Throwable $e) {
            $errorOccurred = true;
            $errorMessage = "Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
        }
        
        restore_error_handler();
        $output = ob_get_clean();
        
        if ($errorOccurred) {
            $loadResults[$name] = ['error' => $errorMessage, 'output' => $output];
        } else {
            $loadResults[$name] = ['loaded' => true, 'output' => $output];
        }
    }
    
    $results['tests']['simulate_wallet_load']['load_results'] = $loadResults;
    $results['tests']['simulate_wallet_load']['functions_after_load'] = [
        'getDB' => function_exists('getDB'),
        'getSupabaseClient' => function_exists('getSupabaseClient'),
        'getAuthUser' => function_exists('getAuthUser'),
        'setCorsHeaders' => function_exists('setCorsHeaders')
    ];
    
} catch (Throwable $e) {
    $results['tests']['simulate_wallet_load']['exception'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
}

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
    'wallet_simulation_ok' => !isset($results['tests']['simulate_wallet_load']['exception']) && 
                            isset($results['tests']['simulate_wallet_load']['load_results']),
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

// Проверяем, была ли глобальная ошибка
if ($globalError !== null) {
    $results['global_error'] = $globalError;
    $results['note'] = 'Произошла ошибка во время выполнения скрипта, но диагностика продолжена';
}

// Очищаем буфер перед выводом
while (ob_get_level() > 0) {
    @ob_end_clean();
}

// Убеждаемся, что заголовки установлены
if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
}

echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
