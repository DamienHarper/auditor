<?php

declare(strict_types=1);

namespace DH\Auditor\History;

use DH\Auditor\Model\Entry;
use DH\Auditor\Tests\History\ChangeExtractorTest;

/**
 * One change read from an audit entry, in a shape that does not depend on the format of the diffs.
 *
 * - Created, Snapshot and Deleted concern the whole entity: $field is null and $new (Created, Snapshot) or $old
 *   (Deleted, with full_snapshot_on_remove) holds the raw field values, as field => value (a JSON field is rebuilt
 *   from its deep diff), and $labels the labels written with some of them (a #[DiffLabel] label or an association
 *   summary), as field => label.
 * - Set, Changed and Cleared concern one field: $old and $new are the raw values, $oldLabel and $newLabel the labels
 *   written with them (an association summary or a #[DiffLabel] label), if any.
 * - Added and Removed concern a collection ($field): $new (Added) or $old (Removed) is the summary of the element
 *   (class, id, label…), whose label is in $newLabel or $oldLabel and stays readable once the element is deleted.
 * - Json concerns a JSON field: $new holds the deep diff tree (see Entry::isDiffLeaf()).
 *
 * @see ChangeExtractorTest
 */
final readonly class Change
{
    /**
     * @param string                $entity      FQCN of the audited entity
     * @param string                $objectId    identifier of the audited entity
     * @param null|string           $objectLabel label of the audited entity when the entry knows it
     * @param array<string, string> $labels      field => label of the values of a Created, Snapshot or Deleted change
     */
    public function __construct(
        public ChangeKind $kind,
        public string $entity,
        public string $objectId,
        public ?string $objectLabel,
        public ?string $field,
        public mixed $old,
        public mixed $new,
        public ?string $oldLabel,
        public ?string $newLabel,
        public Entry $entry,
        public array $labels = [],
    ) {}

    /**
     * Value of one field: the raw value of a Created, Snapshot or Deleted change ($new, or $old when deleted), the
     * value after a field change (the deep diff tree of a Json change). Null for collection changes and for fields the
     * change does not hold.
     */
    public function getValue(string $field): mixed
    {
        if ($this->kind->isEntityLevel()) {
            $values = ChangeKind::Deleted === $this->kind ? $this->old : $this->new;

            return \is_array($values) ? ($values[$field] ?? null) : null;
        }

        return $field === $this->field && !\in_array($this->kind, [ChangeKind::Added, ChangeKind::Removed], true) ? $this->new : null;
    }

    /**
     * Label of the value of one field, when it was written with one.
     */
    public function getLabel(string $field): ?string
    {
        if ($this->kind->isEntityLevel()) {
            return $this->labels[$field] ?? null;
        }

        return $field === $this->field && !\in_array($this->kind, [ChangeKind::Added, ChangeKind::Removed], true) ? $this->newLabel : null;
    }

    /**
     * Class of the collection element of an Added or Removed change.
     */
    public function getTargetClass(): ?string
    {
        if (!\in_array($this->kind, [ChangeKind::Added, ChangeKind::Removed], true)) {
            return null;
        }

        $target = ChangeKind::Removed === $this->kind ? $this->old : $this->new;

        return \is_array($target) && \is_string($target['class'] ?? null) ? $target['class'] : null;
    }

    /**
     * Identifier of the collection element of an Added or Removed change.
     */
    public function getTargetId(): ?string
    {
        if (!\in_array($this->kind, [ChangeKind::Added, ChangeKind::Removed], true)) {
            return null;
        }

        $target = ChangeKind::Removed === $this->kind ? $this->old : $this->new;
        if (!\is_array($target)) {
            return null;
        }

        $key = \is_string($target['pkName'] ?? null) ? $target['pkName'] : 'id';
        $id = $target[$key] ?? null;

        return \is_int($id) || \is_string($id) ? (string) $id : null;
    }
}
