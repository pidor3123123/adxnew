<?php
/**
 * Синхронизация баланса из MySQL в Supabase wallets
 * 
 * Использование: sync_balance.php?email=greg@gmail.com&currency=USD
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/config/supabase.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Config error: ' . $e->getMessage()]);
    exit;
}

$email = $_GET['email'] ?? null;
$currency = $_GET['currency'] ?? 'USD';

if (!$email) {
    echo json_encode([
        'success' => false,
        'error' => 'Email required',
        'usage' => 'sync_balance.php?email=greg@gmail.com&currency=USD'
    ]);
    exit;
}

$result = ['email' => $email, 'currency' => $currency];

try {
    $db = getDB();
    $supabase = getSupabaseClient();
    
    // 1. Get MySQL user and balance
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $mysqlUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mysqlUser) {
        throw new Exception("User not found in MySQL: $email");
    }
    
    $mysqlUserId = $mysqlUser['id'];
    $result['mysql_user_id'] = $mysqlUserId;
    
    $stmt = $db->prepare('SELECT available FROM balances WHERE user_id = ? AND currency = ?');
    $stmt->execute([$mysqlUserId, $currency]);
    $mysqlBalance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $mysqlAvailable = $mysqlBalance ? (float)$mysqlBalance['available'] : 0;
    $result['mysql_balance'] = $mysqlAvailable;
    
    if ($mysqlAvailable == 0) {
        throw new Exception("MySQL balance is 0. Nothing to sync. Add balance first in phpMyAdmin.");
    }
    
    // 2. Get Supabase UUID
    $supabaseUserId = $supabase->findAuthUserByEmail($email);
    
    if (!$supabaseUserId) {
        throw new Exception("User not found in Supabase. Need to sync user first.");
    }
    
    $result['supabase_user_id'] = $supabaseUserId;
    
    // 3. Get current Supabase wallet balance
    $currentWallet = 0;
    try {
        $walletResponse = $supabase->rpc('get_wallet_balance', [
            'p_user_id' => $supabaseUserId,
            'p_currency' => $currency
        ]);
        
        if (isset($walletResponse['balance'])) {
            $currentWallet = (float)$walletResponse['balance'];
        } elseif (is_array($walletResponse) && isset($walletResponse[0]['balance'])) {
            $currentWallet = (float)$walletResponse[0]['balance'];
        }
    } catch (Exception $e) {
        // Wallet doesn't exist
    }
    
    $result['old_supabase_balance'] = $currentWallet;
    
    // 4. Calculate difference
    $difference = $mysqlAvailable - $currentWallet;
    $result['difference'] = $difference;
    
    if (abs($difference) < 0.01) {
        $result['success'] = true;
        $result['message'] = 'Already synchronized';
        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }
    
    // 5. Create transaction to sync
    $idempotencyKey = 'sync_' . $mysqlUserId . '_' . $currency . '_' . time();
    
    $transactionResult = $supabase->rpc('apply_transaction', [
        'p_user_id' => $supabaseUserId,
        'p_amount' => $difference,
        'p_type' => 'admin_topup',
        'p_currency' => $currency,
        'p_idempotency_key' => $idempotencyKey,
        'p_metadata' => json_encode([
            'source' => 'mysql_sync',
            'mysql_user_id' => $mysqlUserId,
            'synced_at' => date('Y-m-d H:i:s')
        ])
    ]);
    
    $result['transaction'] = $transactionResult;
    
    // 6. Verify new balance
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
        $result['verify_error'] = $e->getMessage();
    }
    
    $result['success'] = true;
    $result['message'] = "Synced! Added $difference $currency to Supabase wallet.";
    
} catch (Exception $e) {
    $result['success'] = false;
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
