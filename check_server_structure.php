<?php
/**
 * ADX Finance - Диагностика структуры файлов на сервере
 * Показывает реальную структуру файлов и помогает найти проблему с путями
 * 
 * ВАЖНО: Удалите этот файл после диагностики!
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Диагностика структуры сервера - ADX Finance</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f0f0f;
            color: #e0e0e0;
            padding: 20px;
            line-height: 1.6;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #333;
        }
        h1 { color: #fff; margin-bottom: 10px; }
        .section {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #333;
        }
        .section-title {
            font-weight: 600;
            color: #fff;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .info-item {
            padding: 10px;
            margin-bottom: 8px;
            background: #252525;
            border-radius: 4px;
            border-left: 3px solid #667eea;
        }
        .info-item.success { border-left-color: #28a745; }
        .info-item.error { border-left-color: #dc3545; background: #2a1a1a; }
        .info-label { color: #999; font-size: 12px; margin-bottom: 5px; }
        .info-value {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            word-break: break-all;
            color: #e0e0e0;
        }
        .file-list {
            margin-top: 10px;
            padding: 10px;
            background: #0f0f0f;
            border-radius: 4px;
            max-height: 300px;
            overflow-y: auto;
        }
        .file-item {
            padding: 5px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
        .file-item.exists { color: #28a745; }
        .file-item.missing { color: #dc3545; }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Диагностика структуры сервера</h1>
            <p style="color: #999;">Проверка реальной структуры файлов на сервере</p>
        </div>
        
        <?php
        // Секция 1: Информация о сервере
        echo '<div class="section">';
        echo '<div class="section-title">Информация о сервере</div>';
        
        $info = [
            'PHP Version' => PHP_VERSION,
            'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
            'Script Filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'N/A',
            'Current File' => __FILE__,
            'Current Dir' => __DIR__,
            'Realpath Current Dir' => realpath(__DIR__),
        ];
        
        foreach ($info as $label => $value) {
            $class = 'info-item';
            echo "<div class='$class'>";
            echo "<div class='info-label'>$label:</div>";
            echo "<div class='info-value'>" . htmlspecialchars($value) . "</div>";
            echo "</div>";
        }
        echo '</div>';
        
        // Секция 2: Проверка структуры папок
        echo '<div class="section">';
        echo '<div class="section-title">Структура папок</div>';
        
        $dirs = [
            'Current Dir' => __DIR__,
            'Parent Dir' => dirname(__DIR__),
            'Config Dir (relative)' => __DIR__ . '/../config',
            'Config Dir (from parent)' => dirname(__DIR__) . '/config',
            'Config Dir (from DOCUMENT_ROOT)' => ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/config',
            'API Dir' => __DIR__ . '/api',
            'API Dir (from DOCUMENT_ROOT)' => ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api',
        ];
        
        foreach ($dirs as $label => $path) {
            $exists = is_dir($path);
            $readable = is_readable($path);
            $class = $exists ? 'info-item success' : 'info-item error';
            
            echo "<div class='$class'>";
            echo "<div class='info-label'>$label:</div>";
            echo "<div class='info-value'>" . htmlspecialchars($path) . "</div>";
            echo "<div style='margin-top: 5px; font-size: 11px; color: #999;'>";
            echo "Существует: " . ($exists ? '✓ Да' : '✗ Нет') . " | ";
            echo "Читаемый: " . ($readable ? '✓ Да' : '✗ Нет');
            echo "</div>";
            echo "</div>";
        }
        echo '</div>';
        
        // Секция 3: Поиск config файлов
        echo '<div class="section">';
        echo '<div class="section-title">Поиск config файлов</div>';
        
        $configFiles = ['database.php', 'supabase.php', 'webhook.php'];
        $searchPaths = [
            __DIR__ . '/../config/',
            __DIR__ . '/../../config/',
            dirname(__DIR__) . '/config/',
            ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/config/',
            ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/../config/',
        ];
        
        $foundFiles = [];
        
        foreach ($configFiles as $file) {
            echo "<div class='info-item'>";
            echo "<div class='info-label'><strong>$file</strong></div>";
            echo "<div class='file-list'>";
            
            $found = false;
            foreach ($searchPaths as $basePath) {
                $fullPath = $basePath . $file;
                $realPath = realpath($fullPath);
                
                if ($realPath && file_exists($realPath)) {
                    $found = true;
                    $foundFiles[$file] = $realPath;
                    echo "<div class='file-item exists'>✓ НАЙДЕН: $realPath</div>";
                    break;
                } else {
                    echo "<div class='file-item missing'>✗ Не найден: $fullPath</div>";
                }
            }
            
            if (!$found) {
                echo "<div style='color: #dc3545; margin-top: 5px;'>⚠ Файл не найден ни по одному из путей!</div>";
            }
            
            echo "</div>";
            echo "</div>";
        }
        echo '</div>';
        
        // Секция 4: Содержимое текущей директории
        echo '<div class="section">';
        echo '<div class="section-title">Содержимое текущей директории (' . __DIR__ . ')</div>';
        
        $currentDirFiles = @scandir(__DIR__);
        if ($currentDirFiles) {
            echo "<div class='file-list'>";
            foreach ($currentDirFiles as $item) {
                if ($item === '.' || $item === '..') continue;
                $fullPath = __DIR__ . '/' . $item;
                $isDir = is_dir($fullPath);
                $icon = $isDir ? '📁' : '📄';
                echo "<div class='file-item'>$icon $item " . ($isDir ? '(папка)' : '(' . number_format(filesize($fullPath)) . ' байт)') . "</div>";
            }
            echo "</div>";
        } else {
            echo "<div class='info-item error'>Не удалось прочитать директорию</div>";
        }
        echo '</div>';
        
        // Секция 5: Содержимое родительской директории
        $parentDir = dirname(__DIR__);
        if (is_dir($parentDir) && is_readable($parentDir)) {
            echo '<div class="section">';
            echo '<div class="section-title">Содержимое родительской директории (' . $parentDir . ')</div>';
            
            $parentDirFiles = @scandir($parentDir);
            if ($parentDirFiles) {
                echo "<div class='file-list'>";
                foreach ($parentDirFiles as $item) {
                    if ($item === '.' || $item === '..') continue;
                    $fullPath = $parentDir . '/' . $item;
                    $isDir = is_dir($fullPath);
                    $icon = $isDir ? '📁' : '📄';
                    echo "<div class='file-item'>$icon $item " . ($isDir ? '(папка)' : '(' . number_format(filesize($fullPath)) . ' байт)') . "</div>";
                }
                echo "</div>";
            }
            echo '</div>';
        }
        
        // Секция 6: Рекомендации
        echo '<div class="section">';
        echo '<div class="section-title">Рекомендации</div>';
        
        if (empty($foundFiles)) {
            echo '<div class="warning">';
            echo '<strong>⚠️ КРИТИЧНО:</strong> Файлы config/ не найдены!<br><br>';
            echo '<strong>Решение:</strong><br>';
            echo '1. Убедитесь, что папка <code>config/</code> загружена на сервер<br>';
            echo '2. Проверьте, что файлы находятся в <code>' . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . '/config/</code><br>';
            echo '3. Проверьте права доступа к файлам (должны быть 644)<br>';
            echo '4. Если файлы в другом месте, обновите пути в <code>api/wallet.php</code>';
            echo '</div>';
        } else {
            echo '<div class="info-item success">';
            echo '<strong>✓ Файлы config найдены!</strong><br>';
            echo 'Используйте эти пути в <code>api/wallet.php</code>:<br>';
            foreach ($foundFiles as $file => $path) {
                echo '<code style="font-size: 11px;">' . htmlspecialchars($path) . '</code><br>';
            }
            echo '</div>';
        }
        echo '</div>';
        ?>
        
        <div class="warning">
            <strong>⚠️ ВАЖНО:</strong>
            Этот файл предназначен только для диагностики. Удалите его после использования для безопасности!
        </div>
    </div>
</body>
</html>
