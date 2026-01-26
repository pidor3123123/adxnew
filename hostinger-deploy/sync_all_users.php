<?php
/**
 * ADX Finance - Синхронизация всех пользователей с Supabase
 * Синхронизирует всех пользователей из MySQL в Supabase
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Синхронизация пользователей - ADX Finance</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #1a1a1f;
            color: #fff;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #2a2a32;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }
        h1 {
            color: #6366f1;
            margin-top: 0;
        }
        .info {
            background: #1e1e24;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 4px solid #6366f1;
        }
        .success {
            background: #1a2e1a;
            border-left-color: #22c55e;
        }
        .error {
            background: #2e1a1a;
            border-left-color: #ef4444;
        }
        .warning {
            background: #2e2a1a;
            border-left-color: #f59e0b;
        }
        .progress {
            margin: 20px 0;
            padding: 15px;
            background: #1e1e24;
            border-radius: 8px;
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #0a0a0f;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 10px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
        }
        .user-item {
            padding: 8px;
            margin: 5px 0;
            background: #1a1a1f;
            border-radius: 4px;
            font-size: 14px;
        }
        .user-item.success {
            color: #22c55e;
        }
        .user-item.error {
            color: #ef4444;
        }
        button {
            background: #6366f1;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
        }
        button:hover {
            background: #4f46e5;
        }
        button:disabled {
            background: #4b5563;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Синхронизация всех пользователей с Supabase</h1>

<?php
// Проверка, был ли отправлен запрос на синхронизацию
$syncRequested = isset($_POST['sync']) && $_POST['sync'] === '1';

if (!$syncRequested) {
    // Показываем форму для запуска синхронизации
    echo '<div class="info">';
    echo '<strong>Внимание:</strong> Этот скрипт синхронизирует всех пользователей из MySQL в Supabase.';
    echo '</div>';
    
    try {
        require_once __DIR__ . '/config/database.php';
        require_once __DIR__ . '/config/supabase.php';
        require_once __DIR__ . '/api/sync.php';
        
        $db = getDB();
        $stmt = $db->query('SELECT COUNT(*) as count FROM users');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $userCount = (int)$result['count'];
        
        echo '<div class="info success">';
        echo '<strong>Статус:</strong> Все компоненты загружены успешно.<br>';
        echo '<strong>Найдено пользователей в MySQL:</strong> ' . $userCount;
        echo '</div>';
        
        echo '<form method="POST">';
        echo '<input type="hidden" name="sync" value="1">';
        echo '<button type="submit">Начать синхронизацию</button>';
        echo '</form>';
    } catch (Throwable $e) {
        echo '<div class="info error">';
        echo '<strong>Ошибка:</strong> ' . htmlspecialchars($e->getMessage());
        echo '</div>';
    }
} else {
    // Выполняем синхронизацию
    try {
        require_once __DIR__ . '/config/database.php';
        require_once __DIR__ . '/config/supabase.php';
        require_once __DIR__ . '/api/sync.php';
        
        $db = getDB();
        
        // Получаем всех пользователей
        $stmt = $db->query('SELECT id, email, first_name, last_name FROM users ORDER BY id');
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total = count($users);
        $synced = 0;
        $errors = 0;
        $errorDetails = [];
        
        echo '<div class="progress">';
        echo '<div>Синхронизация пользователей: <strong id="progress-text">0 / ' . $total . '</strong></div>';
        echo '<div class="progress-bar">';
        echo '<div class="progress-fill" id="progress-fill" style="width: 0%">0%</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div style="max-height: 400px; overflow-y: auto; margin-top: 20px;">';
        
        foreach ($users as $user) {
            try {
                syncUserToSupabase((int)$user['id']);
                $synced++;
                echo '<div class="user-item success">✅ ' . htmlspecialchars($user['email']) . ' (ID: ' . $user['id'] . ') - синхронизирован</div>';
            } catch (Throwable $e) {
                $errors++;
                $errorDetails[] = [
                    'user' => $user['email'],
                    'id' => $user['id'],
                    'error' => $e->getMessage()
                ];
                echo '<div class="user-item error">❌ ' . htmlspecialchars($user['email']) . ' (ID: ' . $user['id'] . ') - ошибка: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            
            // Обновляем прогресс
            $progress = ($synced + $errors) / $total * 100;
            echo '<script>
                document.getElementById("progress-text").textContent = "' . ($synced + $errors) . ' / ' . $total . '";
                document.getElementById("progress-fill").style.width = "' . $progress . '%";
                document.getElementById("progress-fill").textContent = "' . round($progress) . '%";
            </script>';
            flush();
            ob_flush();
        }
        
        echo '</div>';
        
        // Итоговый результат
        echo '<div class="info ' . ($errors === 0 ? 'success' : 'warning') . '" style="margin-top: 20px;">';
        echo '<strong>Синхронизация завершена!</strong><br>';
        echo 'Синхронизировано: <strong>' . $synced . '</strong> из <strong>' . $total . '</strong> пользователей.<br>';
        if ($errors > 0) {
            echo 'Ошибок: <strong>' . $errors . '</strong><br>';
            echo '<details style="margin-top: 10px;">';
            echo '<summary style="cursor: pointer; color: #ef4444;">Показать детали ошибок</summary>';
            echo '<div style="margin-top: 10px; padding: 10px; background: #0a0a0f; border-radius: 4px;">';
            foreach ($errorDetails as $detail) {
                echo '<div style="margin: 5px 0; font-size: 12px;">';
                echo htmlspecialchars($detail['user']) . ' (ID: ' . $detail['id'] . '): ' . htmlspecialchars($detail['error']);
                echo '</div>';
            }
            echo '</div>';
            echo '</details>';
        }
        echo '</div>';
        
        echo '<div style="margin-top: 20px;">';
        echo '<a href="sync_all_users.php" style="color: #6366f1; text-decoration: none;">← Вернуться</a>';
        echo '</div>';
        
    } catch (Throwable $e) {
        echo '<div class="info error">';
        echo '<strong>Критическая ошибка:</strong> ' . htmlspecialchars($e->getMessage());
        echo '<div style="margin-top: 10px; padding: 10px; background: #0a0a0f; border-radius: 4px; font-family: monospace; font-size: 12px;">';
        echo htmlspecialchars($e->getTraceAsString());
        echo '</div>';
        echo '</div>';
    }
}
?>

        <div style="margin-top: 30px; padding: 15px; background: #1e1e24; border-radius: 8px; font-size: 12px; color: #9ca3af;">
            <strong>Информация:</strong><br>
            Этот скрипт синхронизирует всех пользователей из MySQL в Supabase.<br>
            После использования удалите этот файл с сервера по соображениям безопасности.
        </div>
    </div>
</body>
</html>
