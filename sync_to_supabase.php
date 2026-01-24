<?php
/**
 * ADX Finance - Массовая синхронизация данных с Supabase
 * 
 * Этот скрипт синхронизирует существующие данные из MySQL в Supabase
 * Можно запускать вручную или через cron
 * 
 * Использование:
 * php sync_to_supabase.php [--users] [--balances] [--all]
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/supabase.php';
require_once __DIR__ . '/api/sync.php';

// Парсинг аргументов командной строки
$options = getopt('', ['users', 'balances', 'all', 'help']);

if (isset($options['help']) || (empty($options) && php_sapi_name() === 'cli')) {
    echo "ADX Finance - Массовая синхронизация с Supabase\n";
    echo "Использование: php sync_to_supabase.php [опции]\n\n";
    echo "Опции:\n";
    echo "  --users      Синхронизировать только пользователей\n";
    echo "  --balances   Синхронизировать только балансы\n";
    echo "  --all        Синхронизировать все данные (по умолчанию)\n";
    echo "  --help       Показать эту справку\n\n";
    exit(0);
}

$syncUsers = isset($options['users']) || isset($options['all']) || empty($options);
$syncBalances = isset($options['balances']) || isset($options['all']) || empty($options);

echo "=== Начало синхронизации ===\n";
echo "Время: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = getDB();
    $supabase = getSupabaseClient();
    
    // Синхронизация пользователей
    if ($syncUsers) {
        echo "Синхронизация пользователей...\n";
        $stmt = $db->query('SELECT id FROM users ORDER BY id');
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $total = count($users);
        $synced = 0;
        $errors = 0;
        
        foreach ($users as $userId) {
            try {
                syncUserToSupabase($userId);
                $synced++;
                
                if ($synced % 10 === 0) {
                    echo "  Обработано: $synced/$total\n";
                }
            } catch (Exception $e) {
                $errors++;
                echo "  Ошибка для пользователя $userId: " . $e->getMessage() . "\n";
            }
        }
        
        echo "Пользователи: синхронизировано $synced из $total";
        if ($errors > 0) {
            echo ", ошибок: $errors";
        }
        echo "\n\n";
    }
    
    // Синхронизация балансов
    if ($syncBalances) {
        echo "Синхронизация балансов...\n";
        $stmt = $db->query('
            SELECT DISTINCT user_id, currency 
            FROM balances 
            ORDER BY user_id, currency
        ');
        $balances = $stmt->fetchAll();
        
        $total = count($balances);
        $synced = 0;
        $errors = 0;
        
        foreach ($balances as $balance) {
            try {
                syncBalanceToSupabase($balance['user_id'], $balance['currency']);
                $synced++;
                
                if ($synced % 50 === 0) {
                    echo "  Обработано: $synced/$total\n";
                }
            } catch (Exception $e) {
                $errors++;
                echo "  Ошибка для баланса (user_id: {$balance['user_id']}, currency: {$balance['currency']}): " . $e->getMessage() . "\n";
            }
        }
        
        echo "Балансы: синхронизировано $synced из $total";
        if ($errors > 0) {
            echo ", ошибок: $errors";
        }
        echo "\n\n";
    }
    
    echo "=== Синхронизация завершена ===\n";
    echo "Время: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "\n";
    echo "Трассировка:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
