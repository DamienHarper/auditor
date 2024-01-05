<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue95;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;

#[ORM\Entity]
#[ORM\Table(name: 'related_dummy_entity')]
class RelatedDummyEntity implements Stringable
{
    #[ORM\Column(type: Types::STRING, length: 50)]
    protected string $label;

    #[ORM\ManyToOne(targetEntity: 'DummyEntity', cascade: ['persist', 'remove'], inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    protected ?DummyEntity $parent;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    public function __construct(?DummyEntity $parent, string $label)
    {
        $this->parent = $parent;
        if ($parent instanceof DummyEntity) {
            $parent->addChild($this);
        }

        $this->label = $label;
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
