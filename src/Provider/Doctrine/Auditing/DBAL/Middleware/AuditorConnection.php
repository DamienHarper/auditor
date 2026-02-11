<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\DBAL\Middleware;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;

/**
 * @interal
 */
final class AuditorConnection extends AbstractConnectionMiddleware
{
    public function __construct(ConnectionInterface $connection, private readonly AuditorDriver $auditorDriver)
    {
        parent::__construct($connection);
    }

    #[\Override]
    public function commit(): void
    {
        $flusherList = $this->auditorDriver->getFlusherList();
        foreach ($flusherList as $flusher) {
            ($flusher)();
        }

        $this->auditorDriver->resetFlusherList();

        parent::commit();
    }

    #[\Override]
    public function rollBack(): void
    {
        $this->auditorDriver->resetFlusherList();

        parent::rollBack();
    }
}
