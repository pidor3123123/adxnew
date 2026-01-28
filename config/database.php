<?php
/**
 * ADX Finance - Конфигурация базы данных
 */

// Определяем, запущено ли на Hostinger (проверяем по домену или переменной окружения)
$isHostinger = (
    isset($_SERVER['HTTP_HOST']) && 
    (strpos($_SERVER['HTTP_HOST'], 'adx.finance') !== false || 
     strpos($_SERVER['HTTP_HOST'], 'hostinger') !== false)
) || getenv('IS_HOSTINGER') === 'true';

// Используем переменные окружения, если установлены, иначе используем значения по умолчанию
// Для Hostinger используем правильные credentials
if ($isHostinger) {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_NAME', getenv('DB_NAME') ?: 'u680161286_adxfinance');
    define('DB_USER', getenv('DB_USER') ?: 'u680161286_adx');
    define('DB_PASS', getenv('DB_PASS') ?: 'pS1gaUyhCm');
} else {
    // Локальная разработка
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_NAME', getenv('DB_NAME') ?: 'novatrade');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
}
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
            // Выбрасываем исключение вместо die(), чтобы auth.php мог перехватить и вернуть корректный JSON
            throw new PDOException('Ошибка подключения к базе данных: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }
    
    return $pdo;
}
