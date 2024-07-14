<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware;

use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * @interal
 */
final class DHDriver extends AbstractDriverMiddleware
{
    /** @var array<callable> */
    private array $flusherList = [];

    public function connect(array $params): DHConnection
    {
        return new DHConnection(parent::connect($params), $this);
    }

    public function addDHFlusher(callable $flusher): void
    {
        $this->flusherList[] = $flusher;
    }

    public function resetDHFlusherList(): void
    {
        $this->flusherList = [];
    }

    public function getFlusherList(): array
    {
        return $this->flusherList;
    }
}
