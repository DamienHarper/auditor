<?php

declare(strict_types=1);

namespace DH\Auditor\Attribute;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Masking\MaskStrategy;
use DH\Auditor\Tests\Attribute\MaskTest;

/**
 * Records a field in the audit without its value.
 *
 * The change of the field is still recorded, but its old and new values are replaced when the entry is written (see
 * DH\Auditor\Masking\Masker):
 * - MaskStrategy::Hidden stores "***" (null stays null);
 * - MaskStrategy::KeyedHash stores an HMAC-SHA256 of the value, keyed with the mask secret of the provider: equal
 *   values give equal hashes, so the entries of one value can be found without storing it.
 *
 * Only the values of the field in the diffs are masked: a value of the field written elsewhere, such as in the label
 * of the entity (__toString() or a label resolver), is not.
 *
 * On a property, the property is the field: leave $field out. On a class, $field names the field; the attribute
 * is repeatable:
 *
 *     #[Mask(field: 'email', strategy: MaskStrategy::KeyedHash)]
 *     final class User extends Model {}
 *
 * @see MaskTest
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Mask
{
    public function __construct(public MaskStrategy $strategy = MaskStrategy::Hidden, public ?string $field = null)
    {
        if (null !== $field && '' === mb_trim($field)) {
            throw new InvalidArgumentException('The field of a #[Mask] attribute cannot be empty.');
        }
    }
}
