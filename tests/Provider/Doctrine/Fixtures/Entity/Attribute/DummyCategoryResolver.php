<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Attribute;

use DH\Auditor\Contract\DiffLabelResolverInterface;

final class DummyCategoryResolver implements DiffLabelResolverInterface
{
    private const array LABELS = [
        1 => 'Books',
        2 => 'Electronics',
        3 => 'Clothing',
    ];

    public function __invoke(mixed $value): ?string
    {
        return self::LABELS[$value] ?? null;
    }
}
