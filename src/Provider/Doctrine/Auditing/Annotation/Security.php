<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Annotation;

use Attribute;
use Symfony\Contracts\Service\Attribute\Required;

#[Attribute(Attribute::TARGET_CLASS)]
final class Security
{
    /**
     * @var string
     */
    public const VIEW_SCOPE = 'view';

    /**
     * @var array<string>
     */
    #[Required]
    public array $view = [];

    public function __construct(array $view)
    {
        $this->view = $view;
    }
}
