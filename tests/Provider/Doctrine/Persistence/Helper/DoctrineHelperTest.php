<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Helper;

use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class DoctrineHelperTest extends TestCase
{
    public function testGetRealClassName(): void
    {
        $className = 'App\Entity\City';
        $expected = $className;
        $realClassName = DoctrineHelper::getRealClassName($className);
        $this->assertSame($expected, $realClassName, 'real class name OK');

        // Doctrine uses __CG__ marker for proxy classes (Doctrine\Persistence\Proxy::MARKER)
        // Note: With PHP 8.4+, Doctrine uses native lazy objects instead
        $className = 'Proxies\__CG__\App\Entity\City';
        $realClassName = DoctrineHelper::getRealClassName($className);
        $this->assertSame($expected, $realClassName, 'proxy class name OK');
    }
}
