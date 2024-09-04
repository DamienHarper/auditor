<?php

declare(strict_types=1);

namespace DH\Auditor\User;

final readonly class User implements UserInterface
{
    /**
     * User constructor.
     */
    public function __construct(private ?string $id = null, private ?string $username = null) {}

    public function getIdentifier(): ?string
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }
}
