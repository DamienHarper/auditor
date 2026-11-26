<?php

declare(strict_types=1);

namespace DH\Auditor\Attribute;

use DH\Auditor\Exception\InvalidArgumentException;

/**
 * Records a field in the audit without its value.
 *
 * The change of the field is still recorded, but its old and new values are replaced when the entry is written:
 * - Mask::HIDDEN stores "***" (null stays null);
 * - Mask::KEYED_HASH stores an HMAC-SHA256 of the value, keyed with the secret of the provider: equal values give
 *   equal hashes, so the entries of one value can be found without storing it.
 *
 * On a property, the property is the field: leave $field out. On a class, $field names the field; the attribute
 * is repeatable:
 *
 *     #[Mask(field: 'email', strategy: Mask::KEYED_HASH)]
 *     final class User extends Model {}
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Mask
{
    public const string HIDDEN = 'hidden';

    public const string KEYED_HASH = 'keyed_hash';

    public const string HIDDEN_VALUE = '***';

    public function __construct(public string $strategy = self::HIDDEN, public ?string $field = null)
    {
        if (!\in_array($strategy, [self::HIDDEN, self::KEYED_HASH], true)) {
            throw new InvalidArgumentException(\sprintf('The strategy of a #[Mask] attribute must be "%s" or "%s", "%s" given.', self::HIDDEN, self::KEYED_HASH, $strategy));
        }

        if (null !== $field && '' === mb_trim($field)) {
            throw new InvalidArgumentException('The field of a #[Mask] attribute cannot be empty.');
        }
    }

    /**
     * Returns what the audit stores instead of $value (null stays null).
     *
     * $value is the value as the provider records it (a scalar or an array): a string is hashed as is, any other
     * value as its JSON encoding.
     *
     * @throws InvalidArgumentException when the strategy is Mask::KEYED_HASH and $secret is empty
     */
    public function apply(mixed $value, ?string $secret): ?string
    {
        if (null === $value) {
            return null;
        }

        if (self::HIDDEN === $this->strategy) {
            return self::HIDDEN_VALUE;
        }

        if (null === $secret || '' === $secret) {
            throw new InvalidArgumentException('A #[Mask] attribute with the "keyed_hash" strategy needs the mask secret of the provider.');
        }

        $data = \is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', $data, $secret);
    }
}
