<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue40;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'case_diesel')]
#[ORM\HasLifecycleCallbacks]
class DieselCase
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: 'CoreCase', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'core_case')]
    public ?CoreCase $coreCase = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    protected ?string $name = null;

    public function getName()
    {
        return $this->name;
    }

    public function setName(mixed $name): void
    {
        $this->name = $name;
    }
}
