<?php

declare(strict_types=1);

namespace DH\Auditor\History;

use DH\Auditor\Contract\FieldLabelResolverInterface;

/**
 * Default field label resolver: the name of the field, unchanged.
 */
final readonly class RawFieldLabelResolver implements FieldLabelResolverInterface
{
    public function resolve(string $entity, string $field): string
    {
        return $field;
    }
}
