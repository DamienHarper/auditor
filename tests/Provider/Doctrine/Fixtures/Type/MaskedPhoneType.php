<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Type;

use DH\Auditor\Transaction\NeedsConversionToAuditableType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

class MaskedPhoneType extends StringType implements NeedsConversionToAuditableType
{
    public function convertToAuditableValue(mixed $value, AbstractPlatform $platform): string|int|float|bool|array|null
    {
        if (!\is_string($value)) {
            return $value;
        }

        // Mask all but the last 4 characters for audit purposes
        $length = mb_strlen($value);
        if ($length <= 4) {
            return $value;
        }

        return str_repeat('*', $length - 4).mb_substr($value, -4);
    }
}