<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue40;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'case_core')]
#[ORM\HasLifecycleCallbacks]
class CoreCase
{
    #[ORM\Column(name: 'type', type: Types::STRING, length: 50)]
    public ?string $type = null;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 50)]
    public ?string $status = null;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;
}
