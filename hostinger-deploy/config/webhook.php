<?php
/**
 * ADX Finance - Конфигурация Webhook
 * Секретный ключ для валидации webhook запросов от админ панели
 */

// Секретный ключ для webhook (должен совпадать с WEBHOOK_SECRET в Vercel)
define('WEBHOOK_SECRET', getenv('WEBHOOK_SECRET') ?: 'novatrade-webhook-secret-2024');

// URL webhook endpoint (опционально, для информации)
define('WEBHOOK_URL', getenv('WEBHOOK_URL') ?: 'https://adx.finance/api/webhook.php');
