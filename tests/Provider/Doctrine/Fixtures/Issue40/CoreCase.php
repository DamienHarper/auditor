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
    #[ORM\Column(type: Types::STRING, name: 'type', length: 50)]
    public ?string $type = null;

    #[ORM\Column(type: Types::STRING, name: 'status', length: 50)]
    public ?string $status = null;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;
}
