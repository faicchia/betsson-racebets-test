-- Runs once, on first start of an empty data volume, after 02-schema.sh has created the tables.
-- Dummy data for local development. The entrypoint runs .sql files against MYSQL_DATABASE only,
-- so the test database stays empty: tests insert their own rows.
-- Business rules are followed by the following statements.

SET @now = UTC_TIMESTAMP(6);

SET @anna  = UUID_TO_BIN('019a5a00-0000-7000-8000-000000000001');
SET @lukas = UUID_TO_BIN('019a5a00-0000-7000-8000-000000000002');
SET @sofia = UUID_TO_BIN('019a5a00-0000-7000-8000-000000000003');
SET @alex  = UUID_TO_BIN('019a5a00-0000-7000-8000-000000000004');
SET @erik  = UUID_TO_BIN('019a5a00-0000-7000-8000-000000000005');

INSERT INTO customers
    (id, email, first_name, last_name, gender, country, bonus_percent, real_balance, bonus_balance, created_at, updated_at)
VALUES
    -- 6 deposits (bonus on 3rd and 6th) and 1 withdrawal; moved from DE to AT after the withdrawal.
    (@anna, 'anna.schmidt@example.com', 'Anna', 'Schmidt', 'female', 'AT', 10, 265.55, 5.56, @now - INTERVAL 7 DAY, @now - INTERVAL 1 DAY),
    -- 3 deposits (bonus on 3rd) and 1 withdrawal.
    (@lukas, 'lukas.gruber@example.com', 'Lukas', 'Gruber', 'male', 'AT', 15, 330.00, 15.00, @now - INTERVAL 7 DAY, @now - INTERVAL 5 MINUTE),
    -- 2 deposits, then withdrew everything: real balance back to 0.00.
    (@sofia, 'sofia.rossi@example.com', 'Sofia', 'Rossi', 'female', 'IT', 7, 0.00, 0.00, @now - INTERVAL 5 DAY, @now - INTERVAL 1 DAY),
    -- 3 small deposits: the bonus 0.5005 rounds to 0.50.
    (@alex, 'alex.morgan@example.com', 'Alex', 'Morgan', 'other', 'GB', 5, 30.03, 0.50, @now - INTERVAL 3 DAY, @now - INTERVAL 10 MINUTE),
    -- Just registered, no transactions yet.
    (@erik, 'erik.lindqvist@example.com', 'Erik', 'Lindqvist', 'male', 'SE', 20, 0.00, 0.00, @now - INTERVAL 1 DAY, @now - INTERVAL 1 DAY);

INSERT INTO transactions
    (customer_id, type, amount, bonus_amount, country, created_at)
VALUES
    (@anna, 'deposit', 50.00, 0.00, 'DE', @now - INTERVAL 6 DAY),
    (@anna, 'deposit', 100.00, 0.00, 'DE', @now - INTERVAL 6 DAY + INTERVAL 10 MINUTE),
    (@lukas, 'deposit', 200.00, 0.00, 'AT', @now - INTERVAL 6 DAY + INTERVAL 20 MINUTE),
    (@anna, 'deposit', 30.00, 3.00, 'DE', @now - INTERVAL 5 DAY),
    (@sofia, 'deposit', 75.50, 0.00, 'IT', @now - INTERVAL 5 DAY + INTERVAL 10 MINUTE),
    (@anna, 'withdrawal', 40.00, 0.00, 'DE', @now - INTERVAL 4 DAY),
    (@lukas, 'deposit', 150.00, 0.00, 'AT', @now - INTERVAL 4 DAY + INTERVAL 10 MINUTE),
    (@anna, 'deposit', 80.00, 0.00, 'AT', @now - INTERVAL 3 DAY),
    (@sofia, 'deposit', 24.50, 0.00, 'IT', @now - INTERVAL 3 DAY + INTERVAL 10 MINUTE),
    (@alex, 'deposit', 10.01, 0.00, 'GB', @now - INTERVAL 3 DAY + INTERVAL 20 MINUTE),
    (@anna, 'deposit', 20.00, 0.00, 'AT', @now - INTERVAL 2 DAY),
    (@lukas, 'deposit', 100.00, 15.00, 'AT', @now - INTERVAL 2 DAY + INTERVAL 10 MINUTE),
    (@alex, 'deposit', 10.01, 0.00, 'GB', @now - INTERVAL 2 DAY + INTERVAL 20 MINUTE),
    (@anna, 'deposit', 25.55, 2.56, 'AT', @now - INTERVAL 1 DAY),
    (@sofia, 'withdrawal', 100.00, 0.00, 'IT', @now - INTERVAL 1 DAY + INTERVAL 10 MINUTE),
    (@alex, 'deposit', 10.01, 0.50, 'GB', @now - INTERVAL 10 MINUTE),
    (@lukas, 'withdrawal', 120.00, 0.00, 'AT', @now - INTERVAL 5 MINUTE);
