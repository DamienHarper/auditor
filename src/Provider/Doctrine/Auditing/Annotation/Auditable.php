<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target("CLASS")
 *
 * @Attributes({
 *
 *     @Attribute("enabled", required=false, type="bool"),
 * })
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Auditable
{
    public bool $enabled = true;

    public function __construct(bool $enabled = true)
    {
        $this->enabled = $enabled;
    }
}
