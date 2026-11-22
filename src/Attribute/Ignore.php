<?php

declare(strict_types=1);

namespace DH\Auditor\Attribute;

use DH\Auditor\Exception\InvalidArgumentException;

/**
 * Excludes a field from the audit.
 *
 * On a property, the property is the field: leave $field out. On a class, $field names the field;
 * the attribute is repeatable, so that models without a declared property per column (e.g. Eloquent)
 * can exclude several fields:
 *
 *     #[Ignore(field: 'password')]
 *     #[Ignore(field: 'remember_token')]
 *     final class User extends Model {}
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Ignore
{
    public function __construct(public ?string $field = null)
    {
        if (null !== $field && '' === mb_trim($field)) {
            throw new InvalidArgumentException('The field of an #[Ignore] attribute cannot be empty.');
        }
    }
}
