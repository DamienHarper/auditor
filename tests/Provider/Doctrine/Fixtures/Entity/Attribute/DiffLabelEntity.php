<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Attribute;

use DH\Auditor\Provider\Doctrine\Auditing\Attribute as Audit;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'diff_label_entity')]
#[Audit\Auditable]
class DiffLabelEntity
{
    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $name = '';

    #[ORM\Column(type: Types::INTEGER)]
    #[Audit\DiffLabel(resolver: DummyCategoryResolver::class)]
    public int $categoryId = 0;

    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;
}
