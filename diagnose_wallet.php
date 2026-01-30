<?php
/**
 * Диагностический скрипт для проверки Wallet API
 * Помогает найти проблемы с Supabase интеграцией
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/supabase.php';
require_once __DIR__ . '/api/auth.php';

echo "=== Диагностика Wallet API ===\n\n";

try {
    // Тест 1: Проверка подключения к Supabase
    echo "--- Тест 1: Подключение к Supabase ---\n";
    try {
        $supabase = getSupabaseClient();
        echo "✓ Supabase клиент инициализирован\n";
        echo "  URL: " . SUPABASE_URL . "\n";
    } catch (Exception $e) {
        echo "✗ Ошибка инициализации Supabase: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    // Тест 2: Проверка существования таблиц
    echo "\n--- Тест 2: Проверка таблиц в Supabase ---\n";
    try {
        // Пытаемся получить данные из таблицы wallets (даже если пусто)
        $result = $supabase->select('wallets', '*', [], 1);
        echo "✓ Таблица 'wallets' существует\n";
    } catch (Exception $e) {
        echo "✗ Таблица 'wallets' не найдена или недоступна: " . $e->getMessage() . "\n";
        echo "  Убедитесь, что SQL схема выполнена в Supabase\n";
    }
    
    try {
        $result = $supabase->select('transactions', '*', [], 1);
        echo "✓ Таблица 'transactions' существует\n";
    } catch (Exception $e) {
        echo "✗ Таблица 'transactions' не найдена или недоступна: " . $e->getMessage() . "\n";
    }
    
    // Тест 3: Проверка RPC функций
    echo "\n--- Тест 3: Проверка RPC функций ---\n";
    
    // Создаем тестовый UUID (несуществующий пользователь)
    $testUserId = '00000000-0000-0000-0000-000000000000';
    
    try {
        $result = $supabase->rpc('get_wallet_balance', [
            'p_user_id' => $testUserId,
            'p_currency' => 'USD'
        ]);
        echo "✓ RPC функция 'get_wallet_balance' существует и работает\n";
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        if (strpos($errorMsg, 'function') !== false || strpos($errorMsg, 'does not exist') !== false || strpos($errorMsg, '404') !== false) {
            echo "✗ RPC функция 'get_wallet_balance' не найдена\n";
            echo "  Ошибка: " . $errorMsg . "\n";
            echo "  Убедитесь, что SQL схема выполнена в Supabase\n";
        } else {
            echo "⚠ RPC функция 'get_wallet_balance' существует, но вернула ошибку: " . $errorMsg . "\n";
            echo "  (Это нормально для несуществующего пользователя)\n";
        }
    }
    
    try {
        $result = $supabase->rpc('get_all_wallet_balances', [
            'p_user_id' => $testUserId
        ]);
        echo "✓ RPC функция 'get_all_wallet_balances' существует и работает\n";
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        if (strpos($errorMsg, 'function') !== false || strpos($errorMsg, 'does not exist') !== false || strpos($errorMsg, '404') !== false) {
            echo "✗ RPC функция 'get_all_wallet_balances' не найдена\n";
            echo "  Ошибка: " . $errorMsg . "\n";
        } else {
            echo "⚠ RPC функция 'get_all_wallet_balances' существует, но вернула ошибку: " . $errorMsg . "\n";
        }
    }
    
    try {
        $result = $supabase->rpc('get_transactions', [
            'p_user_id' => $testUserId,
            'p_limit' => 10,
            'p_offset' => 0
        ]);
        echo "✓ RPC функция 'get_transactions' существует и работает\n";
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        if (strpos($errorMsg, 'function') !== false || strpos($errorMsg, 'does not exist') !== false || strpos($errorMsg, '404') !== false) {
            echo "✗ RPC функция 'get_transactions' не найдена\n";
            echo "  Ошибка: " . $errorMsg . "\n";
        } else {
            echo "⚠ RPC функция 'get_transactions' существует, но вернула ошибку: " . $errorMsg . "\n";
        }
    }
    
    // Тест 4: Проверка синхронизации пользователя
    echo "\n--- Тест 4: Проверка синхронизации пользователя ---\n";
    
    // Получаем тестового пользователя из MySQL
    $db = getDB();
    $stmt = $db->prepare('SELECT id, email FROM users ORDER BY id DESC LIMIT 1');
    $stmt->execute();
    $testUser = $stmt->fetch();
    
    if ($testUser) {
        echo "Найден тестовый пользователь из MySQL:\n";
        echo "  ID: {$testUser['id']}\n";
        echo "  Email: {$testUser['email']}\n";
        
        // Проверяем, есть ли он в Supabase
        $supabaseUser = $supabase->get('users', 'email', $testUser['email']);
        if ($supabaseUser) {
            echo "✓ Пользователь найден в Supabase\n";
            echo "  UUID: {$supabaseUser['id']}\n";
            
            // Проверяем баланс
            try {
                $balance = $supabase->getWalletBalance($supabaseUser['id'], 'USD');
                echo "✓ Баланс получен: " . json_encode($balance, JSON_PRETTY_PRINT) . "\n";
            } catch (Exception $e) {
                echo "✗ Ошибка получения баланса: " . $e->getMessage() . "\n";
            }
        } else {
            echo "✗ Пользователь НЕ найден в Supabase\n";
            echo "  Нужно синхронизировать пользователя\n";
            
            if (function_exists('syncUserToSupabase')) {
                echo "  Пытаемся синхронизировать...\n";
                try {
                    syncUserToSupabase($testUser['id']);
                    echo "✓ Синхронизация выполнена\n";
                    
                    // Проверяем снова
                    $supabaseUser = $supabase->get('users', 'email', $testUser['email']);
                    if ($supabaseUser) {
                        echo "✓ Пользователь теперь найден в Supabase\n";
                        echo "  UUID: {$supabaseUser['id']}\n";
                    }
                } catch (Exception $e) {
                    echo "✗ Ошибка синхронизации: " . $e->getMessage() . "\n";
                }
            } else {
                echo "  Функция syncUserToSupabase не найдена\n";
            }
        }
    } else {
        echo "⚠ Пользователи не найдены в MySQL\n";
    }
    
    // Тест 5: Проверка триггера
    echo "\n--- Тест 5: Проверка триггера (требует реального пользователя) ---\n";
    echo "  Для проверки триггера нужно:\n";
    echo "  1. Иметь пользователя в Supabase\n";
    echo "  2. Вызвать apply_transaction RPC функцию\n";
    echo "  3. Проверить, что баланс обновился автоматически\n";
    
    echo "\n=== Диагностика завершена ===\n";
    echo "\nРекомендации:\n";
    echo "1. Убедитесь, что все RPC функции созданы в Supabase\n";
    echo "2. Убедитесь, что пользователи синхронизированы с Supabase\n";
    echo "3. Проверьте логи сервера для детальных ошибок\n";
    echo "4. Проверьте права доступа к таблицам в Supabase\n";
    
} catch (Exception $e) {
    echo "✗ Критическая ошибка: " . $e->getMessage() . "\n";
    echo "  Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
