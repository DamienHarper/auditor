<?php

declare(strict_types=1);

namespace DH\Auditor\User;

interface UserProviderInterface
{
    public function __invoke(): ?UserInterface;
}
