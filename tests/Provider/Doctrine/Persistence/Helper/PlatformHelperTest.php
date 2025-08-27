<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Helper;

use DH\Auditor\Provider\Doctrine\Persistence\Helper\PlatformHelper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class PlatformHelperTest extends TestCase
{
    #[DataProvider('provideIsJsonSupportedForMariaDbCases')]
    public function testIsJsonSupportedForMariaDb(string $mariaDbVersion, bool $expectedResult): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')
            ->willReturn(new MariaDBPlatform())
        ;
        $connection->method('getServerVersion')
            ->willReturn($mariaDbVersion)
        ;

        $this->assertSame($expectedResult, PlatformHelper::isJsonSupported($connection));
    }

    /**
     * @return iterable<string, array<bool|string>>
     */
    public static function provideIsJsonSupportedForMariaDbCases(): iterable
    {
        yield ['10.2.6', false];

        yield ['10.2.7', true];

        yield ['10.11.8-MariaDB-0ubuntu0.24.04.1', true];
    }
}
