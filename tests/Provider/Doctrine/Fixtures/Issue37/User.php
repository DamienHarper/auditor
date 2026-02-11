<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue37;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    protected int $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $username;

    #[ORM\Column(type: Types::STRING, length: 5, nullable: true)]
    protected ?string $locale_id = null;

    #[ORM\ManyToOne(targetEntity: 'Locale', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'locale_id')]
    protected ?Locale $locale = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function setLocaleId(string $locale_id): self
    {
        $this->locale_id = $locale_id;

        return $this;
    }

    public function getLocaleId(): ?string
    {
        return $this->locale_id;
    }

    public function setLocale(?Locale $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale(): ?Locale
    {
        return $this->locale;
    }
}
