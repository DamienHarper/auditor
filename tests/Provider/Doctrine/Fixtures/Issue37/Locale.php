<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue37;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="locale")
 */
class Locale
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=5)
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $name;

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
     *
     * @return Locale
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
     *
     * @return Locale
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
}
