<?php

declare(strict_types=1);

namespace DH\Auditor\Security;

interface RoleCheckerInterface
{
    public function __invoke(string $entity, string $scope): bool;
}
