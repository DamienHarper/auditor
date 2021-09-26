<?php

namespace DH\Auditor\Provider\Doctrine\Auditing\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Doctrine\Common\Annotations\Annotation\Required;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target("CLASS")
 * @Attributes({
 *     @Attribute("view", required=true, type="array<string>"),
 * })
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Security
{
    public const VIEW_SCOPE = 'view';

    /**
     * @var array
     * @Required
     */
    public $view;

    public function __construct(array $view)
    {
        $this->view = $view;
    }
}
