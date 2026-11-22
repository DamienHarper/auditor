<?php

declare(strict_types=1);

namespace DH\Auditor\Attribute;

use DH\Auditor\Exception\InvalidArgumentException;

/**
 * Resolves a human-readable label for the values of a field in the diffs.
 *
 * On a property, the property is the field: leave $field out. On a class, $field names the field;
 * the attribute is repeatable, so that models without a declared property per column (e.g. Eloquent)
 * can label several fields:
 *
 *     #[DiffLabel(resolver: CategoryLabelResolver::class, field: 'category_id')]
 *     final class Post extends Model {}
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class DiffLabel
{
    /**
     * @param string $resolver FQCN (and service ID) of a DiffLabelResolverInterface implementation
     */
    public function __construct(public string $resolver, public ?string $field = null)
    {
        if (null !== $field && '' === mb_trim($field)) {
            throw new InvalidArgumentException('The field of a #[DiffLabel] attribute cannot be empty.');
        }
    }
}
