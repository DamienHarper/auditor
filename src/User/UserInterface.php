<?php

declare(strict_types=1);

namespace DH\Auditor\User;

interface UserInterface
{
    public function getIdentifier(): ?string;

    public function getUsername(): ?string;
}
