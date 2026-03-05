<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Attribute;

/**
 * @deprecated use \DH\Auditor\Attribute\Ignore instead
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Ignore extends \DH\Auditor\Attribute\Ignore {}
