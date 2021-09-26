<?php

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

    /**
     * {@inheritdoc}
     */
    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        // right before commit insert all audit entries
        if ('"COMMIT"' === $sql) {
            \call_user_func($this->flusher);
        }
        // on rollback remove flusher callback
        if ('"ROLLBACK"' === $sql) {
            $this->flusher = static function (): void {};
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery(): void
    {
    }
}
