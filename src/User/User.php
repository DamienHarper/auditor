<?php

declare(strict_types=1);

namespace DH\Auditor\User;

class User implements UserInterface
{
    /**
     * @var null|string
     */
    protected $id;

    /**
     * @var null|string
     */
    protected $username;

    /**
     * User constructor.
     */
    public function __construct(?string $id = null, ?string $username = null)
    {
        $this->id = $id;
        $this->username = $username;
    }

    public function getIdentifier(): ?string
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }
}
