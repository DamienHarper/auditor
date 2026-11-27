<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Fixtures\Attribute;

use DH\Auditor\Attribute\DiffLabel;
use DH\Auditor\Attribute\Ignore;
use DH\Auditor\Attribute\Mask;

final class PropertyLevelFieldsEntity
{
    #[Ignore]
    public ?string $password = null;

    #[Mask]
    public ?string $email = null;

    #[DiffLabel(resolver: 'App\Resolver\CategoryLabelResolver')]
    public ?int $categoryId = null;
}
