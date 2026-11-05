<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Thin PDO wrapper: prepared statements only, connected on first use.
 */
final class Connection
{
    private ?PDO $pdo = null;

    public function __construct(
        #[Autowire('%env(DB_HOST)%')] private readonly string $host,
        #[Autowire('%env(DB_PORT)%')] private readonly string $port,
        #[Autowire('%env(DB_NAME)%')] private readonly string $name,
        #[Autowire('%env(DB_USER)%')] private readonly string $user,
        #[Autowire('%env(DB_PASSWORD)%')] private readonly string $password,
    ) {
    }

    /**
     * @param array<int|string, mixed> $params
     *
     * @return int the number of affected rows
     */
    public function execute(string $sql, array $params = []): int
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount();
    }

    /**
     * @param array<int|string, mixed> $params
     *
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch();

        return false === $row ? null : $row;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $this->host, $this->port, $this->name),
            $this->user,
            $this->password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // native prepares return integer columns as PHP ints (DECIMAL stays a string)
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }
}
