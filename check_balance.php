<?php
/**
 * Проверка баланса пользователя
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

$email = $_GET['email'] ?? 'greg@gmail.com';
$currency = $_GET['currency'] ?? 'USD';

$result = [
    'email' => $email,
    'currency' => $currency
];

try {
    // 1. MySQL
    $db = getDB();
    
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $mysqlUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mysqlUser) {
        $result['mysql_user'] = 'NOT FOUND';
    } else {
        $mysqlUserId = $mysqlUser['id'];
        $result['mysql_user_id'] = $mysqlUserId;
        
        $stmt = $db->prepare('SELECT available, reserved FROM balances WHERE user_id = ? AND currency = ?');
        $stmt->execute([$mysqlUserId, $currency]);
        $mysqlBalance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mysqlBalance) {
            $result['mysql_balance'] = (float)$mysqlBalance['available'];
            $result['mysql_reserved'] = (float)$mysqlBalance['reserved'];
        } else {
            $result['mysql_balance'] = 0;
            $result['mysql_balance_status'] = 'NO RECORD - need to create';
        }
    }
    
    // 2. Supabase
    $supabase = getSupabaseClient();
    $supabaseUserId = $supabase->findAuthUserByEmail($email);
    
    if (!$supabaseUserId) {
        $result['supabase_user'] = 'NOT FOUND';
    } else {
        $result['supabase_user_id'] = $supabaseUserId;
        
        // Wallets
        try {
            $walletResponse = $supabase->rpc('get_wallet_balance', [
                'p_user_id' => $supabaseUserId,
                'p_currency' => $currency
            ]);
            
            if (isset($walletResponse['balance'])) {
                $result['supabase_wallet_balance'] = (float)$walletResponse['balance'];
            } elseif (is_array($walletResponse) && isset($walletResponse[0]['balance'])) {
                $result['supabase_wallet_balance'] = (float)$walletResponse[0]['balance'];
            } else {
                $result['supabase_wallet_balance'] = 0;
            }
        } catch (Exception $e) {
            $result['supabase_wallet_balance'] = 0;
            $result['supabase_wallet_error'] = $e->getMessage();
        }
    }
    
    // 3. Comparison
    $mysqlBal = $result['mysql_balance'] ?? 0;
    $supaBal = $result['supabase_wallet_balance'] ?? 0;
    
    if (abs($mysqlBal - $supaBal) < 0.01) {
        $result['sync_status'] = 'OK - synchronized';
    } else {
        $result['sync_status'] = 'NOT SYNCED';
        $result['difference'] = $mysqlBal - $supaBal;
        $result['action_needed'] = 'Need to sync MySQL balance to Supabase wallets';
    }
    
    $result['success'] = true;
    
} catch (Exception $e) {
    $result['success'] = false;
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
