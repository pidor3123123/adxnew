<?php
/**
 * Простой тест вызова wallet.php
 * Показывает, что именно происходит при вызове
 */

if (!ob_get_level()) {
    @ob_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

$result = [
    'success' => false,
    'message' => 'Testing wallet.php call',
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

// Тест: Попытка вызвать wallet.php через output buffering
$result['tests']['direct_call'] = [];

try {
    // Сохраняем текущий output
    $oldOutput = ob_get_contents();
    ob_clean();
    
    // Устанавливаем переменные как в реальном запросе
    $_GET['action'] = 'balances';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    // Пытаемся включить wallet.php
    $errorOccurred = false;
    $errorMessage = '';
    $output = '';
    
    set_error_handler(function($severity, $message, $file, $line) use (&$errorOccurred, &$errorMessage) {
        if (in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR])) {
            $errorOccurred = true;
            $errorMessage = "$message in $file:$line";
        }
        return true;
    }, E_ALL);
    
    // Пытаемся включить файл
    try {
        @include __DIR__ . '/api/wallet.php';
        $output = ob_get_contents();
    } catch (Throwable $e) {
        $errorOccurred = true;
        $errorMessage = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    }
    
    restore_error_handler();
    ob_end_clean();
    
    if ($oldOutput) {
        ob_start();
        echo $oldOutput;
    }
    
    $result['tests']['direct_call'] = [
        'success' => !$errorOccurred,
        'error' => $errorOccurred ? $errorMessage : null,
        'output_length' => strlen($output),
        'output_preview' => substr($output, 0, 1000),
        'is_json' => $output ? (json_decode($output) !== null) : false
    ];
    
    if ($output && json_decode($output)) {
        $result['tests']['direct_call']['parsed_output'] = json_decode($output, true);
    }
    
} catch (Throwable $e) {
    $result['tests']['direct_call'] = [
        'success' => false,
        'exception' => [
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'type' => get_class($e)
        ]
    ];
}

// Очищаем буфер
while (ob_get_level() > 0) {
    @ob_end_clean();
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
