<?php

namespace  DH\Auditor\Security;

interface RoleCheckerInterface
{
    public function __invoke(string $entity, string $scope): bool;
}
