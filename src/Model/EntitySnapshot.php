<?php

declare(strict_types=1);

namespace DH\Auditor\Model;

use DH\Auditor\Tests\Model\EntitySnapshotTest;

/**
 * @see EntitySnapshotTest
 */
final readonly class EntitySnapshot
{
    public function __construct(
        public string $class,
        public int|string $id,
        public string $label,
        public string $table,
    ) {}

    public static function fromRaw(array $raw): self
    {
        $class = $raw['class'] ?? '';
        $id = $raw['id'] ?? '';
        $label = $raw['label'] ?? '';
        $table = $raw['table'] ?? '';

        return new self(
            class: \is_string($class) ? $class : '',
            id: \is_int($id) || \is_string($id) ? $id : '',
            label: \is_string($label) ? $label : '',
            table: \is_string($table) ? $table : '',
        );
    }
}
