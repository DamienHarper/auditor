<?php

declare(strict_types=1);

namespace DH\Auditor\History;

use DH\Auditor\Model\Entry;
use DH\Auditor\Model\TransactionType;
use DH\Auditor\Tests\History\ChangeExtractorTest;

/**
 * Reads audit entries as lists of Change, whatever their schema version.
 *
 * An optional field list per entity keeps only those fields, in that order, for the field changes and the values of
 * Created, Snapshot and Deleted changes. Collection changes are kept when their field is listed.
 *
 * @see ChangeExtractorTest
 */
final readonly class ChangeExtractor
{
    /**
     * @param array<string, list<string>> $fields ordered list of the fields to keep, per entity FQCN
     */
    public function __construct(private array $fields = []) {}

    /**
     * Returns the changes of several entries, without the mirror entries of a bidirectional association: when both
     * sides of the same association change are audited, only the first one read is kept.
     *
     * @param iterable<Entry> $entries
     *
     * @return list<Change>
     */
    public function extractAll(iterable $entries): array
    {
        $changes = [];
        $seen = [];

        foreach ($entries as $entry) {
            foreach ($this->extract($entry) as $change) {
                $key = $this->mirrorKey($change, $entry);
                if (null !== $key) {
                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;
                }

                $changes[] = $change;
            }
        }

        return $changes;
    }

    /**
     * @return list<Change>
     */
    public function extract(Entry $entry): array
    {
        $entity = self::entityOf($entry);
        $objectLabel = $this->objectLabel($entry);
        $diffs = $entry->getDiffs();

        return match ($entry->type) {
            TransactionType::Insert->value => [$this->entityChange($entry, $entity, $objectLabel, $diffs, 'new')],
            TransactionType::Remove->value => [$this->entityChange($entry, $entity, $objectLabel, $diffs, 'old')],
            TransactionType::Update->value => $this->fieldChanges($entry, $entity, $objectLabel, $diffs),
            TransactionType::Associate->value, TransactionType::Dissociate->value => $this->collectionChanges($entry, $entity, $objectLabel, $diffs),
            default => [],
        };
    }

    /**
     * FQCN of the entity of an entry: Entry::$entity when the reader set it, otherwise the class written in the diffs.
     */
    public static function entityOf(Entry $entry): string
    {
        if (null !== $entry->entity) {
            return $entry->entity;
        }

        $class = $entry->getDiffSource()['class'] ?? null;
        if (\is_string($class)) {
            return $class;
        }

        $source = $entry->getDiffs()['source'] ?? null;
        $class = \is_array($source) ? ($source['class'] ?? null) : null;

        return \is_string($class) ? $class : '';
    }

    private function entityChange(Entry $entry, string $entity, ?string $objectLabel, array $diffs, string $side): Change
    {
        [$values, $labels] = $this->values($entry, $entity, $diffs, $side);

        if ('old' === $side) {
            return new Change(ChangeKind::Deleted, $entity, $entry->objectId, $objectLabel, null, $values, null, null, null, $entry, $labels);
        }

        return new Change($entry->isSnapshot() ? ChangeKind::Snapshot : ChangeKind::Created, $entity, $entry->objectId, $objectLabel, null, null, $values, null, null, $entry, $labels);
    }

    /**
     * @return list<Change>
     */
    private function fieldChanges(Entry $entry, string $entity, ?string $objectLabel, array $diffs): array
    {
        $changes = [];

        foreach ($this->orderedFields($entity, array_keys($diffs)) as $field) {
            $diff = $diffs[$field];

            if ($entry->isDeepDiff($field)) {
                $changes[] = new Change(ChangeKind::Json, $entity, $entry->objectId, $objectLabel, $field, null, $diff, null, null, $entry);

                continue;
            }

            if (!\is_array($diff)) {
                continue;
            }

            [$old, $oldLabel] = $this->value($diff['old'] ?? null);
            [$new, $newLabel] = $this->value($diff['new'] ?? null);

            $kind = match (true) {
                null === $old && null !== $new => ChangeKind::Set,
                null !== $old && null === $new => ChangeKind::Cleared,
                default => ChangeKind::Changed,
            };

            $changes[] = new Change($kind, $entity, $entry->objectId, $objectLabel, $field, $old, $new, $oldLabel, $newLabel, $entry);
        }

        return $changes;
    }

    /**
     * @return list<Change>
     */
    private function collectionChanges(Entry $entry, string $entity, ?string $objectLabel, array $diffs): array
    {
        $source = \is_array($diffs['source'] ?? null) ? $diffs['source'] : [];
        $target = \is_array($diffs['target'] ?? null) ? $diffs['target'] : null;
        $field = \is_string($source['field'] ?? null) ? $source['field'] : null;

        if (null === $target || null === $field || [] === $this->orderedFields($entity, [$field])) {
            return [];
        }

        $label = \is_string($target['label'] ?? null) ? $target['label'] : null;

        return TransactionType::Associate->value === $entry->type
            ? [new Change(ChangeKind::Added, $entity, $entry->objectId, $objectLabel, $field, null, $target, null, $label, $entry)]
            : [new Change(ChangeKind::Removed, $entity, $entry->objectId, $objectLabel, $field, $target, null, $label, null, $entry)];
    }

    /**
     * Field => raw value map of a creation or deletion, read from the $side of each field diff, and the field => label
     * map of the values written with a label (a #[DiffLabel] label or an association summary). The value of a JSON
     * field is rebuilt from its deep diff tree.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function values(Entry $entry, string $entity, array $diffs, string $side): array
    {
        $values = [];
        $labels = [];

        foreach ($this->orderedFields($entity, array_keys($diffs)) as $field) {
            $diff = $diffs[$field];
            if (!\is_array($diff)) {
                continue;
            }

            if ($entry->isDeepDiff($field)) {
                $values[$field] = self::rebuild($diff, $side);

                continue;
            }

            if (!\array_key_exists($side, $diff)) {
                continue;
            }

            [$values[$field], $label] = $this->value($diff[$side]);
            if (null !== $label) {
                $labels[$field] = $label;
            }
        }

        return [$values, $labels];
    }

    /**
     * Rebuilds the $side value of a JSON field from its deep diff tree.
     *
     * @param array<array-key, mixed> $node
     *
     * @return array<array-key, mixed>
     */
    private static function rebuild(array $node, string $side): array
    {
        $value = [];

        foreach ($node as $key => $child) {
            if (!\is_array($child)) {
                continue;
            }

            if (Entry::isDiffLeaf($child)) {
                if (\array_key_exists($side, $child)) {
                    $value[$key] = $child[$side];
                }

                continue;
            }

            $value[$key] = self::rebuild($child, $side);
        }

        return $value;
    }

    /**
     * Splits a stored value into its raw value and its label: {value, label} pairs come from #[DiffLabel], arrays
     * holding a class and a label are association summaries.
     *
     * @return array{0: mixed, 1: null|string}
     */
    private function value(mixed $value): array
    {
        if (!\is_array($value)) {
            return [$value, null];
        }

        if (\array_key_exists('value', $value) && \array_key_exists('label', $value) && 2 === \count($value)) {
            return [$value['value'], \is_string($value['label']) ? $value['label'] : null];
        }

        if (\is_string($value['class'] ?? null) && \is_string($value['label'] ?? null)) {
            return [$value, $value['label']];
        }

        return [$value, null];
    }

    private function objectLabel(Entry $entry): ?string
    {
        $source = $entry->getDiffSource() ?? $entry->getDiffs()['source'] ?? null;

        return \is_array($source) && \is_string($source['label'] ?? null) ? $source['label'] : null;
    }

    /**
     * @param list<int|string> $fields fields found in the diff
     *
     * @return list<string>
     */
    private function orderedFields(string $entity, array $fields): array
    {
        $fields = array_values(array_filter(array_map(strval(...), $fields), static fn (string $field): bool => !\in_array($field, ['source', 'target', 'is_owning_side', 'join_table', 'table'], true)));

        if (!isset($this->fields[$entity])) {
            return $fields;
        }

        return array_values(array_intersect($this->fields[$entity], $fields));
    }

    /**
     * Identifies both sides of the same association change: the pair of (class#id.field) ends, sorted.
     */
    private function mirrorKey(Change $change, Entry $entry): ?string
    {
        if (!\in_array($change->kind, [ChangeKind::Added, ChangeKind::Removed], true)) {
            return null;
        }

        $target = $entry->getDiffs()['target'] ?? null;
        $targetField = \is_array($target) && \is_string($target['field'] ?? null) ? $target['field'] : '';
        $ends = [
            $change->entity.'#'.$change->objectId.'.'.$change->field,
            $change->getTargetClass().'#'.$change->getTargetId().'.'.$targetField,
        ];
        sort($ends);

        return $change->kind->value.'|'.$ends[0].'|'.$ends[1];
    }
}
