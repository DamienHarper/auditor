<?php

declare(strict_types=1);

namespace DH\Auditor\Security;

interface SecurityProviderInterface
{
    public function __invoke(): array;
}
