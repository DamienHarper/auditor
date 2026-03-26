<?php

declare(strict_types=1);

namespace DH\Auditor\Model;

use DH\Auditor\Tests\Model\RelationDescriptorTest;

/**
 * @see RelationDescriptorTest
 */
final readonly class RelationDescriptor
{
    public function __construct(
        public RelationEndpoint $source,
        public RelationEndpoint $target,
        public bool $isOwningSide,
        public ?string $pivotTable = null,
    ) {}

    public static function fromRaw(array $raw): self
    {
        $source = \is_array($raw['source'] ?? null) ? $raw['source'] : [];
        $target = \is_array($raw['target'] ?? null) ? $raw['target'] : [];
        $pivotTable = \is_string($raw['table'] ?? null) ? $raw['table'] : null;

        return new self(
            source: RelationEndpoint::fromRaw($source),
            target: RelationEndpoint::fromRaw($target),
            isOwningSide: (bool) ($raw['is_owning_side'] ?? false),
            pivotTable: $pivotTable,
        );
    }
}
