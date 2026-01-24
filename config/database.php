<?php
/**
 * ADX Finance - Конфигурация базы данных
 */

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'novatrade');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

/**
 * Получение разрешенных CORS origins
 */
function getAllowedOrigins(): array {
    $origins = getenv('ALLOWED_ORIGINS');
    if (empty($origins)) {
        return ['*']; // Fallback для разработки
    }
    return array_map('trim', explode(',', $origins));
}

/**
 * Установка CORS заголовков
 */
function setCorsHeaders(): void {
    $allowedOrigins = getAllowedOrigins();
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array('*', $allowedOrigins)) {
        header('Access-Control-Allow-Origin: *');
    } elseif ($origin && in_array($origin, $allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } elseif (!empty($allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . $allowedOrigins[0]);
    }
}

/**
 * Получение PDO подключения к базе данных
 */
function getDB(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Ошибка подключения к базе данных']));
        }
    }
    
    return $pdo;
}
