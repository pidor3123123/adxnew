<?php
/**
 * ADX Finance - TOTP (Time-based One-Time Password) класс
 * Реализация RFC 6238 для Google Authenticator
 */

class TOTP {
    /**
     * Алфавит Base32 (RFC 4648)
     */
    private static $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    
    /**
     * Длина кода (стандарт - 6 цифр)
     */
    private static $codeLength = 6;
    
    /**
     * Период времени в секундах (стандарт - 30 сек)
     */
    private static $period = 30;
    
    /**
     * Допуск по времени (количество периодов до и после)
     * 1 = проверяем текущий, предыдущий и следующий период
     */
    private static $discrepancy = 1;
    
    /**
     * Генерация случайного секретного ключа (Base32)
     * @param int $length Длина ключа в байтах (16 = 128 бит, рекомендуемо)
     * @return string Base32-encoded секрет
     */
    public static function generateSecret(int $length = 16): string {
        $secret = '';
        $randomBytes = random_bytes($length);
        
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Chars[ord($randomBytes[$i]) & 31];
        }
        
        return $secret;
    }
    
    /**
     * Декодирование Base32 в бинарные данные
     * @param string $base32 Base32-encoded строка
     * @return string Бинарные данные
     */
    private static function base32Decode(string $base32): string {
        $base32 = strtoupper($base32);
        $base32 = str_replace([' ', '-', '='], '', $base32);
        
        if (empty($base32)) {
            return '';
        }
        
        $buffer = 0;
        $bitsLeft = 0;
        $result = '';
        
        for ($i = 0; $i < strlen($base32); $i++) {
            $char = $base32[$i];
            $val = strpos(self::$base32Chars, $char);
            
            if ($val === false) {
                continue;
            }
            
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        
        return $result;
    }
    
    /**
     * Генерация TOTP кода для заданного времени
     * @param string $secret Base32-encoded секрет
     * @param int|null $timestamp Unix timestamp (null = текущее время)
     * @return string 6-значный код
     */
    public static function getCode(string $secret, ?int $timestamp = null): string {
        if ($timestamp === null) {
            $timestamp = time();
        }
        
        // Вычисляем счётчик времени
        $timeSlice = floor($timestamp / self::$period);
        
        // Преобразуем счётчик в 8-байтовую строку (big-endian)
        $time = pack('N*', 0, $timeSlice);
        
        // Декодируем секрет из Base32
        $secretKey = self::base32Decode($secret);
        
        // Вычисляем HMAC-SHA1
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        
        // Динамическое усечение (Dynamic Truncation)
        $offset = ord($hash[19]) & 0x0F;
        
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % pow(10, self::$codeLength);
        
        // Дополняем нулями слева до 6 цифр
        return str_pad((string) $code, self::$codeLength, '0', STR_PAD_LEFT);
    }
    
    /**
     * Проверка TOTP кода
     * @param string $secret Base32-encoded секрет
     * @param string $code Код для проверки
     * @return bool Верен ли код
     */
    public static function verifyCode(string $secret, string $code): bool {
        // Удаляем пробелы и проверяем длину
        $code = preg_replace('/\s+/', '', $code);
        
        if (strlen($code) !== self::$codeLength) {
            return false;
        }
        
        $currentTime = time();
        
        // Проверяем коды для текущего и соседних периодов
        for ($i = -self::$discrepancy; $i <= self::$discrepancy; $i++) {
            $checkTime = $currentTime + ($i * self::$period);
            $expectedCode = self::getCode($secret, $checkTime);
            
            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Генерация URL для QR-кода (otpauth://)
     * @param string $email Email пользователя
     * @param string $secret Base32-encoded секрет
     * @param string $issuer Название приложения
     * @return string otpauth URL
     */
    public static function getOtpauthUrl(string $email, string $secret, string $issuer = 'ADX Finance'): string {
        $label = rawurlencode($issuer . ':' . $email);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::$codeLength,
            'period' => self::$period
        ]);
        
        return "otpauth://totp/{$label}?{$params}";
    }
    
    /**
     * Генерация URL для получения QR-кода (через Google Charts API)
     * @param string $otpauthUrl otpauth URL
     * @param int $size Размер QR-кода в пикселях
     * @return string URL изображения QR-кода
     */
    public static function getQRCodeUrl(string $otpauthUrl, int $size = 200): string {
        return 'https://chart.googleapis.com/chart?' . http_build_query([
            'chs' => $size . 'x' . $size,
            'chld' => 'M|0',
            'cht' => 'qr',
            'chl' => $otpauthUrl
        ]);
    }
    
    /**
     * Получение всех данных для настройки 2FA
     * @param string $email Email пользователя
     * @return array Данные для настройки
     */
    public static function setupData(string $email): array {
        $secret = self::generateSecret();
        $otpauthUrl = self::getOtpauthUrl($email, $secret);
        $qrCodeUrl = self::getQRCodeUrl($otpauthUrl);
        
        return [
            'secret' => $secret,
            'otpauth_url' => $otpauthUrl,
            'qr_code_url' => $qrCodeUrl
        ];
    }
}
