<?php

declare(strict_types=1);

namespace DH\Auditor\Masking;

/**
 * How a #[Mask] field is recorded in the audit.
 */
enum MaskStrategy: string
{
    /**
     * The values are replaced with Masker::HIDDEN_VALUE ("***").
     */
    case Hidden = 'hidden';

    /**
     * The values are replaced with an HMAC-SHA256 keyed with the mask secret of the provider: equal values give equal
     * hashes, so the entries of one value can be found without storing it.
     */
    case KeyedHash = 'keyed_hash';
}
