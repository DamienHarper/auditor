<?php

namespace DH\Auditor\Provider\Doctrine\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("CLASS")
 * @Attributes({
 *     @Attribute("enabled", required=false, type="bool"),
 * })
 */
final class Auditable extends Annotation
{
    /**
     * @var bool
     */
    public $enabled = true;
}
