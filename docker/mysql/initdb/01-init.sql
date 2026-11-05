-- Runs once, on first start of an empty data volume.
-- The app database and user come from MYSQL_DATABASE / MYSQL_USER; this adds the test database.
CREATE DATABASE IF NOT EXISTS racebets_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON racebets_test.* TO 'user'@'%';
FLUSH PRIVILEGES;
