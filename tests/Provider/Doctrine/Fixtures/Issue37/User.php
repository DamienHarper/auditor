<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue37;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="users")
 */
#[ORM\Entity, ORM\Table(name: 'users')]
class User
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="integer", options={"unsigned": true})
     *
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY'), ORM\Column(type: 'integer', options: ['unsigned' => true])]
    protected $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    #[ORM\Column(type: 'string', length: 255)]
    protected $username;

    /**
     * @ORM\Column(type="string", nullable=true, length=5)
     */
    #[ORM\Column(type: 'string', nullable: true, length: 5)]
    protected $locale_id;

    /**
     * @ORM\ManyToOne(targetEntity="Locale", cascade={"persist", "remove"})
     *
     * @ORM\JoinColumn(name="locale_id", referencedColumnName="id", nullable=true)
     */
    #[ORM\ManyToOne(targetEntity: 'Locale', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'locale_id', referencedColumnName: 'id', nullable: true)]
    protected $locale;

    /**
     * Get the value of id.
     *
     * @return mixed
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set the value of id.
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of username.
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Set the value of username.
     */
    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Set the value of locale_id.
     */
    public function setLocaleId(string $locale_id): self
    {
        $this->locale_id = $locale_id;

        return $this;
    }

    /**
     * Get the value of locale_id.
     */
    public function getLocaleId(): ?string
    {
        return $this->locale_id;
    }

    /**
     * Set Locale entity (many to one).
     *
     * @param ?Locale $locale
     */
    public function setLocale(?Locale $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Get Locale entity (many to one).
     *
     * @return ?Locale
     */
    public function getLocale(): ?Locale
    {
        return $this->locale;
    }
}
