<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Logger;

use Doctrine\DBAL\Logging\SQLLogger;

class Logger implements SQLLogger
{
    /**
     * @var callable
     */
    private $flusher;

    public function __construct(callable $flusher)
    {
        $this->flusher = $flusher;
    }

    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        // insert all audit entries right before commit
        if ('"COMMIT"' === $sql) {
            ($this->flusher)();
        }
        // on rollback remove flusher callback
        if ('"ROLLBACK"' === $sql) {
            $this->flusher = static function (): void {};
        }
    }

    public function stopQuery(): void {}
}
