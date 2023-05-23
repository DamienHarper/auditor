<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Logger;

use Doctrine\DBAL\Logging\SQLLogger;

class LoggerChain implements SQLLogger
{
    /**
     * @var SQLLogger[]
     */
    private array $loggers = [];

    /**
     * Adds a logger in the chain.
     */
    public function addLogger(SQLLogger $logger): void
    {
        $this->loggers[] = $logger;
    }

    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        foreach ($this->loggers as $logger) {
            $logger->startQuery($sql, $params, $types);
        }
    }

    public function stopQuery(): void
    {
        foreach ($this->loggers as $logger) {
            $logger->stopQuery();
        }
    }

    /**
     * @return SQLLogger[]
     */
    public function getLoggers(): array
    {
        return $this->loggers;
    }
}
