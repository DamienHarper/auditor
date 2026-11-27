<?php

declare(strict_types=1);

namespace DH\Auditor\Masking;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Tests\Masking\MaskerTest;

/**
 * Replaces the values of the #[Mask] fields, so that every provider masks them the same way.
 *
 * @see MaskerTest
 */
final readonly class Masker
{
    /**
     * What the audit stores for a value masked with MaskStrategy::Hidden.
     */
    public const string HIDDEN_VALUE = '***';

    /**
     * @param null|string $secret key of the MaskStrategy::KeyedHash hashes (the mask secret of the provider)
     */
    public function __construct(private ?string $secret = null) {}

    /**
     * Returns what the audit stores instead of $value (null stays null).
     *
     * $value is the value as the provider records it (a scalar or an array): a string is hashed as is, any other
     * value as its JSON encoding.
     *
     * @throws InvalidArgumentException when the strategy is MaskStrategy::KeyedHash and there is no secret
     */
    public function mask(MaskStrategy $strategy, mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (MaskStrategy::Hidden === $strategy) {
            return self::HIDDEN_VALUE;
        }

        if (null === $this->secret || '' === $this->secret) {
            throw new InvalidArgumentException('A #[Mask] field with the "keyed_hash" strategy needs the mask secret of the provider.');
        }

        $data = \is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', $data, $this->secret);
    }
}
