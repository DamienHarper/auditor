<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Ignore {}
