<?php
/**
 * ADX Finance - Конфигурация Supabase
 * Подключение к Supabase через REST API для синхронизации с админ панелью
 */

// Конфигурация Supabase
define('SUPABASE_URL', getenv('SUPABASE_URL') ?: 'https://teqnsfxvogniblyvsfun.supabase.co');
define('SUPABASE_SERVICE_ROLE_KEY', getenv('SUPABASE_SERVICE_ROLE_KEY') ?: 'sb_secret_1n5HZHAYXSXLg5wnanntrA_d_t82MzG');

/**
 * Получение Supabase REST API клиента
 */
function getSupabaseClient() {
    static $client = null;
    
    if ($client === null) {
        if (empty(SUPABASE_URL) || empty(SUPABASE_SERVICE_ROLE_KEY)) {
            throw new Exception('Supabase configuration is missing. Please set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY');
        }
        
        $client = new SupabaseClient(SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY);
    }
    
    return $client;
}

/**
 * Класс для работы с Supabase REST API
 */
class SupabaseClient {
    private $url;
    private $apiKey;
    private $baseUrl;
    
    public function __construct(string $url, string $apiKey) {
        $this->url = rtrim($url, '/');
        $this->apiKey = $apiKey;
        $this->baseUrl = $this->url . '/rest/v1';
    }
    
    /**
     * Выполнение HTTP запроса к Supabase
     */
    private function request(string $method, string $endpoint, array $data = null, array $headers = []): array {
        $url = $this->baseUrl . $endpoint;
        
        $defaultHeaders = [
            'apikey: ' . $this->apiKey,
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];
        
        $headers = array_merge($defaultHeaders, $headers);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        if ($data !== null && in_array($method, ['POST', 'PATCH', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error: $error");
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMessage = $decoded['message'] ?? $decoded['error'] ?? "HTTP $httpCode";
            throw new Exception("Supabase API Error: $errorMessage", $httpCode);
        }
        
        return $decoded;
    }
    
    /**
     * Вставка записи в таблицу
     */
    public function insert(string $table, array $data): array {
        return $this->request('POST', "/$table", $data);
    }
    
    /**
     * Обновление записи в таблице
     */
    public function update(string $table, string $column, $value, array $updates): array {
        $endpoint = "/$table?$column=eq." . urlencode($value);
        return $this->request('PATCH', $endpoint, $updates);
    }
    
    /**
     * Upsert (вставка или обновление)
     */
    public function upsert(string $table, array $data, string $onConflict = 'id'): array {
        $headers = ['Prefer: resolution=merge-duplicates'];
        return $this->request('POST', "/$table", $data, $headers);
    }
    
    /**
     * Выборка записей
     */
    public function select(string $table, string $select = '*', array $filters = [], int $limit = null, int $offset = null): array {
        $endpoint = "/$table?select=$select";
        
        foreach ($filters as $key => $value) {
            $endpoint .= "&$key=eq." . urlencode($value);
        }
        
        if ($limit !== null) {
            $endpoint .= "&limit=$limit";
        }
        
        if ($offset !== null) {
            $endpoint .= "&offset=$offset";
        }
        
        return $this->request('GET', $endpoint);
    }
    
    /**
     * Получение одной записи
     */
    public function get(string $table, string $column, $value, string $select = '*'): ?array {
        $result = $this->select($table, $select, [$column => $value], 1);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Удаление записи
     */
    public function delete(string $table, string $column, $value): array {
        $endpoint = "/$table?$column=eq." . urlencode($value);
        return $this->request('DELETE', $endpoint);
    }
    
    /**
     * Конвертация MySQL ID в UUID для Supabase
     * Генерирует детерминированный UUID на основе MySQL ID
     */
    public static function mysqlIdToUuid(int $mysqlId): string {
        // Используем namespace UUID для генерации детерминированного UUID
        $namespace = '6ba7b810-9dad-11d1-80b4-00c04fd430c8'; // DNS namespace
        $name = "mysql_user_$mysqlId";
        
        // Генерируем UUID v5 (детерминированный)
        $hash = sha1($namespace . $name);
        
        return sprintf(
            '%08s-%04s-%04x-%04x-%12s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000,
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            substr($hash, 20, 12)
        );
    }
    
    /**
     * Конвертация UUID обратно в MySQL ID (для поиска)
     * Внимание: это работает только если UUID был сгенерирован через mysqlIdToUuid
     */
    public static function uuidToMysqlId(string $uuid): ?int {
        // Это сложно сделать обратно, поэтому лучше хранить маппинг в отдельной таблице
        // Но для простоты можно попробовать найти по email
        return null;
    }
    
    /**
     * Создание пользователя в auth.users через Admin API
     * Возвращает UUID созданного пользователя
     */
    public function createAuthUser(string $email, string $password, array $metadata = []): string {
        $url = $this->url . '/auth/v1/admin/users';
        
        $data = [
            'email' => $email,
            'password' => $password,
            'email_confirm' => true, // Автоматически подтверждаем email
            'user_metadata' => $metadata
        ];
        
        $headers = [
            'apikey: ' . $this->apiKey,
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error creating auth user: $error");
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMessage = $decoded['message'] ?? $decoded['error'] ?? $decoded['msg'] ?? "HTTP $httpCode";
            // Если пользователь уже существует, это не критическая ошибка
            if (strpos($errorMessage, 'already registered') !== false || strpos($errorMessage, 'already exists') !== false) {
                // Пытаемся найти существующего пользователя
                return $this->findAuthUserByEmail($email);
            }
            throw new Exception("Supabase Auth API Error: $errorMessage", $httpCode);
        }
        
        if (!isset($decoded['user']['id'])) {
            throw new Exception("Failed to create auth user: no ID returned");
        }
        
        return $decoded['user']['id'];
    }
    
    /**
     * Поиск пользователя в auth.users по email
     * Возвращает UUID пользователя или null если не найден
     */
    public function findAuthUserByEmail(string $email): ?string {
        $url = $this->url . '/auth/v1/admin/users';
        $url .= '?email=' . urlencode($email);
        
        $headers = [
            'apikey: ' . $this->apiKey,
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error finding auth user: $error");
        }
        
        if ($httpCode >= 400) {
            return null;
        }
        
        $decoded = json_decode($response, true);
        
        if (isset($decoded['users']) && !empty($decoded['users'])) {
            return $decoded['users'][0]['id'];
        }
        
        return null;
    }
}
