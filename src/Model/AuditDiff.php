<?php

declare(strict_types=1);

namespace DH\Auditor\Model;

use DH\Auditor\Tests\Model\AuditDiffTest;

/**
 * Immutable value object representing a single field change in an audit entry.
 *
 * @see AuditDiffTest
 */
final class AuditDiff
{
    public function __construct(
        private readonly string $field,
        private readonly mixed $oldValue,
        private readonly mixed $newValue,
    ) {}

    public function getField(): string
    {
        return $this->field;
    }

    public function getOldValue(): mixed
    {
        return $this->oldValue;
    }

    public function getNewValue(): mixed
    {
        return $this->newValue;
    }

    /**
     * Returns true when the field was added (old is null, new is set).
     * Reliable for schema_version >= 2 entries where 'old' is always explicit.
     * For legacy entries (schema_version = 1 INSERT), old is null by convention.
     */
    public function wasAdded(): bool
    {
        return null === $this->oldValue && null !== $this->newValue;
    }

    /**
     * Returns true when the field was removed (old is set, new is null).
     * Reliable for schema_version >= 2 entries where 'new' is always explicit.
     */
    public function wasRemoved(): bool
    {
        return null !== $this->oldValue && null === $this->newValue;
    }

    /**
     * Returns true when the old or new value is a relation descriptor array
     * with keys: id, class, label, table.
     */
    public function isRelation(): bool
    {
        return self::isRelationDescriptor($this->oldValue)
            || self::isRelationDescriptor($this->newValue);
    }

    private static function isRelationDescriptor(mixed $value): bool
    {
        return \is_array($value)
            && \array_key_exists('id', $value)
            && \array_key_exists('class', $value)
            && \array_key_exists('label', $value)
            && \array_key_exists('table', $value);
    }
}
