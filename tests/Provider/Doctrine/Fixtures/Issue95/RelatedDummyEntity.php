<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue95;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'related_dummy_entity')]
class RelatedDummyEntity implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    public function __construct(#[ORM\ManyToOne(targetEntity: 'DummyEntity', cascade: ['persist', 'remove'], inversedBy: 'children')]
        #[ORM\JoinColumn(name: 'parent_id')]
        protected ?DummyEntity $parent, #[ORM\Column(type: Types::STRING, length: 50)]
        protected string $label)
    {
        if ($this->parent instanceof DummyEntity) {
            $this->parent->addChild($this);
        }
    }

    public function __toString(): string
    {
        return $this->label;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the value of name.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    public function getParent(): ?DummyEntity
    {
        return $this->parent;
    }
}
