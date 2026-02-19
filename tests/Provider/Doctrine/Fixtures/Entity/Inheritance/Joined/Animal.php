<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'animal')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap(['cat' => 'Cat', 'dog' => 'Dog'])]
abstract class Animal
{
    #[ORM\Column(type: Types::STRING, length: 50)]
    protected string $label;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    final public function getId(): int
    {
        return $this->id;
    }

    final public function getLabel(): string
    {
        return $this->label;
    }

    final public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }
}
