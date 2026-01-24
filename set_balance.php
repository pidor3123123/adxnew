<?php
/**
 * ADX Finance - Установка баланса 1000 USD
 * Скрипт для установки баланса 1000 USD для авторизованного пользователя
 */

header('Content-Type: application/json');

require_once __DIR__ . '/api/auth.php';
require_once __DIR__ . '/config/database.php';

try {
    $token = getAuthorizationToken();
    if (!$token) {
        throw new Exception('Unauthorized', 401);
    }

    $user = getUserByToken($token);
    if (!$user) {
        throw new Exception('Invalid token', 401);
    }

    $db = getDB();
    $db->beginTransaction();
    
    try {
        // Проверяем существование баланса
        $stmt = $db->prepare('SELECT id, available FROM balances WHERE user_id = ? AND currency = ?');
        $stmt->execute([$user['id'], 'USD']);
        $balance = $stmt->fetch();
        
        if ($balance) {
            // Обновляем существующий баланс
            $stmt = $db->prepare('UPDATE balances SET available = ? WHERE id = ?');
            $stmt->execute([1000, $balance['id']]);
        } else {
            // Создаем новый баланс
            $stmt = $db->prepare('INSERT INTO balances (user_id, currency, available) VALUES (?, ?, ?)');
            $stmt->execute([$user['id'], 'USD', 1000]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Balance set to 1000 USD',
            'balance' => 1000
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    $code = $e->getCode();
    if (!is_numeric($code) || $code < 100 || $code > 599) {
        $code = 500;
    }
    $code = (int)$code;
    http_response_code($code);
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
