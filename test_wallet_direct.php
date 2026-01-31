<?php
/**
 * Прямой тест wallet.php
 * Пытается вызвать wallet.php и показать результат
 */

// Включаем буферизацию
if (!ob_get_level()) {
    @ob_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

$result = [
    'success' => false,
    'message' => 'Testing wallet.php',
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

// Тест 1: Проверка существования wallet.php
$walletPath = __DIR__ . '/api/wallet.php';
$result['tests']['file_exists'] = [
    'exists' => file_exists($walletPath),
    'path' => $walletPath,
    'readable' => is_readable($walletPath),
    'size' => file_exists($walletPath) ? filesize($walletPath) : 0
];

// Тест 2: Попытка прочитать первые строки файла
if (file_exists($walletPath)) {
    try {
        $content = file_get_contents($walletPath, false, null, 0, 500);
        $result['tests']['file_read'] = [
            'success' => true,
            'first_chars' => substr($content, 0, 200),
            'has_php_tag' => strpos($content, '<?php') !== false
        ];
    } catch (Throwable $e) {
        $result['tests']['file_read'] = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Тест 3: Попытка включить wallet.php (без выполнения)
// Это проверит синтаксис
$result['tests']['syntax_check'] = [];
try {
    // Сохраняем текущий output buffer
    $oldOutput = ob_get_contents();
    ob_clean();
    
    // Пытаемся включить файл с подавлением ошибок
    $syntaxOk = true;
    $syntaxError = null;
    
    set_error_handler(function($severity, $message, $file, $line) use (&$syntaxOk, &$syntaxError) {
        if (in_array($severity, [E_PARSE, E_COMPILE_ERROR])) {
            $syntaxOk = false;
            $syntaxError = "$message in $file:$line";
        }
        return true;
    }, E_ALL);
    
    // Пытаемся прочитать файл как PHP (но не выполняем)
    $testCode = "<?php\n" . file_get_contents($walletPath);
    
    // Проверяем базовый синтаксис через token_get_all
    $tokens = @token_get_all($testCode);
    $hasSyntaxError = false;
    foreach ($tokens as $token) {
        if (is_array($token) && $token[0] === T_PARSE_ERROR) {
            $hasSyntaxError = true;
            break;
        }
    }
    
    restore_error_handler();
    ob_end_clean();
    
    if ($oldOutput) {
        ob_start();
        echo $oldOutput;
    }
    
    $result['tests']['syntax_check'] = [
        'success' => !$hasSyntaxError && $syntaxOk,
        'error' => $syntaxError,
        'token_check' => !$hasSyntaxError
    ];
    
} catch (Throwable $e) {
    $result['tests']['syntax_check'] = [
        'success' => false,
        'error' => $e->getMessage(),
        'type' => get_class($e)
    ];
}

// Тест 4: Проверка через прямой HTTP запрос к wallet.php
$result['tests']['http_request'] = [];
try {
    $url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/api/wallet.php?action=balances';
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = @curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $result['tests']['http_request'] = [
        'success' => $httpCode === 200,
        'http_code' => $httpCode,
        'curl_error' => $curlError ?: null,
        'response_length' => strlen($response ?? ''),
        'response_preview' => substr($response ?? '', 0, 500),
        'is_json' => $response ? (json_decode($response) !== null) : false
    ];
    
    if ($response && json_decode($response)) {
        $result['tests']['http_request']['parsed_response'] = json_decode($response, true);
    }
    
} catch (Throwable $e) {
    $result['tests']['http_request'] = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

// Очищаем буфер
while (ob_get_level() > 0) {
    @ob_end_clean();
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
