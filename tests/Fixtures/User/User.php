<?php

namespace DH\Auditor\Tests\Fixtures\User;

use DH\Auditor\User\UserInterface;

class User implements UserInterface
{
    /**
     * @var null|int|string
     */
    protected $id;

    /**
     * @var null|string
     */
    protected $username;

    /**
     * User constructor.
     *
     * @param null|int|string $id
     */
    public function __construct($id = null, ?string $username = null)
    {
        $this->id = $id;
        $this->username = $username;
    }

    /**
     * @return null|int|string
     */
    public function getId()
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }
}
