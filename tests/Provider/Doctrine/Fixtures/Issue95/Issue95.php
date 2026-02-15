<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue95;

use DH\Auditor\Provider\Doctrine\Auditing\Attribute\Auditable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'issue95')]
#[Auditable]
class Issue95 implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    private string $type;   // uninitialized property

    public function __construct(#[ORM\Column(name: 'type', type: Types::STRING, length: 50)]
        private string $name) {}

    public function __toString(): string
    {
        return $this->name.$this->type;   // this triggers an uninitialized property error
    }

    public function getName(): string
    {
        return $this->name;
    }
}
