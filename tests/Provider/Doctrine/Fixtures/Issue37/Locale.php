<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue37;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'locale')]
class Locale
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 5)]
    protected string $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $name;

    public function __sleep()
    {
        return ['id', 'name'];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
}
