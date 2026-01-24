<?php
/**
 * ADX Finance - Миграция: добавление колонок take_profit и stop_loss
 * Запустите этот файл один раз для добавления недостающих колонок в таблицу orders
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$user = 'root';
$pass = 'M#LXcxB}1';  // Стандартный пароль Open Server
$dbName = 'novatrade';

echo "<h1>ADX Finance - Миграция TP/SL</h1>";
echo "<style>body{font-family:sans-serif;padding:20px;background:#1a1a1f;color:#fff;}h1{color:#6366f1;}pre{background:#2a2a32;padding:15px;border-radius:8px;overflow-x:auto;}.success{color:#22c55e;}.error{color:#ef4444;}.info{color:#6366f1;}</style>";

try {
    // Подключаемся к базе данных
    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "<p class='success'>✓ Подключение к MySQL успешно</p>";
    
    // Проверяем существование колонок
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = ? 
        AND TABLE_NAME = 'orders' 
        AND COLUMN_NAME = ?
    ");
    
    // Проверяем take_profit
    $stmt->execute([$dbName, 'take_profit']);
    $hasTakeProfit = $stmt->fetch()['count'] > 0;
    
    // Проверяем stop_loss
    $stmt->execute([$dbName, 'stop_loss']);
    $hasStopLoss = $stmt->fetch()['count'] > 0;
    
    // Добавляем take_profit, если его нет
    if (!$hasTakeProfit) {
        $pdo->exec("
            ALTER TABLE `orders` 
            ADD COLUMN `take_profit` DECIMAL(20,8) DEFAULT NULL 
            COMMENT 'Take Profit - цена фиксации прибыли'
            AFTER `stop_price`
        ");
        echo "<p class='success'>✓ Колонка 'take_profit' добавлена</p>";
    } else {
        echo "<p class='info'>ℹ Колонка 'take_profit' уже существует</p>";
    }
    
    // Добавляем stop_loss, если его нет
    if (!$hasStopLoss) {
        $pdo->exec("
            ALTER TABLE `orders` 
            ADD COLUMN `stop_loss` DECIMAL(20,8) DEFAULT NULL 
            COMMENT 'Stop Loss - цена ограничения убытков'
            AFTER `take_profit`
        ");
        echo "<p class='success'>✓ Колонка 'stop_loss' добавлена</p>";
    } else {
        echo "<p class='info'>ℹ Колонка 'stop_loss' уже существует</p>";
    }
    
    echo "<hr>";
    echo "<h2 class='success'>✓ Миграция завершена!</h2>";
    echo "<p>Теперь вы можете использовать Take Profit и Stop Loss в быстрой торговле.</p>";
    echo "<p class='info'>Этот файл можно удалить после выполнения миграции.</p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>✗ Ошибка: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<h3>Возможные решения:</h3>";
    echo "<ul>";
    echo "<li>Убедитесь, что MySQL запущен в Open Server Panel</li>";
    echo "<li>Проверьте настройки подключения к базе данных</li>";
    echo "<li>Убедитесь, что таблица 'orders' существует</li>";
    echo "<li>Попробуйте перезапустить Open Server</li>";
    echo "</ul>";
}
