<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter;

interface FilterInterface
{
    public function getName(): string;

    public function getSQL(): array;
}
