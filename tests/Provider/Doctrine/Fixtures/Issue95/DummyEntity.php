<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue95;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'dummy_entity')]
class DummyEntity implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\OneToMany(targetEntity: 'RelatedDummyEntity', mappedBy: 'parent')]
    private Collection $children;

    public function __construct(#[ORM\Column(type: Types::STRING, length: 50)]
        protected string $label)
    {
        $this->children = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->label;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function addChild(RelatedDummyEntity $param): void
    {
        $this->children->add($param);
    }
}
