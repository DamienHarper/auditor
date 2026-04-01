<?php

declare(strict_types=1);

namespace DH\Auditor\Model;

use DH\Auditor\Tests\Model\AuditDiffCollectionTest;

/**
 * Typed, iterable collection of {@see AuditDiff} objects for a single audit entry.
 *
 * Built from the 'changes' map returned by Entry::getDiffs() internals.
 * Non-array values (e.g. legacy REMOVE flat shape) are skipped so the collection
 * is always safe to iterate — use Entry::getDiffsAsArray() to access the raw descriptor.
 *
 * @implements \IteratorAggregate<int, AuditDiff>
 *
 * @see AuditDiffCollectionTest
 */
final class AuditDiffCollection implements \Countable, \IteratorAggregate
{
    /** @var list<AuditDiff> */
    private array $diffs;

    /**
     * @param array<string, mixed> $changes Raw changes map from Entry::getDiffs() internals.
     *                                      Each value should be an array with 'old' and/or 'new' keys.
     *                                      Non-array values are silently skipped (legacy REMOVE shape).
     */
    public function __construct(array $changes)
    {
        $this->diffs = [];

        foreach ($changes as $field => $change) {
            // Non-array values are legacy REMOVE flat shape (id, class, label, table as strings).
            if (!\is_array($change)) {
                continue;
            }

            $old = \array_key_exists('old', $change) ? $change['old'] : null;
            $new = \array_key_exists('new', $change) ? $change['new'] : null;

            $this->diffs[] = new AuditDiff((string) $field, $old, $new);
        }
    }

    /** @return \ArrayIterator<int, AuditDiff> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->diffs);
    }

    public function count(): int
    {
        return \count($this->diffs);
    }

    /**
     * Returns the diff for the given field name, or null if not present.
     */
    public function getField(string $field): ?AuditDiff
    {
        foreach ($this->diffs as $diff) {
            if ($diff->getField() === $field) {
                return $diff;
            }
        }

        return null;
    }

    /**
     * Returns a new collection containing only fields that were added (old === null, new !== null).
     */
    public function added(): self
    {
        return self::fromDiffs(array_values(array_filter(
            $this->diffs,
            static fn (AuditDiff $d): bool => $d->wasAdded(),
        )));
    }

    /**
     * Returns a new collection containing only fields that were removed (old !== null, new === null).
     */
    public function removed(): self
    {
        return self::fromDiffs(array_values(array_filter(
            $this->diffs,
            static fn (AuditDiff $d): bool => $d->wasRemoved(),
        )));
    }

    /**
     * Returns a new collection containing only fields that were changed (old !== null and new !== null).
     *
     * Diffs where both old and new are null are excluded — a field with no before and no after
     * value is not a meaningful change.
     */
    public function changed(): self
    {
        return self::fromDiffs(array_values(array_filter(
            $this->diffs,
            static fn (AuditDiff $d): bool => null !== $d->getOldValue() && null !== $d->getNewValue(),
        )));
    }

    /** @param list<AuditDiff> $diffs */
    private static function fromDiffs(array $diffs): self
    {
        $instance = new self([]);
        $instance->diffs = $diffs;

        return $instance;
    }
}
