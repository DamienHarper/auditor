<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue37;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="locale")
 */
#[ORM\Entity, ORM\Table(name: 'locale')]
class Locale
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=5)
     */
    #[ORM\Id, ORM\Column(type: 'string', length: 5)]
    protected string $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    #[ORM\Column(type: 'string', length: 255)]
    protected string $name;

    public function __sleep()
    {
        return ['id', 'name'];
    }

    /**
     * Get the value of id.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set the value of id.
     */
    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the value of name.
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
}
