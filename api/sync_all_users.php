<?php
/**
 * ADX Finance - Массовая синхронизация всех пользователей из MySQL в Supabase
 * 
 * Этот скрипт синхронизирует всех существующих пользователей и их балансы
 * из MySQL в Supabase для отображения в админ панели.
 * 
 * Запуск: https://adx.finance/api/sync_all_users.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/sync.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Синхронизация пользователей</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1f; color: #fff; }
        .success { color: #22c55e; }
        .error { color: #ef4444; }
        .info { color: #6366f1; }
        .warning { color: #f59e0b; }
        pre { background: #2a2a32; padding: 15px; border-radius: 8px; overflow-x: auto; }
        h1 { color: #6366f1; }
    </style>
</head>
<body>
<h1>Синхронизация пользователей MySQL → Supabase</h1>
<pre>";

try {
    $db = getDB();
    $supabase = getSupabaseClient();
    
    // Получаем всех пользователей из MySQL
    $stmt = $db->query('SELECT id, email, first_name, last_name, country, is_verified, is_active, created_at FROM users ORDER BY id');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalUsers = count($users);
    $syncedUsers = 0;
    $syncedBalances = 0;
    $errors = [];
    
    echo "Найдено пользователей в MySQL: $totalUsers\n";
    echo "Начинаем синхронизацию...\n\n";
    
    foreach ($users as $user) {
        $userId = $user['id'];
        $email = $user['email'];
        
        echo "\n[Пользователь #$userId] $email\n";
        
        try {
            // Синхронизируем пользователя
            syncUserToSupabase($userId);
            $syncedUsers++;
            echo "  ✓ Пользователь синхронизирован\n";
            
            // Синхронизируем все балансы пользователя
            $balanceStmt = $db->prepare('SELECT currency, available, reserved FROM balances WHERE user_id = ?');
            $balanceStmt->execute([$userId]);
            $balances = $balanceStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($balances)) {
                echo "  ⚠ Балансов не найдено, создаем начальные балансы...\n";
                // Создаем начальные балансы если их нет
                require_once __DIR__ . '/auth.php';
                if (function_exists('createInitialBalances')) {
                    createInitialBalances($userId);
                    // Повторно получаем балансы
                    $balanceStmt->execute([$userId]);
                    $balances = $balanceStmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            
            foreach ($balances as $balance) {
                try {
                    syncBalanceToSupabase($userId, $balance['currency']);
                    $syncedBalances++;
                    echo "  ✓ Баланс {$balance['currency']}: {$balance['available']} (зарезервировано: {$balance['reserved']})\n";
                } catch (Exception $e) {
                    $errors[] = "Ошибка синхронизации баланса {$balance['currency']} для пользователя #$userId: " . $e->getMessage();
                    echo "  ✗ Ошибка баланса {$balance['currency']}: " . $e->getMessage() . "\n";
                }
            }
            
        } catch (Exception $e) {
            $errors[] = "Ошибка синхронизации пользователя #$userId ($email): " . $e->getMessage();
            echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "РЕЗУЛЬТАТЫ СИНХРОНИЗАЦИИ:\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Всего пользователей: $totalUsers\n";
    echo "Синхронизировано пользователей: $syncedUsers\n";
    echo "Синхронизировано балансов: $syncedBalances\n";
    echo "Ошибок: " . count($errors) . "\n";
    
    if (!empty($errors)) {
        echo "\nОШИБКИ:\n";
        foreach ($errors as $error) {
            echo "  ✗ $error\n";
        }
    }
    
    if ($syncedUsers === $totalUsers && empty($errors)) {
        echo "\n<span class='success'>✓ Все пользователи успешно синхронизированы!</span>\n";
    } else {
        echo "\n<span class='warning'>⚠ Синхронизация завершена с ошибками. Проверьте логи выше.</span>\n";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>КРИТИЧЕСКАЯ ОШИБКА: " . htmlspecialchars($e->getMessage()) . "</span>\n";
    echo "\nStack trace:\n" . htmlspecialchars($e->getTraceAsString());
}

echo "</pre>
</body>
</html>";
