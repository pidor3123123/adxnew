<?php
/**
 * Синхронизация баланса из MySQL в Supabase wallets
 * Использует apply_transaction RPC для создания транзакции
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/supabase.php';

header('Content-Type: application/json; charset=utf-8');

// Получаем параметры
$email = $_GET['email'] ?? null;
$currency = $_GET['currency'] ?? 'USD';

if (!$email) {
    echo json_encode(['success' => false, 'error' => 'Email is required. Usage: ?email=user@example.com&currency=USD']);
    exit;
}

try {
    $db = getDB();
    $supabase = getSupabaseClient();
    
    // 1. Получаем пользователя из MySQL
    $stmt = $db->prepare('SELECT id, email, first_name, last_name FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $mysqlUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mysqlUser) {
        throw new Exception("User not found in MySQL: $email");
    }
    
    $mysqlUserId = $mysqlUser['id'];
    
    // 2. Получаем баланс из MySQL
    $stmt = $db->prepare('SELECT available, reserved FROM balances WHERE user_id = ? AND currency = ?');
    $stmt->execute([$mysqlUserId, $currency]);
    $mysqlBalance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $mysqlAvailable = $mysqlBalance ? (float)$mysqlBalance['available'] : 0;
    $mysqlReserved = $mysqlBalance ? (float)$mysqlBalance['reserved'] : 0;
    
    // 3. Получаем Supabase UUID по email
    $supabaseUserId = $supabase->findAuthUserByEmail($email);
    
    if (!$supabaseUserId) {
        throw new Exception("User not found in Supabase auth.users: $email");
    }
    
    // 4. Получаем текущий баланс из Supabase wallets
    $supabaseWallet = null;
    try {
        $response = $supabase->rpc('get_wallet_balance', [
            'p_user_id' => $supabaseUserId,
            'p_currency' => $currency
        ]);
        
        if (isset($response['balance'])) {
            $supabaseWallet = (float)$response['balance'];
        } elseif (is_array($response) && isset($response[0]['balance'])) {
            $supabaseWallet = (float)$response[0]['balance'];
        } else {
            $supabaseWallet = 0;
        }
    } catch (Exception $e) {
        $supabaseWallet = 0;
    }
    
    // 5. Рассчитываем разницу
    $difference = $mysqlAvailable - $supabaseWallet;
    
    $result = [
        'mysql_user_id' => $mysqlUserId,
        'supabase_user_id' => $supabaseUserId,
        'email' => $email,
        'currency' => $currency,
        'mysql_balance' => $mysqlAvailable,
        'supabase_wallet_balance' => $supabaseWallet,
        'difference' => $difference,
        'sync_needed' => abs($difference) > 0.001
    ];
    
    if (abs($difference) > 0.001) {
        // 6. Создаем транзакцию для синхронизации
        $idempotencyKey = 'mysql_sync_' . $mysqlUserId . '_' . $currency . '_' . time();
        
        $transactionResult = $supabase->rpc('apply_transaction', [
            'p_user_id' => $supabaseUserId,
            'p_amount' => $difference,
            'p_type' => 'admin_topup',
            'p_currency' => $currency,
            'p_idempotency_key' => $idempotencyKey,
            'p_metadata' => json_encode([
                'source' => 'mysql_sync',
                'mysql_user_id' => $mysqlUserId,
                'mysql_balance' => $mysqlAvailable,
                'old_supabase_balance' => $supabaseWallet,
                'synced_at' => date('Y-m-d H:i:s')
            ])
        ]);
        
        $result['transaction_result'] = $transactionResult;
        $result['success'] = isset($transactionResult['success']) && $transactionResult['success'];
        
        // 7. Проверяем новый баланс
        try {
            $newResponse = $supabase->rpc('get_wallet_balance', [
                'p_user_id' => $supabaseUserId,
                'p_currency' => $currency
            ]);
            
            if (isset($newResponse['balance'])) {
                $result['new_supabase_balance'] = (float)$newResponse['balance'];
            } elseif (is_array($newResponse) && isset($newResponse[0]['balance'])) {
                $result['new_supabase_balance'] = (float)$newResponse[0]['balance'];
            }
        } catch (Exception $e) {
            $result['balance_check_error'] = $e->getMessage();
        }
        
        $result['message'] = "Balance synchronized. Added $difference $currency to Supabase wallets.";
    } else {
        $result['success'] = true;
        $result['message'] = "Balances are already in sync. No action needed.";
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
