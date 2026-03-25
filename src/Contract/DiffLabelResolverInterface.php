<?php

declare(strict_types=1);

namespace DH\Auditor\Contract;

/**
 * Resolves a raw audit diff value to a human-readable label.
 *
 * Implementations are called at write-time (during flush) so they run inside
 * the Doctrine post-commit callback.  Resolvers MUST NOT flush the same
 * EntityManager or perform operations that depend on an open transaction.
 * Using a separate read-only connection or a pure in-memory lookup is safe.
 *
 * Return null to fall back to storing the plain raw value without a label.
 */
interface DiffLabelResolverInterface
{
    public function __invoke(mixed $value): ?string;
}
