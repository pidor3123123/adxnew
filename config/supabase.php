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
     * Upsert по email (для таблицы users)
     * Использует конфликт по уникальному индексу email
     */
    public function upsertByEmail(string $table, array $data): array {
        // Для upsert по email используем PATCH с проверкой существования
        // Сначала проверяем существование по email
        $email = $data['email'] ?? null;
        if (!$email) {
            throw new Exception("Email is required for upsertByEmail");
        }
        
        $existing = $this->get($table, 'email', $email);
        if ($existing) {
            // Обновляем существующую запись
            $id = $existing['id'];
            unset($data['id']); // Не обновляем id
            unset($data['created_at']); // Не обновляем created_at
            return $this->update($table, 'id', $id, $data);
        } else {
            // Вставляем новую запись
            return $this->insert($table, $data);
        }
    }
    
    /**
     * Вызов RPC функции
     */
    public function rpc(string $functionName, array $params = []): array {
        $url = $this->baseUrl . '/rpc/' . $functionName;
        
        $headers = [
            'apikey: ' . $this->apiKey,
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error calling RPC $functionName: $error");
        }
        
        // Логируем запрос для диагностики
        error_log("Supabase RPC Request: $functionName, Params: " . json_encode($params));
        error_log("Supabase RPC Response Code: $httpCode");
        error_log("Supabase RPC Response: " . substr($response, 0, 500)); // Первые 500 символов
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            // Логируем детали ошибки для диагностики
            error_log("Supabase RPC Error ($functionName): HTTP $httpCode");
            error_log("Supabase RPC Error Full Response: " . $response);
            
            $errorMessage = $decoded['message'] ?? $decoded['error'] ?? $decoded['hint'] ?? $decoded['error_description'] ?? "HTTP $httpCode";
            
            // Если это ошибка отсутствия функции, даем более понятное сообщение
            if ($httpCode === 404 || strpos($errorMessage, 'function') !== false || strpos($errorMessage, 'does not exist') !== false) {
                throw new Exception("RPC function '$functionName' not found in Supabase. Please execute SQL schema.", 404);
            }
            
            throw new Exception("Supabase RPC Error ($functionName): $errorMessage", $httpCode);
        }
        
        // Supabase RPC функции могут возвращать JSONB напрямую (не массив)
        // Если ответ - это JSONB объект, он будет распарсен как массив
        // Если ответ - это массив с одним элементом (JSONB), берем первый элемент
        if (is_array($decoded) && count($decoded) === 1 && isset($decoded[0])) {
            // Это массив с одним JSONB объектом - возвращаем объект
            return $decoded[0];
        }
        
        // Если ответ пустой, возвращаем пустой массив
        if ($decoded === null) {
            if ($response === '' || $response === 'null') {
                return [];
            }
            // Пытаемся распарсить как JSON еще раз
            $decoded = json_decode($response, true);
            if ($decoded === null) {
                error_log("Supabase RPC Warning: Could not parse response as JSON: " . substr($response, 0, 200));
                return [];
            }
        }
        
        return $decoded;
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
        
        // Логируем полный ответ для отладки
        error_log("createAuthUser response for $email: HTTP $httpCode, Response: " . json_encode($decoded));
        
        if ($httpCode >= 400) {
            $errorMessage = $decoded['message'] ?? $decoded['error'] ?? $decoded['msg'] ?? $decoded['error_description'] ?? "HTTP $httpCode";
            error_log("createAuthUser error for $email: $errorMessage");
            
            // Если пользователь уже существует, пытаемся найти его
            if (strpos(strtolower($errorMessage), 'already') !== false || 
                strpos(strtolower($errorMessage), 'exists') !== false ||
                strpos(strtolower($errorMessage), 'registered') !== false ||
                $httpCode === 422) { // 422 часто означает, что пользователь уже существует
                
                error_log("User $email already exists, attempting to find existing user...");
                $existingUserId = $this->findAuthUserByEmail($email);
                
                if ($existingUserId) {
                    error_log("Found existing auth user for $email with UUID: $existingUserId");
                    return $existingUserId;
                } else {
                    // Пользователь должен существовать, но не найден - это странно
                    error_log("WARNING: User $email should exist but findAuthUserByEmail returned null");
                    // Попробуем еще раз через небольшую задержку (на случай задержки репликации)
                    sleep(1);
                    $existingUserId = $this->findAuthUserByEmail($email);
                    if ($existingUserId) {
                        return $existingUserId;
                    }
                    throw new Exception("User $email already exists but could not be found: $errorMessage");
                }
            }
            throw new Exception("Supabase Auth API Error: $errorMessage", $httpCode);
        }
        
        // Проверяем разные возможные структуры ответа
        $userId = null;
        if (isset($decoded['user']['id'])) {
            $userId = $decoded['user']['id'];
        } elseif (isset($decoded['id'])) {
            $userId = $decoded['id'];
        } elseif (isset($decoded['data']['user']['id'])) {
            $userId = $decoded['data']['user']['id'];
        } elseif (isset($decoded['data']['id'])) {
            $userId = $decoded['data']['id'];
        }
        
        if (!$userId) {
            // Если ID не найден, но HTTP код успешный, возможно пользователь был создан
            // Попробуем найти его по email
            error_log("WARNING: No ID in response for $email, attempting to find user by email...");
            $foundUserId = $this->findAuthUserByEmail($email);
            if ($foundUserId) {
                error_log("Found user $email with UUID: $foundUserId");
                return $foundUserId;
            }
            throw new Exception("Failed to create auth user: no ID returned. Response: " . json_encode($decoded));
        }
        
        error_log("Successfully created/found auth user for $email with UUID: $userId");
        return $userId;
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
    
    /**
     * Wallet RPC Methods
     */
    
    /**
     * Применить транзакцию (с защитой от double spend)
     * @param string $userId UUID пользователя
     * @param float $amount Сумма транзакции (положительная для пополнения, отрицательная для списания)
     * @param string $type Тип транзакции: 'admin_topup', 'deal_open', 'deal_close', 'profit', 'withdrawal', 'deposit'
     * @param string $currency Валюта (по умолчанию 'USD')
     * @param string|null $idempotencyKey Уникальный ключ для защиты от double spend
     * @param array $metadata Дополнительные метаданные
     * @return array Результат транзакции
     */
    public function applyTransaction(
        string $userId,
        float $amount,
        string $type,
        string $currency = 'USD',
        ?string $idempotencyKey = null,
        array $metadata = []
    ): array {
        $params = [
            'p_user_id' => $userId,
            'p_amount' => (string)$amount, // Преобразуем в строку для точности
            'p_type' => $type,
            'p_currency' => $currency,
            'p_metadata' => $metadata
        ];
        
        if ($idempotencyKey !== null) {
            $params['p_idempotency_key'] = $idempotencyKey;
        }
        
        return $this->rpc('apply_transaction', $params);
    }
    
    /**
     * Получить баланс пользователя
     * @param string $userId UUID пользователя
     * @param string $currency Валюта (по умолчанию 'USD')
     * @return array Баланс пользователя
     */
    public function getWalletBalance(string $userId, string $currency = 'USD'): array {
        return $this->rpc('get_wallet_balance', [
            'p_user_id' => $userId,
            'p_currency' => $currency
        ]);
    }
    
    /**
     * Получить все балансы пользователя
     * @param string $userId UUID пользователя
     * @return array Все балансы пользователя
     */
    public function getAllWalletBalances(string $userId): array {
        return $this->rpc('get_all_wallet_balances', [
            'p_user_id' => $userId
        ]);
    }
    
    /**
     * Получить историю транзакций
     * @param string $userId UUID пользователя
     * @param string|null $currency Валюта (null для всех валют)
     * @param int $limit Лимит записей (по умолчанию 50)
     * @param int $offset Смещение (по умолчанию 0)
     * @return array История транзакций
     */
    public function getTransactions(
        string $userId,
        ?string $currency = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        $params = [
            'p_user_id' => $userId,
            'p_limit' => $limit,
            'p_offset' => $offset
        ];
        
        if ($currency !== null) {
            $params['p_currency'] = $currency;
        }
        
        return $this->rpc('get_transactions', $params);
    }
    
    /**
     * Получить сводку по кошельку (балансы + последние транзакции)
     * @param string $userId UUID пользователя
     * @param string|null $currency Валюта (null для всех валют)
     * @param int $transactionLimit Лимит транзакций (по умолчанию 10)
     * @return array Сводка по кошельку
     */
    public function getWalletSummary(
        string $userId,
        ?string $currency = null,
        int $transactionLimit = 10
    ): array {
        $params = [
            'p_user_id' => $userId,
            'p_transaction_limit' => $transactionLimit
        ];
        
        if ($currency !== null) {
            $params['p_currency'] = $currency;
        }
        
        return $this->rpc('get_wallet_summary', $params);
    }
}
