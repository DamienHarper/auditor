<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Attribute;

use Symfony\Contracts\Service\Attribute\Required;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Security
{
    public const string VIEW_SCOPE = 'view';

    public function __construct(
        /**
         * @var array<string>
         */
        #[Required]
        public array $view
    ) {}
}
