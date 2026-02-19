<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Minimal Doctrine SQL filter used in tests to simulate Gedmo SoftDeleteable behaviour:
 * entities with a non-null `deleted_at` value are excluded from all SELECT queries.
 */
final class SoftDeleteFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->hasField('deleted_at')) {
            return '';
        }

        return $targetTableAlias.'.deleted_at IS NULL';
    }
}
