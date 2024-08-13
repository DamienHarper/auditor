<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Auditable
{
    public function __construct(public bool $enabled = true)
    {
    }
}
