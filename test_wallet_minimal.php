<?php
/**
 * Минимальный тест wallet.php
 * Проверяет только базовую функциональность
 */

// Включаем буферизацию
if (!ob_get_level()) {
    @ob_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

$result = [
    'success' => true,
    'message' => 'Minimal test works',
    'php_version' => PHP_VERSION,
    'timestamp' => date('Y-m-d H:i:s')
];

// Тест 1: Проверка базовых функций
$result['tests'] = [
    'ob_start' => function_exists('ob_start'),
    'header' => function_exists('header'),
    'json_encode' => function_exists('json_encode')
];

// Тест 2: Попытка загрузить wallet.php напрямую
$result['load_wallet'] = [];
try {
    $walletPath = __DIR__ . '/api/wallet.php';
    
    if (file_exists($walletPath)) {
        // Читаем первые 50 строк файла для проверки синтаксиса
        $lines = file($walletPath);
        $result['load_wallet'] = [
            'exists' => true,
            'size' => filesize($walletPath),
            'first_line' => trim($lines[0] ?? ''),
            'line_count' => count($lines)
        ];
        
        // Проверяем синтаксис через eval (осторожно!)
        // Но лучше просто проверим, что файл читается
        $content = file_get_contents($walletPath);
        $result['load_wallet']['readable'] = !empty($content);
        $result['load_wallet']['has_opening_tag'] = strpos($content, '<?php') !== false;
    } else {
        $result['load_wallet'] = ['exists' => false, 'path' => $walletPath];
    }
} catch (Throwable $e) {
    $result['load_wallet']['error'] = $e->getMessage();
}

// Очищаем буфер
while (ob_get_level() > 0) {
    @ob_end_clean();
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
