<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="animal")
 * @ORM\InheritanceType("JOINED")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * @ORM\DiscriminatorMap({"cat": "Cat", "dog": "Dog"})
 */
#[ORM\Entity, ORM\Table(name: 'animal'), ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap(['cat' => 'Cat', 'dog' => 'Dog'])]
abstract class Animal
{
    /**
     * @var string
     *
     * @ORM\Column(type="string", length=50)
     */
    #[ORM\Column(type: 'string', length: 50)]
    protected $label;

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(type="integer")
     */
    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY'), ORM\Column(type: 'integer')]
    private $id;

    final public function getId()
    {
        return $this->id;
    }

    final public function getLabel()
    {
        return $this->label;
    }

    final public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }
}
