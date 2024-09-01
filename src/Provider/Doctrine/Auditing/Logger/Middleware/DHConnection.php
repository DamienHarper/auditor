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
    public function __construct(ConnectionInterface $connection, private readonly DHDriver $DHDriver)
    {
        parent::__construct($connection);
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
