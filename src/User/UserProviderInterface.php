<?php

namespace DH\Auditor\User;

interface UserProviderInterface
{
    public function __invoke(): ?UserInterface;
}
