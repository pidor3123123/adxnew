<?php
/**
 * ADX Finance - API пользователя
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
setCorsHeaders();
header('Access-Control-Allow-Methods: POST, GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

/**
 * Установка заголовков для предотвращения кеширования
 * Используется для динамических данных (баланс, транзакции, профиль)
 */
function setNoCacheHeaders(): void {
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

/**
 * Получение Authorization токена из разных источников
 */
function getAuthorizationToken(): string {
    $token = '';
    
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (isset($headers['Authorization'])) {
            $token = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $token = $headers['authorization'];
        }
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $token = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $token = $headers['authorization'];
        }
    }
    
    $token = str_replace('Bearer ', '', $token);
    return trim($token);
}

function getAuthUser(): ?array {
    $token = getAuthorizationToken();
    
    if (!$token) return null;
    
    $db = getDB();
    $stmt = $db->prepare('
        SELECT u.* FROM users u
        JOIN user_sessions s ON s.user_id = u.id
        WHERE s.token = ? AND s.expires_at > NOW() AND u.is_active = 1
    ');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) unset($user['password']);
    return $user ?: null;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $user = getAuthUser();
    
    if (!$user) {
        throw new Exception('Unauthorized', 401);
    }
    
    switch ($action) {
        case 'profile':
            if ($method === 'GET') {
                setNoCacheHeaders();
                echo json_encode([
                    'success' => true,
                    'user' => $user
                ]);
            } elseif ($method === 'PUT' || $method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                
                $firstName = trim($data['firstName'] ?? $user['first_name'] ?? '');
                $lastName = trim($data['lastName'] ?? $user['last_name'] ?? '');
                $phone = trim($data['phone'] ?? $user['phone'] ?? '');
                $country = trim($data['country'] ?? $user['country'] ?? '');
                
                $db = getDB();
                $stmt = $db->prepare('
                    UPDATE users 
                    SET first_name = ?, last_name = ?, phone = ?, country = ?
                    WHERE id = ?
                ');
                $stmt->execute([$firstName, $lastName, $phone, $country, $user['id']]);
                
                setNoCacheHeaders();
                echo json_encode([
                    'success' => true,
                    'message' => 'Profile updated'
                ]);
            }
            break;
            
        case 'password':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $currentPassword = $data['currentPassword'] ?? '';
            $newPassword = $data['newPassword'] ?? '';
            
            if (!$currentPassword || !$newPassword) {
                throw new Exception('Both passwords required', 400);
            }
            
            if (strlen($newPassword) < 8) {
                throw new Exception('New password must be at least 8 characters', 400);
            }
            
            // Получаем текущий пароль
            $db = getDB();
            $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            $userData = $stmt->fetch();
            
            if (!password_verify($currentPassword, $userData['password'])) {
                throw new Exception('Current password is incorrect', 400);
            }
            
            // Обновляем пароль
            $newHash = password_hash($newPassword, PASSWORD_ARGON2ID);
            $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$newHash, $user['id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Password updated'
            ]);
            break;
            
        case 'sessions':
            $db = getDB();
            $stmt = $db->prepare('
                SELECT id, ip_address, user_agent, created_at, expires_at
                FROM user_sessions
                WHERE user_id = ? AND expires_at > NOW()
                ORDER BY created_at DESC
            ');
            $stmt->execute([$user['id']]);
            $sessions = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'sessions' => $sessions
            ]);
            break;
            
        case 'delete-session':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $sessionId = (int)($data['sessionId'] ?? 0);
            
            if (!$sessionId) {
                throw new Exception('Session ID required', 400);
            }
            
            $db = getDB();
            $stmt = $db->prepare('DELETE FROM user_sessions WHERE id = ? AND user_id = ?');
            $stmt->execute([$sessionId, $user['id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Session deleted'
            ]);
            break;
            
        case 'delete_account':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $password = $data['password'] ?? '';
            
            if (!$password) {
                throw new Exception('Password required for account deletion', 400);
            }
            
            // Проверяем пароль
            $db = getDB();
            $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            $userData = $stmt->fetch();
            
            if (!password_verify($password, $userData['password'])) {
                throw new Exception('Invalid password', 400);
            }
            
            // Удаляем пользователя (все связанные данные удалятся через CASCADE)
            $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Account deleted successfully'
            ]);
            break;
            
        default:
            throw new Exception('Unknown action', 400);
    }
    
} catch (Exception $e) {
    $code = $e->getCode();
    // Валидация и приведение к int
    if (!is_numeric($code) || $code < 100 || $code > 599) {
        $code = 500;
    }
    $code = (int)$code;
    http_response_code($code);
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
