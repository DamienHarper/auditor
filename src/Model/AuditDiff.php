<?php

declare(strict_types=1);

namespace DH\Auditor\Model;

use DH\Auditor\Tests\Model\AuditDiffTest;

/**
 * Immutable value object representing a single field-level change in an audit diff.
 *
 * Applies to INSERT and UPDATE operations. For REMOVE and ASSOCIATE/DISSOCIATE,
 * see {@see AuditDiffCollection::$entitySnapshot} and {@see AuditDiffCollection::$relationDescriptor}.
 *
 * @see AuditDiffTest
 */
final class AuditDiff
{
    /**
     * Whether the 'old' key was present in the raw diff (false for INSERT fields).
     * Note: this may be true even when $old is null (an UPDATE from null to a value).
     */
    public bool $hasOld {
        get => $this->hadOldKey;
    }

    /**
     * Whether the old or new value is a DiffLabel-enriched shape: ['label' => string, 'value' => mixed].
     */
    public bool $isEnriched {
        get => $this->isEnrichedShape($this->new) || ($this->hadOldKey && $this->isEnrichedShape($this->old));
    }

    /**
     * The unwrapped old value: $old['value'] when enriched, $old otherwise.
     * Returns null when hasOld is false.
     */
    public mixed $oldRawValue {
        get {
            if (\is_array($this->old) && \array_key_exists('label', $this->old) && \array_key_exists('value', $this->old)) {
                return $this->old['value'];
            }

            return $this->old;
        }
    }

    /**
     * The unwrapped new value: $new['value'] when enriched, $new otherwise.
     */
    public mixed $newRawValue {
        get {
            if (\is_array($this->new) && \array_key_exists('label', $this->new) && \array_key_exists('value', $this->new)) {
                return $this->new['value'];
            }

            return $this->new;
        }
    }

    /**
     * The human-readable label for the old value when enriched by a DiffLabelResolver, null otherwise.
     */
    public ?string $oldLabel {
        get {
            if (!\is_array($this->old) || !\array_key_exists('label', $this->old) || !\array_key_exists('value', $this->old)) {
                return null;
            }
            $label = $this->old['label'];

            return \is_string($label) ? $label : null;
        }
    }

    /**
     * The human-readable label for the new value when enriched by a DiffLabelResolver, null otherwise.
     */
    public ?string $newLabel {
        get {
            if (!\is_array($this->new) || !\array_key_exists('label', $this->new) || !\array_key_exists('value', $this->new)) {
                return null;
            }
            $label = $this->new['label'];

            return \is_string($label) ? $label : null;
        }
    }

    public function __construct(
        public readonly string $field,
        public readonly mixed $old,
        public readonly mixed $new,
        private readonly bool $hadOldKey,
    ) {}

    /**
     * Builds an AuditDiff from a single field's raw diff array (e.g. ['old' => ..., 'new' => ...]).
     */
    public static function fromRaw(string $field, array $raw): self
    {
        $hadOldKey = \array_key_exists('old', $raw);

        return new self(
            field: $field,
            old: $hadOldKey ? $raw['old'] : null,
            new: $raw['new'],
            hadOldKey: $hadOldKey,
        );
    }

    private function isEnrichedShape(mixed $value): bool
    {
        return \is_array($value)
            && \array_key_exists('label', $value)
            && \array_key_exists('value', $value);
    }
}
