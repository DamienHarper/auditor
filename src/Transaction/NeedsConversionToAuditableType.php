<?php

declare(strict_types=1);

namespace DH\Auditor\Transaction;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Marks a Doctrine type as requiring custom conversion to an auditable value,
 * when the built-in {@see \Doctrine\DBAL\Types\Type::convertToDatabaseValue()}
 * is not sufficient for producing an auditable representation of the value.
 * @author Karol Szarafinowski <karol@szarafinowski.pl>
 */
interface NeedsConversionToAuditableType
{
    public function convertToAuditableValue(mixed $value, AbstractPlatform $platform): mixed;
}
