<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter;

/**
 * @deprecated since auditor 4.x, to be removed in v5.0. Use damienharper/auditor-doctrine-provider instead.
 * Filter for NULL values (e.g., anonymous users where blame_id IS NULL).
 */
final readonly class NullFilter implements FilterInterface
{
    public function __construct(private string $name) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getSQL(): array
    {
        return [
            'sql' => \sprintf('%s IS NULL', $this->name),
            'params' => [],
        ];
    }
}
