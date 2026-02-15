<?php

declare(strict_types=1);

namespace DH\Auditor\User;

final class User implements UserInterface
{
    public ?string $identifier {
        get => $this->id;
    }

    public function __construct(
        private readonly ?string $id = null,
        public readonly ?string $username = null,
    ) {}
}
