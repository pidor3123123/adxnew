<?php
/**
 * Тест wallet.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$result = ['steps' => []];

try {
    // Step 1: database.php
    require_once __DIR__ . '/../config/database.php';
    $result['steps'][] = '1. database.php loaded';
    
    // Step 2: setCorsHeaders
    // setCorsHeaders(); // Пропускаем, уже установили заголовки
    $result['steps'][] = '2. setCorsHeaders available: ' . (function_exists('setCorsHeaders') ? 'YES' : 'NO');
    
    // Step 3: supabase.php
    require_once __DIR__ . '/../config/supabase.php';
    $result['steps'][] = '3. supabase.php loaded';
    
    // Step 4: auth.php
    require_once __DIR__ . '/auth.php';
    $result['steps'][] = '4. auth.php loaded';
    
    // Step 5: getAuthUser
    $result['steps'][] = '5. getAuthUser available: ' . (function_exists('getAuthUser') ? 'YES' : 'NO');
    
    // Step 6: Try to call getAuthUser
    try {
        $user = getAuthUser();
        $result['steps'][] = '6. getAuthUser result: ' . ($user ? 'User found (ID: ' . ($user['id'] ?? 'unknown') . ')' : 'No user');
    } catch (Exception $e) {
        $result['steps'][] = '6. getAuthUser error: ' . $e->getMessage();
    }
    
    $result['success'] = true;
    
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
    $result['file'] = $e->getFile();
    $result['line'] = $e->getLine();
} catch (Error $e) {
    $result['error'] = 'PHP Error: ' . $e->getMessage();
    $result['file'] = $e->getFile();
    $result['line'] = $e->getLine();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
