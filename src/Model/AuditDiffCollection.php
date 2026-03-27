<?php

declare(strict_types=1);

namespace DH\Auditor\Model;

use DH\Auditor\Tests\Model\AuditDiffCollectionTest;

/**
 * Typed, iterable collection of {@see AuditDiff} objects for INSERT and UPDATE operations.
 *
 * For REMOVE operations, iterate yields no items — use {@see self::requireEntitySnapshot()} instead.
 * For ASSOCIATE/DISSOCIATE operations, iterate yields no items — use {@see self::requireRelationDescriptor()} instead.
 *
 * Dispatch on {@see self::$kind} to branch between the three structural variants:
 *
 * ```php
 * match ($collection->kind) {
 *     DiffKind::FieldChanges => foreach ($collection as $field => $diff) { ... },
 *     DiffKind::EntityRemoval => $snapshot = $collection->requireEntitySnapshot(),
 *     DiffKind::Associate,
 *     DiffKind::Dissociate   => $rel = $collection->requireRelationDescriptor(),
 * };
 * ```
 *
 * @implements \IteratorAggregate<string, AuditDiff>
 *
 * @see AuditDiffCollectionTest
 */
final class AuditDiffCollection implements \Countable, \IteratorAggregate
{
    /**
     * Whether this collection contains no field-level diffs.
     * Always true for EntityRemoval, Associate, and Dissociate kinds.
     */
    public bool $isEmpty {
        get => [] === $this->diffs;
    }

    private function __construct(
        public readonly DiffKind $kind,
        /** @var array<string, AuditDiff> */
        private readonly array $diffs,
        public readonly ?EntitySnapshot $entitySnapshot,
        public readonly ?RelationDescriptor $relationDescriptor
    ) {}

    /**
     * Builds an AuditDiffCollection from a decoded (and already ksorted) raw diffs array.
     *
     * @param array<string, mixed> $raw
     */
    public static function fromRawDiffs(array $raw, TransactionType $transactionType): self
    {
        if (TransactionType::Remove === $transactionType) {
            return new self(
                kind: DiffKind::EntityRemoval,
                diffs: [],
                entitySnapshot: EntitySnapshot::fromRaw($raw),
                relationDescriptor: null,
            );
        }

        if (TransactionType::Associate === $transactionType || TransactionType::Dissociate === $transactionType) {
            return new self(
                kind: TransactionType::Associate === $transactionType ? DiffKind::Associate : DiffKind::Dissociate,
                diffs: [],
                entitySnapshot: null,
                relationDescriptor: RelationDescriptor::fromRaw($raw),
            );
        }

        $diffs = [];
        foreach ($raw as $field => $change) {
            if (str_starts_with((string) $field, '@')) {
                continue;
            }

            if (\is_array($change) && \array_key_exists('new', $change)) {
                $diffs[$field] = AuditDiff::fromRaw($field, $change);
            }
        }

        return new self(
            kind: DiffKind::FieldChanges,
            diffs: $diffs,
            entitySnapshot: null,
            relationDescriptor: null,
        );
    }

    /**
     * @return \ArrayIterator<string, AuditDiff>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->diffs);
    }

    public function count(): int
    {
        return \count($this->diffs);
    }

    public function has(string $field): bool
    {
        return \array_key_exists($field, $this->diffs);
    }

    public function get(string $field): ?AuditDiff
    {
        return $this->diffs[$field] ?? null;
    }

    /**
     * Returns the list of changed field names (only meaningful for FieldChanges kind).
     *
     * @return list<string>
     */
    public function fields(): array
    {
        return array_keys($this->diffs);
    }

    /**
     * Returns the entity snapshot for REMOVE entries.
     *
     * @throws \LogicException when called on a non-EntityRemoval collection
     */
    public function requireEntitySnapshot(): EntitySnapshot
    {
        return $this->entitySnapshot ?? throw new \LogicException(
            \sprintf('entitySnapshot is only available for EntityRemoval diffs, got %s.', $this->kind->value)
        );
    }

    /**
     * Returns the relation descriptor for ASSOCIATE/DISSOCIATE entries.
     *
     * @throws \LogicException when called on a non-Relation collection
     */
    public function requireRelationDescriptor(): RelationDescriptor
    {
        return $this->relationDescriptor ?? throw new \LogicException(
            \sprintf('relationDescriptor is only available for Associate/Dissociate diffs, got %s.', $this->kind->value)
        );
    }
}
