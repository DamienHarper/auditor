<?php

declare(strict_types=1);

namespace DH\Auditor\User;

interface UserInterface
{
    public ?string $identifier { get; }

    public ?string $username { get; }

    /**
     * Class of the user in the application (such as App\Entity\User), written in the `user_fqdn` key of the blame.
     */
    public ?string $fqdn { get; }

    /**
     * User who impersonates this one (switch user), written in the `impersonator` key of the blame; null otherwise.
     */
    public ?UserInterface $impersonator { get; }
}
