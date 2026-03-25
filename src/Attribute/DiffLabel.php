<?php

declare(strict_types=1);

namespace DH\Auditor\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class DiffLabel
{
    public function __construct(public string $resolver) {}
}
