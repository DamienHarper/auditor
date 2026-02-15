<?php

declare(strict_types=1);

namespace DH\Auditor\User;

interface UserInterface
{
    public ?string $identifier { get; }

    public ?string $username { get; }
}
