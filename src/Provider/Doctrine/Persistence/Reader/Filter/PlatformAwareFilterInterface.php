<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter;

use Doctrine\DBAL\Connection;

/**
 * Interface for filters that require database platform detection.
 *
 * Filters implementing this interface receive the database connection
 * to generate platform-specific SQL (e.g., JSON functions).
 */
interface PlatformAwareFilterInterface extends FilterInterface
{
    /**
     * Returns SQL and parameters for this filter, using platform-specific syntax.
     *
     * @return array{sql: string, params: array<string, mixed>}
     */
    public function getSQLWithConnection(Connection $connection): array;
}
