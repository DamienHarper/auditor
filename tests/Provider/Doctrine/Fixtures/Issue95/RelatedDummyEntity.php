<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue95;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="related_dummy_entity")
 */
#[ORM\Entity, ORM\Table(name: 'related_dummy_entity')]
class RelatedDummyEntity
{
    /**
     * @ORM\Column(type="string", length=50)
     */
    #[ORM\Column(type: 'string', length: 50)]
    protected string $label;

    /**
     * @ORM\ManyToOne(targetEntity="DummyEntity", cascade={"persist", "remove"}, inversedBy="children")
     *
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id", nullable=true)
     */
    #[ORM\ManyToOne(targetEntity: 'DummyEntity', cascade: ['persist', 'remove'], inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true)]
    protected ?DummyEntity $parent;

    /**
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="IDENTITY")
     *
     * @ORM\Column(type="integer")
     */
    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY'), ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function __construct(?DummyEntity $parent, string $label)
    {
        $this->parent = $parent;
        if (null !== $parent) {
            $parent->addChild($this);
        }
        $this->label = $label;
    }

    public function __toString()
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

    public function getParent(): DummyEntity
    {
        return $this->parent;
    }
}
