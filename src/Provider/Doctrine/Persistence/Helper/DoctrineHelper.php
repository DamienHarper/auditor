<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Helper;

use Doctrine\DBAL\Types\Types;
use InvalidArgumentException;

/**
 * @see \DH\Auditor\Tests\Provider\Doctrine\Persistence\Helper\DoctrineHelperTest
 */
abstract class DoctrineHelper
{
    /**
     * Gets the real class name of a class name that could be a proxy.
     *
     * @param object|string $subject
     *
     * @return string
     *
     * credits
     * https://github.com/api-platform/core/blob/master/src/Util/ClassInfoTrait.php
     */
    public static function getRealClassName($subject): string
    {
        $subject = \is_object($subject) ? \get_class($subject) : $subject;

        // __CG__: Doctrine Common Marker for Proxy (ODM < 2.0 and ORM < 3.0)
        // __PM__: Ocramius Proxy Manager (ODM >= 2.0)
        $positionCg = mb_strrpos($subject, '\\__CG__\\');
        $positionPm = mb_strrpos($subject, '\\__PM__\\');
        if (false === $positionCg && false === $positionPm) {
            return $subject;
        }
        if (false !== $positionCg) {
            return mb_substr($subject, $positionCg + 8);
        }
        $className = ltrim($subject, '\\');

        return mb_substr(
            $className,
            8 + $positionPm,
            mb_strrpos($className, '\\') - ($positionPm + 8)
        );
    }

    public static function getDoctrineType(string $type): string
    {
        if (!\defined(Types::class.'::'.$type)) {
            throw new InvalidArgumentException(sprintf('Undefined Doctrine type "%s"', $type));
        }

        \assert(\is_string(\constant(Types::class.'::'.$type)));

        return \constant(Types::class.'::'.$type);
    }
}
