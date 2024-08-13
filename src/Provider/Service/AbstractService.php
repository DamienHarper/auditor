<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Service;

abstract class AbstractService implements ServiceInterface
{
    public function __construct(private readonly string $name)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }
}
