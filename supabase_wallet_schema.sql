-- ================================================
-- ADX Finance - Wallet Schema for Supabase
-- Single Source of Truth: Supabase PostgreSQL
-- ================================================

-- ----------------------------
-- Таблица wallets
-- ----------------------------
CREATE TABLE IF NOT EXISTS wallets (
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    balance DECIMAL(20, 8) NOT NULL DEFAULT 0 CHECK (balance >= 0),
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (user_id, currency),
    CONSTRAINT check_balance_non_negative CHECK (balance >= 0)
);

CREATE INDEX IF NOT EXISTS idx_wallets_user_currency ON wallets(user_id, currency);
CREATE INDEX IF NOT EXISTS idx_wallets_updated_at ON wallets(updated_at);

-- ----------------------------
-- Таблица transactions
-- ----------------------------
CREATE TABLE IF NOT EXISTS transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    amount DECIMAL(20, 8) NOT NULL,
    type VARCHAR(50) NOT NULL, -- 'admin_topup', 'deal_open', 'deal_close', 'profit', 'withdrawal', 'deposit'
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    idempotency_key VARCHAR(255) UNIQUE, -- Защита от double spend
    metadata JSONB DEFAULT '{}'::JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT check_amount_not_zero CHECK (amount != 0)
);

CREATE INDEX IF NOT EXISTS idx_transactions_user_id ON transactions(user_id);
CREATE INDEX IF NOT EXISTS idx_transactions_type ON transactions(type);
CREATE INDEX IF NOT EXISTS idx_transactions_created_at ON transactions(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_transactions_idempotency ON transactions(idempotency_key) WHERE idempotency_key IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_transactions_user_currency ON transactions(user_id, currency);

-- ----------------------------
-- Триггер для автоматического обновления баланса
-- ----------------------------
CREATE OR REPLACE FUNCTION update_wallet_balance()
RETURNS TRIGGER AS $$
DECLARE
    v_new_balance DECIMAL;
BEGIN
    -- Вставляем или обновляем баланс
    INSERT INTO wallets (user_id, balance, currency, updated_at)
    VALUES (NEW.user_id, NEW.amount, NEW.currency, NOW())
    ON CONFLICT (user_id, currency)
    DO UPDATE SET
        balance = wallets.balance + NEW.amount,
        updated_at = NOW();
    
    -- Получаем новый баланс для проверки
    SELECT balance INTO v_new_balance
    FROM wallets
    WHERE user_id = NEW.user_id AND currency = NEW.currency;
    
    -- Проверка на отрицательный баланс
    IF v_new_balance < 0 THEN
        RAISE EXCEPTION 'Insufficient balance: balance cannot be negative. Current balance: %, Transaction amount: %', v_new_balance - NEW.amount, NEW.amount;
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Удаляем триггер, если существует
DROP TRIGGER IF EXISTS trigger_update_wallet_balance ON transactions;

-- Создаем триггер
CREATE TRIGGER trigger_update_wallet_balance
AFTER INSERT ON transactions
FOR EACH ROW
EXECUTE FUNCTION update_wallet_balance();

-- ----------------------------
-- RPC функция: apply_transaction
-- Основная функция для применения транзакции с защитой от double spend
-- ----------------------------
CREATE OR REPLACE FUNCTION apply_transaction(
    p_user_id UUID,
    p_amount DECIMAL,
    p_type VARCHAR,
    p_currency VARCHAR DEFAULT 'USD',
    p_idempotency_key VARCHAR DEFAULT NULL,
    p_metadata JSONB DEFAULT '{}'::JSONB
)
RETURNS JSONB AS $$
DECLARE
    v_transaction_id UUID;
    v_new_balance DECIMAL;
    v_existing_transaction UUID;
    v_current_balance DECIMAL;
BEGIN
    -- Проверка idempotency key (защита от double spend)
    IF p_idempotency_key IS NOT NULL THEN
        SELECT id INTO v_existing_transaction
        FROM transactions
        WHERE idempotency_key = p_idempotency_key;
        
        IF v_existing_transaction IS NOT NULL THEN
            -- Транзакция уже существует - возвращаем существующую
            SELECT COALESCE(balance, 0) INTO v_new_balance
            FROM wallets
            WHERE user_id = p_user_id AND currency = p_currency;
            
            RETURN jsonb_build_object(
                'success', true,
                'transaction_id', v_existing_transaction,
                'balance', v_new_balance,
                'duplicate', true,
                'message', 'Transaction already processed'
            );
        END IF;
    END IF;
    
    -- Проверка баланса для отрицательных операций
    IF p_amount < 0 THEN
        -- Блокируем строку для предотвращения race condition
        SELECT COALESCE(balance, 0) INTO v_current_balance
        FROM wallets
        WHERE user_id = p_user_id AND currency = p_currency
        FOR UPDATE;
        
        IF v_current_balance + p_amount < 0 THEN
            RAISE EXCEPTION 'Insufficient balance: current balance is %, requested amount is %', v_current_balance, ABS(p_amount);
        END IF;
    END IF;
    
    -- Вставка транзакции (триггер автоматически обновит баланс)
    INSERT INTO transactions (
        user_id,
        amount,
        type,
        currency,
        idempotency_key,
        metadata
    ) VALUES (
        p_user_id,
        p_amount,
        p_type,
        p_currency,
        p_idempotency_key,
        p_metadata
    ) RETURNING id INTO v_transaction_id;
    
    -- Получаем новый баланс
    SELECT COALESCE(balance, 0) INTO v_new_balance
    FROM wallets
    WHERE user_id = p_user_id AND currency = p_currency;
    
    RETURN jsonb_build_object(
        'success', true,
        'transaction_id', v_transaction_id,
        'balance', v_new_balance,
        'duplicate', false,
        'message', 'Transaction processed successfully'
    );
EXCEPTION
    WHEN unique_violation THEN
        -- Idempotency key уже существует (race condition)
        IF p_idempotency_key IS NOT NULL THEN
            SELECT t.id, COALESCE(w.balance, 0) INTO v_transaction_id, v_new_balance
            FROM transactions t
            LEFT JOIN wallets w ON w.user_id = t.user_id AND w.currency = t.currency
            WHERE t.idempotency_key = p_idempotency_key
            AND t.user_id = p_user_id
            AND t.currency = p_currency;
            
            IF v_transaction_id IS NOT NULL THEN
                RETURN jsonb_build_object(
                    'success', true,
                    'transaction_id', v_transaction_id,
                    'balance', v_new_balance,
                    'duplicate', true,
                    'message', 'Transaction already processed (race condition)'
                );
            END IF;
        END IF;
        RAISE;
    WHEN OTHERS THEN
        RAISE;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- ----------------------------
-- RPC функция: get_wallet_balance
-- Получение баланса пользователя
-- ----------------------------
CREATE OR REPLACE FUNCTION get_wallet_balance(
    p_user_id UUID,
    p_currency VARCHAR DEFAULT 'USD'
)
RETURNS JSONB AS $$
DECLARE
    v_balance DECIMAL;
BEGIN
    SELECT COALESCE(balance, 0) INTO v_balance
    FROM wallets
    WHERE user_id = p_user_id AND currency = p_currency;
    
    RETURN jsonb_build_object(
        'success', true,
        'balance', COALESCE(v_balance, 0),
        'currency', p_currency,
        'user_id', p_user_id
    );
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- ----------------------------
-- RPC функция: get_all_wallet_balances
-- Получение всех балансов пользователя
-- ----------------------------
CREATE OR REPLACE FUNCTION get_all_wallet_balances(
    p_user_id UUID
)
RETURNS JSONB AS $$
DECLARE
    v_balances JSONB;
BEGIN
    SELECT jsonb_agg(
        jsonb_build_object(
            'currency', currency,
            'balance', balance,
            'updated_at', updated_at
        ) ORDER BY currency
    ) INTO v_balances
    FROM wallets
    WHERE user_id = p_user_id;
    
    RETURN jsonb_build_object(
        'success', true,
        'balances', COALESCE(v_balances, '[]'::JSONB),
        'user_id', p_user_id
    );
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- ----------------------------
-- RPC функция: get_transactions
-- Получение истории транзакций
-- ----------------------------
CREATE OR REPLACE FUNCTION get_transactions(
    p_user_id UUID,
    p_currency VARCHAR DEFAULT NULL,
    p_limit INTEGER DEFAULT 50,
    p_offset INTEGER DEFAULT 0
)
RETURNS JSONB AS $$
DECLARE
    v_transactions JSONB;
    v_total INTEGER;
BEGIN
    -- Получаем транзакции
    SELECT jsonb_agg(
        jsonb_build_object(
            'id', id,
            'amount', amount,
            'type', type,
            'currency', currency,
            'metadata', metadata,
            'created_at', created_at
        ) ORDER BY created_at DESC
    ) INTO v_transactions
    FROM (
        SELECT id, amount, type, currency, metadata, created_at
        FROM transactions
        WHERE user_id = p_user_id
        AND (p_currency IS NULL OR currency = p_currency)
        ORDER BY created_at DESC
        LIMIT p_limit
        OFFSET p_offset
    ) t;
    
    -- Получаем общее количество
    SELECT COUNT(*) INTO v_total
    FROM transactions
    WHERE user_id = p_user_id
    AND (p_currency IS NULL OR currency = p_currency);
    
    RETURN jsonb_build_object(
        'success', true,
        'transactions', COALESCE(v_transactions, '[]'::JSONB),
        'total', v_total,
        'limit', p_limit,
        'offset', p_offset
    );
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- ----------------------------
-- RPC функция: get_wallet_summary
-- Получение сводки по кошельку (балансы + последние транзакции)
-- ----------------------------
CREATE OR REPLACE FUNCTION get_wallet_summary(
    p_user_id UUID,
    p_currency VARCHAR DEFAULT NULL,
    p_transaction_limit INTEGER DEFAULT 10
)
RETURNS JSONB AS $$
DECLARE
    v_balances JSONB;
    v_transactions JSONB;
    v_total_usd DECIMAL := 0;
BEGIN
    -- Получаем балансы
    IF p_currency IS NULL THEN
        SELECT jsonb_agg(
            jsonb_build_object(
                'currency', currency,
                'balance', balance,
                'updated_at', updated_at
            ) ORDER BY currency
        ) INTO v_balances
        FROM wallets
        WHERE user_id = p_user_id;
    ELSE
        SELECT jsonb_agg(
            jsonb_build_object(
                'currency', currency,
                'balance', balance,
                'updated_at', updated_at
            )
        ) INTO v_balances
        FROM wallets
        WHERE user_id = p_user_id AND currency = p_currency;
    END IF;
    
    -- Получаем последние транзакции
    SELECT jsonb_agg(
        jsonb_build_object(
            'id', id,
            'amount', amount,
            'type', type,
            'currency', currency,
            'metadata', metadata,
            'created_at', created_at
        ) ORDER BY created_at DESC
    ) INTO v_transactions
    FROM (
        SELECT id, amount, type, currency, metadata, created_at
        FROM transactions
        WHERE user_id = p_user_id
        AND (p_currency IS NULL OR currency = p_currency)
        ORDER BY created_at DESC
        LIMIT p_transaction_limit
    ) t;
    
    RETURN jsonb_build_object(
        'success', true,
        'balances', COALESCE(v_balances, '[]'::JSONB),
        'transactions', COALESCE(v_transactions, '[]'::JSONB),
        'user_id', p_user_id
    );
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- ----------------------------
-- Комментарии к таблицам
-- ----------------------------
COMMENT ON TABLE wallets IS 'Кошельки пользователей. Баланс обновляется автоматически через триггер при вставке транзакций.';
COMMENT ON TABLE transactions IS 'Транзакции пользователей. Единственный способ изменения баланса.';
COMMENT ON COLUMN transactions.idempotency_key IS 'Уникальный ключ для защиты от double spend. При повторном использовании возвращается существующая транзакция.';
