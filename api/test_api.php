<?php
/**
 * API диагностика
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$result = [
    'php_version' => phpversion(),
    'errors' => [],
    'checks' => []
];

// Проверка database.php
$dbConfigPath = __DIR__ . '/../config/database.php';
if (file_exists($dbConfigPath)) {
    $result['checks']['database_php'] = 'exists';
    try {
        require_once $dbConfigPath;
        $result['checks']['database_constants'] = defined('DB_HOST') ? 'OK' : 'MISSING';
    } catch (Exception $e) {
        $result['errors'][] = 'database.php: ' . $e->getMessage();
    }
} else {
    $result['checks']['database_php'] = 'NOT FOUND';
}

// Проверка supabase.php
$supabasePath = __DIR__ . '/../config/supabase.php';
if (file_exists($supabasePath)) {
    $result['checks']['supabase_php'] = 'exists';
    try {
        require_once $supabasePath;
        $result['checks']['supabase_constants'] = defined('SUPABASE_URL') ? 'OK' : 'MISSING';
    } catch (Exception $e) {
        $result['errors'][] = 'supabase.php: ' . $e->getMessage();
    }
} else {
    $result['checks']['supabase_php'] = 'NOT FOUND';
}

// Проверка auth.php
$authPath = __DIR__ . '/auth.php';
if (file_exists($authPath)) {
    $result['checks']['auth_php'] = 'exists';
} else {
    $result['checks']['auth_php'] = 'NOT FOUND';
}

// Проверка подключения к БД
if (defined('DB_HOST')) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $result['checks']['db_connection'] = 'OK';
    } catch (PDOException $e) {
        $result['checks']['db_connection'] = 'FAILED: ' . $e->getMessage();
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
