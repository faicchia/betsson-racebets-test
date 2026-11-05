<?php

declare(strict_types=1);

// Guards against tests running on the development database, which they truncate.
it('boots the kernel in the test environment', function () {
    self::bootKernel();

    expect(self::$kernel->getEnvironment())->toBe('test');
});

it('targets the test database', function () {
    expect($_ENV['DB_NAME'])->toBe('racebets_test');
});

it('reaches the test database with the schema applied', function () {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s', $_ENV['DB_HOST'], $_ENV['DB_PORT'], $_ENV['DB_NAME']),
        $_ENV['DB_USER'],
        $_ENV['DB_PASSWORD'],
    );

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    expect($tables)->toEqualCanonicalizing(['customers', 'transactions']);
});
