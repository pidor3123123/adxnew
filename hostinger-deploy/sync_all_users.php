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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #1a1a1f 0%, #2a2a32 100%);
            color: #fff;
            padding: 20px;
            line-height: 1.6;
            min-height: 100vh;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(42, 42, 50, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        
        h1 {
            color: #6366f1;
            margin: 0 0 30px 0;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 32px;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(99, 102, 241, 0.3);
        }
        
        h1::before {
            content: '🔄';
            font-size: 36px;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }
        
        .info {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(99, 102, 241, 0.05) 100%);
            padding: 20px;
            margin: 20px 0;
            border-radius: 12px;
            border-left: 5px solid #6366f1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .info.success {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1) 0%, rgba(34, 197, 94, 0.05) 100%);
            border-left-color: #22c55e;
        }
        
        .info.error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
            border-left-color: #ef4444;
        }
        
        .info.warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(245, 158, 11, 0.05) 100%);
            border-left-color: #f59e0b;
        }
        
        .info strong {
            color: #e5e7eb;
            display: block;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .progress {
            margin: 30px 0;
            padding: 25px;
            background: linear-gradient(135deg, #1e1e24 0%, #25252d 100%);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .progress-text {
            font-size: 18px;
            font-weight: 600;
            color: #e5e7eb;
            margin-bottom: 15px;
        }
        
        .progress-bar {
            width: 100%;
            height: 40px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 20px;
            overflow: hidden;
            margin-top: 15px;
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.3);
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #6366f1, #8b5cf6, #a855f7);
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.5);
        }
        
        .users-list {
            max-height: 500px;
            overflow-y: auto;
            margin-top: 20px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
        }
        
        .user-item {
            padding: 12px 16px;
            margin: 8px 0;
            background: rgba(30, 30, 36, 0.6);
            border-radius: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        
        .user-item:hover {
            background: rgba(30, 30, 36, 0.8);
            transform: translateX(5px);
        }
        
        .user-item.success {
            color: #22c55e;
            border-left-color: #22c55e;
        }
        
        .user-item.error {
            color: #ef4444;
            border-left-color: #ef4444;
        }
        
        .user-icon {
            font-size: 18px;
            width: 24px;
            text-align: center;
        }
        
        button {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: #fff;
            border: none;
            padding: 16px 32px;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        button:hover {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.6);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        button:disabled {
            background: #4b5563;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #6366f1;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
            padding: 10px 20px;
            border-radius: 8px;
            background: rgba(99, 102, 241, 0.1);
        }
        
        .back-link:hover {
            background: rgba(99, 102, 241, 0.2);
            transform: translateX(-5px);
        }
        
        .info-box {
            margin-top: 30px;
            padding: 20px;
            background: rgba(30, 30, 36, 0.6);
            border-radius: 12px;
            font-size: 13px;
            color: #9ca3af;
            border: 1px solid rgba(99, 102, 241, 0.1);
        }
        
        .info-box strong {
            color: #6366f1;
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(99, 102, 241, 0.05) 100%);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #6366f1;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Синхронизация всех пользователей с Supabase</h1>

<?php
// Проверка, был ли отправлен запрос на синхронизацию
$syncRequested = isset($_POST['sync']) && $_POST['sync'] === '1';

if (!$syncRequested) {
    // Показываем форму для запуска синхронизации
    echo '<div class="info">';
    echo '<strong>📋 Что делает этот скрипт?</strong>';
    echo 'Этот скрипт синхронизирует всех пользователей из вашей базы данных MySQL в Supabase, чтобы они появились в админ панели.';
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
        echo '<strong>✅ Готово к синхронизации</strong><br>';
        echo 'Все компоненты загружены успешно.<br>';
        echo '<div style="margin-top: 15px; font-size: 18px; color: #22c55e; font-weight: 600;">';
        echo 'Найдено пользователей в базе данных: <span style="color: #6366f1;">' . $userCount . '</span>';
        echo '</div>';
        echo '</div>';
        
        echo '<form method="POST">';
        echo '<input type="hidden" name="sync" value="1">';
        echo '<button type="submit">🚀 Начать синхронизацию</button>';
        echo '</form>';
    } catch (Throwable $e) {
        echo '<div class="info error">';
        echo '<strong>❌ Ошибка инициализации</strong><br>';
        echo htmlspecialchars($e->getMessage());
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
        echo '<div class="progress-text">Синхронизация пользователей: <strong id="progress-text" style="color: #6366f1;">0 / ' . $total . '</strong></div>';
        echo '<div class="progress-bar">';
        echo '<div class="progress-fill" id="progress-fill" style="width: 0%">0%</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="users-list">';
        
        foreach ($users as $user) {
            $userName = !empty($user['first_name']) ? $user['first_name'] . ' ' . $user['last_name'] : $user['email'];
            
            try {
                syncUserToSupabase((int)$user['id']);
                $synced++;
                echo '<div class="user-item success">';
                echo '<span class="user-icon">✅</span>';
                echo '<span>' . htmlspecialchars($userName) . ' <span style="color: #6b7280;">(' . htmlspecialchars($user['email']) . ')</span></span>';
                echo '</div>';
            } catch (Throwable $e) {
                $errors++;
                $errorDetails[] = [
                    'user' => $user['email'],
                    'id' => $user['id'],
                    'error' => $e->getMessage()
                ];
                echo '<div class="user-item error">';
                echo '<span class="user-icon">❌</span>';
                echo '<span>' . htmlspecialchars($userName) . ' <span style="color: #6b7280;">(' . htmlspecialchars($user['email']) . ')</span> - ' . htmlspecialchars($e->getMessage()) . '</span>';
                echo '</div>';
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
        echo '<div class="stats">';
        echo '<div class="stat-card">';
        echo '<div class="stat-value" style="color: #22c55e;">' . $synced . '</div>';
        echo '<div class="stat-label">Синхронизировано</div>';
        echo '</div>';
        echo '<div class="stat-card">';
        echo '<div class="stat-value" style="color: #ef4444;">' . $errors . '</div>';
        echo '<div class="stat-label">Ошибок</div>';
        echo '</div>';
        echo '<div class="stat-card">';
        echo '<div class="stat-value" style="color: #6366f1;">' . $total . '</div>';
        echo '<div class="stat-label">Всего пользователей</div>';
        echo '</div>';
        echo '</div>';
        
        if ($errors > 0) {
            echo '<div class="info warning" style="margin-top: 30px;">';
            echo '<strong>⚠️ Обнаружены ошибки при синхронизации</strong><br>';
            echo 'Некоторые пользователи не были синхронизированы. Проверьте детали ниже.';
            echo '<details style="margin-top: 15px;">';
            echo '<summary style="cursor: pointer; color: #f59e0b; font-weight: 600; padding: 10px; background: rgba(245, 158, 11, 0.1); border-radius: 8px;">Показать детали ошибок (' . $errors . ')</summary>';
            echo '<div style="margin-top: 15px; padding: 15px; background: rgba(0, 0, 0, 0.3); border-radius: 8px; font-size: 12px;">';
            foreach ($errorDetails as $detail) {
                echo '<div style="margin: 8px 0; padding: 10px; background: rgba(239, 68, 68, 0.1); border-radius: 6px; border-left: 3px solid #ef4444;">';
                echo '<strong>' . htmlspecialchars($detail['user']) . '</strong> (ID: ' . $detail['id'] . ')<br>';
                echo '<span style="color: #9ca3af;">' . htmlspecialchars($detail['error']) . '</span>';
                echo '</div>';
            }
            echo '</div>';
            echo '</details>';
            echo '</div>';
        } else {
            echo '<div class="info success" style="margin-top: 30px;">';
            echo '<strong>✅ Синхронизация завершена успешно!</strong><br>';
            echo 'Все ' . $total . ' пользователей успешно синхронизированы с Supabase. Теперь они должны появиться в админ панели.';
            echo '</div>';
        }
        
        echo '<div style="margin-top: 30px;">';
        echo '<a href="sync_all_users.php" class="back-link">← Вернуться к началу</a>';
        echo '</div>';
        
    } catch (Throwable $e) {
        echo '<div class="info error">';
        echo '<strong>❌ Критическая ошибка</strong><br>';
        echo htmlspecialchars($e->getMessage());
        echo '<div style="margin-top: 15px; padding: 15px; background: rgba(0, 0, 0, 0.3); border-radius: 8px; font-family: monospace; font-size: 12px; color: #9ca3af;">';
        echo htmlspecialchars($e->getTraceAsString());
        echo '</div>';
        echo '</div>';
    }
}
?>

        <div class="info-box">
            <strong>💡 Полезная информация</strong>
            Этот скрипт синхронизирует всех пользователей из MySQL в Supabase.<br>
            После использования удалите этот файл с сервера по соображениям безопасности.
        </div>
    </div>
</body>
</html>
