<?php

namespace DH\Auditor\Security;

interface SecurityProviderInterface
{
    public function __invoke(): array;
}
