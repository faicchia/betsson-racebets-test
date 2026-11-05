-- Applied to both the app and the test database by initdb/02-schema.sh.

CREATE TABLE customers (
    id BINARY(16) NOT NULL,  -- UUID v7, generated in PHP
    email VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    gender ENUM('male','female','other') NOT NULL,
    country CHAR(2) NOT NULL,  -- ISO 3166-1 alpha-2
    bonus_percent TINYINT UNSIGNED NOT NULL,
    real_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    bonus_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME(6) NOT NULL,  -- UTC
    updated_at DATETIME(6) NOT NULL,  -- UTC

    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_email (email),

    CONSTRAINT chk_customers_bonus_percent CHECK (bonus_percent BETWEEN 5 AND 20),
    CONSTRAINT chk_customers_real_balance CHECK (real_balance  >= 0),
    CONSTRAINT chk_customers_bonus_balance CHECK (bonus_balance >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BINARY(16) NOT NULL,
    type ENUM('deposit','withdrawal') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,  -- always positive; type gives the direction
    bonus_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    country CHAR(2) NOT NULL,  -- snapshot of customers.country at the time
    created_at DATETIME(6) NOT NULL,  -- UTC

    PRIMARY KEY (id),
    KEY idx_transactions_customer_type (customer_id, type),
    KEY idx_transactions_created_at (created_at),

    CONSTRAINT fk_transactions_customer FOREIGN KEY (customer_id) REFERENCES customers (id),
    CONSTRAINT chk_transactions_amount CHECK (amount > 0),
    CONSTRAINT chk_transactions_bonus_amount CHECK (bonus_amount >= 0),
    CONSTRAINT chk_transactions_bonus_only_on_deposit CHECK (type = 'deposit' OR bonus_amount = 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
