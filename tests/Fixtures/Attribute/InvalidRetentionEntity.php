<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Fixtures\Attribute;

use DH\Auditor\Attribute\Auditable;

#[Auditable(maxAge: 'P0D')]
final class InvalidRetentionEntity {}
