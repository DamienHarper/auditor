<?php

declare(strict_types=1);

namespace DH\Auditor\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Security
{
    public const string VIEW_SCOPE = 'view';

    public function __construct(
        /**
         * @var array<string>
         */
        public array $view
    ) {}
}
