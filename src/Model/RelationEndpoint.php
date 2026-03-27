<?php

declare(strict_types=1);

namespace DH\Auditor\Model;

use DH\Auditor\Tests\Model\RelationEndpointTest;

/**
 * @see RelationEndpointTest
 */
final readonly class RelationEndpoint
{
    public function __construct(
        public string $class,
        public string $field,
        public int|string $id,
        public string $label,
        public string $table,
        public ?string $pkName = null,
    ) {}

    public static function fromRaw(array $raw): self
    {
        $class = $raw['class'] ?? '';
        $field = $raw['field'] ?? '';
        $label = $raw['label'] ?? '';
        $table = $raw['table'] ?? '';

        $pkName = \is_string($raw['pkName'] ?? null) ? $raw['pkName'] : null;

        if (null !== $pkName && !\array_key_exists($pkName, $raw)) {
            throw new \UnexpectedValueException(
                \sprintf('RelationEndpoint: pkName "%s" declared but the corresponding key is absent from raw data.', $pkName)
            );
        }

        $rawId = null !== $pkName ? $raw[$pkName] : ($raw['id'] ?? '');
        $id = \is_int($rawId) || \is_string($rawId) ? $rawId : '';

        return new self(
            class: \is_string($class) ? $class : '',
            field: \is_string($field) ? $field : '',
            id: $id,
            label: \is_string($label) ? $label : '',
            table: \is_string($table) ? $table : '',
            pkName: $pkName,
        );
    }
}
