<?php

declare(strict_types=1);

namespace DH\Auditor\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Auditable
{
    public function __construct(
        public bool $enabled = true,
        public ?string $maxAge = null,
        public ?int $maxEntries = null,
    ) {}
}
