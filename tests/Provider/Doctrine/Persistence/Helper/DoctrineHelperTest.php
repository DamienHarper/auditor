<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Helper;

use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 */
#[\PHPUnit\Framework\Attributes\Small]
final class DoctrineHelperTest extends TestCase
{
    public function testRegisterStorageServiceAgainstNoStorageProvider(): void
    {
        $className = 'App\Entity\City';
        $expected = $className;
        $realClassName = DoctrineHelper::getRealClassName($className);
        self::assertSame($expected, $realClassName, 'real class name OK');

        $className = 'Proxies\__CG__\App\Entity\City';
        $realClassName = DoctrineHelper::getRealClassName($className);
        self::assertSame($expected, $realClassName, 'proxy class name OK');

        $className = 'Proxies\__PM__\App\Entity\City\Generated';
        $realClassName = DoctrineHelper::getRealClassName($className);
        self::assertSame($expected, $realClassName, 'proxy class name OK');
    }
}
