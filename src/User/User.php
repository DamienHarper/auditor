<?php

declare(strict_types=1);

namespace DH\Auditor\User;

use DH\Auditor\Tests\User\UserTest;

/**
 * @see UserTest
 */
final class User implements UserInterface
{
    public ?string $identifier {
        get => $this->id;
    }

    /**
     * @param null|string        $fqdn         class of the user in the application
     * @param null|UserInterface $impersonator user who impersonates this one
     */
    public function __construct(
        private readonly ?string $id = null,
        public readonly ?string $username = null,
        public readonly ?string $fqdn = null,
        public readonly ?UserInterface $impersonator = null,
    ) {}
}
