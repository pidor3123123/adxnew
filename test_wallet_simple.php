<?php
/**
 * ADX Finance - Упрощенная версия wallet.php для диагностики
 * Постепенно добавляет функциональность, чтобы найти проблемное место
 * 
 * ВАЖНО: Удалите этот файл после диагностики!
 */

// Включаем буферизацию ПЕРВЫМ делом
if (!ob_get_level()) {
    @ob_start();
}

// Простая функция для вывода JSON
function simpleJsonOutput($data, $code = 200) {
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

// Тест 1: Базовая проверка
$test = $_GET['test'] ?? 'step1';

try {
    switch ($test) {
        case 'step1':
            // Только базовый вывод
            simpleJsonOutput([
                'success' => true,
                'step' => 1,
                'message' => 'Basic PHP execution works',
                'php_version' => PHP_VERSION
            ]);
            
        case 'step2':
            // Проверка существования файлов с несколькими вариантами путей
            $possiblePaths = [
                'database' => [
                    __DIR__ . '/../config/database.php',
                    __DIR__ . '/../../config/database.php',
                    $_SERVER['DOCUMENT_ROOT'] . '/config/database.php',
                    dirname(__DIR__) . '/config/database.php'
                ],
                'supabase' => [
                    __DIR__ . '/../config/supabase.php',
                    __DIR__ . '/../../config/supabase.php',
                    $_SERVER['DOCUMENT_ROOT'] . '/config/supabase.php',
                    dirname(__DIR__) . '/config/supabase.php'
                ],
                'auth' => [
                    __DIR__ . '/auth.php',
                    $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php'
                ]
            ];
            
            $results = [];
            foreach ($possiblePaths as $name => $paths) {
                $found = false;
                $foundPath = null;
                foreach ($paths as $path) {
                    $realPath = realpath($path);
                    if ($realPath && file_exists($realPath)) {
                        $found = true;
                        $foundPath = $realPath;
                        break;
                    }
                }
                
                $results[$name] = [
                    'exists' => $found,
                    'found_path' => $foundPath,
                    'searched_paths' => $paths,
                    'current_dir' => __DIR__,
                    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A'
                ];
            }
            
            simpleJsonOutput([
                'success' => true,
                'step' => 2,
                'files' => $results
            ]);
            
        case 'step3':
            // Загрузка database.php с поиском по нескольким путям
            $dbPaths = [
                __DIR__ . '/../config/database.php',
                __DIR__ . '/../../config/database.php',
                $_SERVER['DOCUMENT_ROOT'] . '/config/database.php',
                dirname(__DIR__) . '/config/database.php'
            ];
            
            $dbFile = null;
            foreach ($dbPaths as $path) {
                $realPath = realpath($path);
                if ($realPath && file_exists($realPath)) {
                    $dbFile = $realPath;
                    break;
                }
            }
            
            if (!$dbFile) {
                simpleJsonOutput([
                    'success' => false,
                    'step' => 3,
                    'error' => 'database.php not found',
                    'searched_paths' => $dbPaths
                ], 500);
            }
            
            require_once $dbFile;
            
            simpleJsonOutput([
                'success' => true,
                'step' => 3,
                'message' => 'database.php loaded',
                'functions' => [
                    'getDB' => function_exists('getDB'),
                    'setCorsHeaders' => function_exists('setCorsHeaders')
                ]
            ]);
            
        case 'step4':
            // Загрузка database.php и supabase.php с поиском
            function findFile($filename, $baseDir = null) {
                if ($baseDir === null) {
                    $baseDir = __DIR__;
                }
                $paths = [
                    $baseDir . '/../config/' . $filename,
                    $baseDir . '/../../config/' . $filename,
                    $_SERVER['DOCUMENT_ROOT'] . '/config/' . $filename,
                    dirname($baseDir) . '/config/' . $filename
                ];
                foreach ($paths as $path) {
                    $realPath = realpath($path);
                    if ($realPath && file_exists($realPath)) {
                        return $realPath;
                    }
                }
                return null;
            }
            
            $dbFile = findFile('database.php');
            $supabaseFile = findFile('supabase.php');
            
            if (!$dbFile) {
                simpleJsonOutput(['success' => false, 'error' => 'database.php not found'], 500);
            }
            if (!$supabaseFile) {
                simpleJsonOutput(['success' => false, 'error' => 'supabase.php not found'], 500);
            }
            
            require_once $dbFile;
            require_once $supabaseFile;
            
            simpleJsonOutput([
                'success' => true,
                'step' => 4,
                'message' => 'database.php and supabase.php loaded',
                'functions' => [
                    'getDB' => function_exists('getDB'),
                    'getSupabaseClient' => function_exists('getSupabaseClient'),
                    'setCorsHeaders' => function_exists('setCorsHeaders')
                ]
            ]);
            
        case 'step5':
            // Загрузка всех файлов с поиском
            function findFileStep5($filename) {
                if ($filename === 'auth.php') {
                    $paths = [
                        __DIR__ . '/auth.php',
                        $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php'
                    ];
                } else {
                    $paths = [
                        __DIR__ . '/../config/' . $filename,
                        __DIR__ . '/../../config/' . $filename,
                        $_SERVER['DOCUMENT_ROOT'] . '/config/' . $filename,
                        dirname(__DIR__) . '/config/' . $filename
                    ];
                }
                foreach ($paths as $path) {
                    $realPath = realpath($path);
                    if ($realPath && file_exists($realPath)) {
                        return $realPath;
                    }
                }
                return null;
            }
            
            $dbFile = findFileStep5('database.php');
            $supabaseFile = findFileStep5('supabase.php');
            $authFile = findFileStep5('auth.php');
            
            if (!$dbFile) {
                simpleJsonOutput(['success' => false, 'error' => 'database.php not found'], 500);
            }
            if (!$supabaseFile) {
                simpleJsonOutput(['success' => false, 'error' => 'supabase.php not found'], 500);
            }
            if (!$authFile) {
                simpleJsonOutput(['success' => false, 'error' => 'auth.php not found'], 500);
            }
            
            require_once $dbFile;
            require_once $supabaseFile;
            require_once $authFile;
            
            simpleJsonOutput([
                'success' => true,
                'step' => 5,
                'message' => 'All files loaded',
                'functions' => [
                    'getDB' => function_exists('getDB'),
                    'getSupabaseClient' => function_exists('getSupabaseClient'),
                    'getAuthUser' => function_exists('getAuthUser'),
                    'setCorsHeaders' => function_exists('setCorsHeaders')
                ]
            ]);
            
        case 'step6':
            // Полная инициализация как в wallet.php с поиском файлов
            function findFileStep6($filename) {
                if ($filename === 'auth.php') {
                    $paths = [
                        __DIR__ . '/auth.php',
                        $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php'
                    ];
                } else {
                    $paths = [
                        __DIR__ . '/../config/' . $filename,
                        __DIR__ . '/../../config/' . $filename,
                        $_SERVER['DOCUMENT_ROOT'] . '/config/' . $filename,
                        dirname(__DIR__) . '/config/' . $filename
                    ];
                }
                foreach ($paths as $path) {
                    $realPath = realpath($path);
                    if ($realPath && file_exists($realPath)) {
                        return $realPath;
                    }
                }
                return null;
            }
            
            $dbFile = findFileStep6('database.php');
            $supabaseFile = findFileStep6('supabase.php');
            $authFile = findFileStep6('auth.php');
            
            if (!$dbFile || !$supabaseFile || !$authFile) {
                simpleJsonOutput([
                    'success' => false,
                    'error' => 'Required files not found',
                    'database' => $dbFile !== null,
                    'supabase' => $supabaseFile !== null,
                    'auth' => $authFile !== null
                ], 500);
            }
            
            require_once $dbFile;
            require_once $supabaseFile;
            require_once $authFile;
            
            // Устанавливаем заголовки
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
                if (function_exists('setCorsHeaders')) {
                    setCorsHeaders();
                } else {
                    header('Access-Control-Allow-Origin: *');
                }
            }
            
            simpleJsonOutput([
                'success' => true,
                'step' => 6,
                'message' => 'Full initialization complete',
                'ready' => true
            ]);
            
        default:
            simpleJsonOutput([
                'success' => false,
                'error' => 'Unknown test step',
                'available_steps' => ['step1', 'step2', 'step3', 'step4', 'step5', 'step6']
            ], 400);
    }
} catch (ParseError $e) {
    simpleJsonOutput([
        'success' => false,
        'error' => 'Parse Error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'type' => 'ParseError'
    ], 500);
} catch (Error $e) {
    simpleJsonOutput([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'type' => get_class($e)
    ], 500);
} catch (Exception $e) {
    simpleJsonOutput([
        'success' => false,
        'error' => 'Exception: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'type' => get_class($e)
    ], 500);
} catch (Throwable $e) {
    simpleJsonOutput([
        'success' => false,
        'error' => 'Throwable: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'type' => get_class($e)
    ], 500);
}
