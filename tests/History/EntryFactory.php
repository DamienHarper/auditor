<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\History;

use DH\Auditor\Model\Entry;

/**
 * Builds schema v2 entries for the history tests.
 *
 * @internal
 */
final class EntryFactory
{
    private static int $id = 0;

    /**
     * @param array<string, mixed>      $diffs
     * @param null|array<string, mixed> $blame
     */
    public static function entry(string $type, string $class, string $objectId, array $diffs, string $transactionId = 'T1', ?array $blame = null, string $createdAt = '2026-11-23 10:00:00', ?string $label = null): Entry
    {
        $diffs['source'] ??= ['id' => $objectId, 'class' => $class, 'label' => $label ?? $class.'#'.$objectId, 'table' => 'table'];

        return Entry::fromArray([
            'id' => ++self::$id,
            'schema_version' => 2,
            'type' => $type,
            'object_id' => $objectId,
            'transaction_id' => $transactionId,
            'diffs' => json_encode($diffs, JSON_THROW_ON_ERROR),
            'blame_id' => null === $blame ? null : ($blame['id'] ?? null),
            'blame' => null === $blame ? null : json_encode(array_diff_key($blame, ['id' => true]), JSON_THROW_ON_ERROR),
            'created_at' => new \DateTimeImmutable($createdAt),
            'entity' => $class,
        ]);
    }

    /**
     * @param array<string, mixed> $target
     */
    public static function association(string $type, string $class, string $objectId, string $field, array $target, string $transactionId = 'T1', bool $owning = true): Entry
    {
        return self::entry($type, $class, $objectId, [
            'source' => ['id' => $objectId, 'class' => $class, 'label' => $class.'#'.$objectId, 'table' => 'table', 'field' => $field],
            'target' => $target,
            'is_owning_side' => $owning,
        ], $transactionId);
    }
}
