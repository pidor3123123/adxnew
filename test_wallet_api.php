<?php
/**
 * Тестовый скрипт для проверки Wallet API
 * Тестирует работу с Supabase через RPC функции
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/supabase.php';
require_once __DIR__ . '/api/auth.php';

echo "=== Тестирование Wallet API ===\n\n";

try {
    $supabase = getSupabaseClient();
    echo "✓ Supabase клиент инициализирован\n";
    
    // Тест 1: Проверка существования RPC функций
    echo "\n--- Тест 1: Проверка RPC функций ---\n";
    
    // Получаем тестового пользователя (нужно будет заменить на реальный UUID)
    $testEmail = 'test@example.com';
    $testUserId = $supabase->findAuthUserByEmail($testEmail);
    
    if (!$testUserId) {
        echo "⚠ Тестовый пользователь не найден. Создайте пользователя через регистрацию.\n";
        echo "   Для тестирования используйте реальный UUID пользователя из Supabase.\n\n";
        
        // Показываем инструкции
        echo "Инструкции для тестирования:\n";
        echo "1. Зарегистрируйте нового пользователя на adx.finance\n";
        echo "2. Найдите UUID пользователя в Supabase (таблица users)\n";
        echo "3. Замените \$testUserId в этом скрипте на реальный UUID\n";
        echo "4. Запустите скрипт снова\n\n";
        
        exit(0);
    }
    
    echo "✓ Найден тестовый пользователь: $testUserId\n";
    
    // Тест 2: Получение баланса
    echo "\n--- Тест 2: Получение баланса ---\n";
    try {
        $balance = $supabase->getWalletBalance($testUserId, 'USD');
        echo "✓ Баланс получен: " . json_encode($balance, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } catch (Exception $e) {
        echo "✗ Ошибка получения баланса: " . $e->getMessage() . "\n";
    }
    
    // Тест 3: Получение всех балансов
    echo "\n--- Тест 3: Получение всех балансов ---\n";
    try {
        $balances = $supabase->getAllWalletBalances($testUserId);
        echo "✓ Все балансы получены: " . json_encode($balances, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } catch (Exception $e) {
        echo "✗ Ошибка получения балансов: " . $e->getMessage() . "\n";
    }
    
    // Тест 4: Применение транзакции (пополнение)
    echo "\n--- Тест 4: Применение транзакции (пополнение +100 USD) ---\n";
    try {
        $idempotencyKey = 'test_topup_' . time() . '_' . $testUserId;
        $result = $supabase->applyTransaction(
            $testUserId,
            100.00,
            'admin_topup',
            'USD',
            $idempotencyKey,
            ['description' => 'Test topup', 'source' => 'test_script']
        );
        echo "✓ Транзакция применена: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } catch (Exception $e) {
        echo "✗ Ошибка применения транзакции: " . $e->getMessage() . "\n";
    }
    
    // Тест 5: Проверка защиты от double spend
    echo "\n--- Тест 5: Защита от double spend ---\n";
    try {
        // Пытаемся применить ту же транзакцию с тем же idempotency key
        $result2 = $supabase->applyTransaction(
            $testUserId,
            100.00,
            'admin_topup',
            'USD',
            $idempotencyKey, // Тот же ключ
            ['description' => 'Test topup duplicate', 'source' => 'test_script']
        );
        
        if ($result2['duplicate'] ?? false) {
            echo "✓ Защита от double spend работает! Транзакция помечена как дубликат\n";
            echo "  Результат: " . json_encode($result2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "⚠ Транзакция не помечена как дубликат (возможно, это новая транзакция)\n";
        }
    } catch (Exception $e) {
        echo "✗ Ошибка проверки double spend: " . $e->getMessage() . "\n";
    }
    
    // Тест 6: Получение истории транзакций
    echo "\n--- Тест 6: Получение истории транзакций ---\n";
    try {
        $transactions = $supabase->getTransactions($testUserId, 'USD', 10, 0);
        echo "✓ Транзакции получены: " . json_encode($transactions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } catch (Exception $e) {
        echo "✗ Ошибка получения транзакций: " . $e->getMessage() . "\n";
    }
    
    // Тест 7: Проверка отрицательного баланса (должна быть ошибка)
    echo "\n--- Тест 7: Проверка защиты от отрицательного баланса ---\n";
    try {
        $idempotencyKey2 = 'test_withdraw_' . time() . '_' . $testUserId;
        $result = $supabase->applyTransaction(
            $testUserId,
            -1000000.00, // Очень большая сумма для списания
            'withdrawal',
            'USD',
            $idempotencyKey2,
            ['description' => 'Test withdrawal (should fail)', 'source' => 'test_script']
        );
        echo "⚠ Ошибка: Транзакция прошла, хотя должна была быть отклонена (недостаточно средств)\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Insufficient balance') !== false) {
            echo "✓ Защита от отрицательного баланса работает! Ошибка: " . $e->getMessage() . "\n";
        } else {
            echo "✗ Неожиданная ошибка: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== Тестирование завершено ===\n";
    
} catch (Exception $e) {
    echo "✗ Критическая ошибка: " . $e->getMessage() . "\n";
    echo "  Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
