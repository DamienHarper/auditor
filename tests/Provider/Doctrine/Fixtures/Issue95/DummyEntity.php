<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue95;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="dummy_entity")
 */
#[ORM\Entity, ORM\Table(name: 'dummy_entity')]
class DummyEntity implements \Stringable
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(type="integer")
     */
    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY'), ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=50)
     */
    #[ORM\Column(type: 'string', length: 50)]
    protected string $label;

    /**
     * @var Collection<array-key, RelatedDummyEntity>
     * @ORM\OneToMany(targetEntity="RelatedDummyEntity", mappedBy="parent")
     */
    #[ORM\OneToMany(targetEntity: "RelatedDummyEntity", mappedBy: "parent")]
    private Collection $children;

    public function __construct(string $label)
    {
        $this->label = $label;
        $this->children = new ArrayCollection();
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

    public function __toString()
    {
        return $this->label;
    }

    public function addChild(RelatedDummyEntity $param)
    {
        $this->children->add($param);
    }
}
