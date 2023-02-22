<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="vehicle")
 *
 * @ORM\InheritanceType("SINGLE_TABLE")
 *
 * @ORM\DiscriminatorColumn(name="type", type="string")
 *
 * @ORM\DiscriminatorMap({"vehicle": "Vehicle", "car": "Car", "bike": "Bike"})
 */
#[ORM\Entity, ORM\Table(name: 'vehicle'), ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap(['vehicle' => 'Vehicle', 'car' => 'Car', 'bike' => 'Bike'])]
class Vehicle
{
    /**
     * @ORM\Column(type="string", length=50)
     */
    #[ORM\Column(type: 'string', length: 50)]
    protected string $label;

    /**
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="IDENTITY")
     *
     * @ORM\Column(type="integer")
     */
    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY'), ORM\Column(type: 'integer')]
    private int $id;

    /**
     * @ORM\Column(type="integer")
     */
    #[ORM\Column(type: 'integer')]
    private int $wheels;

    public function getWheels(): int
    {
        return $this->wheels;
    }

    public function setWheels(int $wheels): self
    {
        $this->wheels = $wheels;

        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }
}
