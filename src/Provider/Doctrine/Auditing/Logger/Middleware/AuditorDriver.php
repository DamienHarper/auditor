<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware;

use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * @interal
 */
final class AuditorDriver extends AbstractDriverMiddleware
{
    /** @var array<callable> */
    private array $flusherList = [];

    public function connect(array $params): AuditorConnection
    {
        return new AuditorConnection(parent::connect($params), $this);
    }

    public function addFlusher(callable $flusher): void
    {
        $this->flusherList[] = $flusher;
    }

    public function resetFlusherList(): void
    {
        $this->flusherList = [];
    }

    public function getFlusherList(): array
    {
        return $this->flusherList;
    }
}
