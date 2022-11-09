<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;

/**
 * @interal
 */
final class DHConnection extends AbstractConnectionMiddleware
{
    private DHDriver $DHDriver;

    public function __construct(ConnectionInterface $connection, DHDriver $DHDriver)
    {
        parent::__construct($connection);
        $this->DHDriver = $DHDriver;
    }

    public function commit(): bool
    {
        $flusherList = $this->DHDriver->getFlusherList();
        foreach ($flusherList as $flusher) {
            ($flusher)();
        }
        $this->DHDriver->resetDHFlusherList();

        return parent::commit();
    }

    public function rollBack(): bool
    {
        $this->DHDriver->resetDHFlusherList();

        return parent::rollBack();
    }
}
