<?php

declare(strict_types=1);

namespace DH\Auditor\Attribute;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Model\RetentionPolicy;

/**
 * Marks an entity as auditable.
 *
 * $maxAge (ISO 8601 duration, e.g. "P1Y") and $maxEntries (positive integer) define an optional
 * per-entity retention policy, applied by providers that support pruning audit entries.
 * Both default to null: no retention policy, nothing is ever deleted on account of this attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Auditable
{
    /**
     * @throws InvalidArgumentException when the retention values are invalid
     */
    public function __construct(
        public bool $enabled = true,
        public ?string $maxAge = null,
        public ?int $maxEntries = null,
    ) {
        // Fail fast on an invalid retention policy.
        new RetentionPolicy($maxAge, $maxEntries);
    }

    public function getRetentionPolicy(): RetentionPolicy
    {
        return new RetentionPolicy($this->maxAge, $this->maxEntries);
    }
}
