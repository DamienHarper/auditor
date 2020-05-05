<?php

namespace DH\Auditor\User;

interface UserInterface
{
    /**
     * @return null|int|string
     */
    public function getId();

    public function getUsername(): ?string;
}
