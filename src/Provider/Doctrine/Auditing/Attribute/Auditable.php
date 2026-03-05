<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Attribute;

/**
 * @deprecated use \DH\Auditor\Attribute\Auditable instead
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Auditable extends \DH\Auditor\Attribute\Auditable {}
