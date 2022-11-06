<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

/**
 * @interal
 */
final class DHConnection implements ConnectionInterface
{
    private ConnectionInterface $connection;
    private DHDriver $DHDriver;

    public function __construct(ConnectionInterface $connection, DHDriver $DHDriver)
    {
        $this->connection = $connection;
        $this->DHDriver = $DHDriver;
    }

    public function prepare(string $sql): Statement
    {
        return $this->connection->prepare($sql);
    }

    public function query(string $sql): Result
    {
        return $this->connection->query($sql);
    }

    public function quote($value, $type = ParameterType::STRING)
    {
        return $this->connection->quote($value, $type);
    }

    public function exec(string $sql): int
    {
        return $this->connection->exec($sql);
    }

    public function lastInsertId($name = null)
    {
        return $this->connection->lastInsertId($name);
    }

    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool
    {
        $flusherList = $this->DHDriver->getFlusherList();
        foreach ($flusherList as $flusher) {
            ($flusher)();
        }
        $this->DHDriver->resetDHFlusherList();

        return $this->connection->commit();
    }

    public function rollBack(): bool
    {
        $this->DHDriver->resetDHFlusherList();

        return $this->connection->rollBack();
    }

    /**
     * @return object|resource
     */
    public function getNativeConnection()
    {
        return $this->connection->getNativeConnection();
    }
}
