<?php

namespace DH\Auditor\User;

interface UserInterface
{
    public function getIdentifier(): ?string;

    public function getUsername(): ?string;
}
