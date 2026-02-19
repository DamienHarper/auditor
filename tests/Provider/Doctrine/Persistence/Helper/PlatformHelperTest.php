<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Helper;

use DH\Auditor\Provider\Doctrine\Persistence\Helper\PlatformHelper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
#[AllowMockObjectsWithoutExpectations]
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

    /**
     * Ensures that defaultTableOptions are propagated as column platform options only on
     * MySQL/MariaDB platforms. PostgreSQL does not support per-column charset/collation,
     * so passing them causes the schema comparator to generate false-positive migrations.
     *
     * @see https://github.com/DamienHarper/auditor/issues/241
     */
    #[DataProvider('provideGetColumnPlatformOptionsCases')]
    public function testGetColumnPlatformOptions(object $platform, array $params, array $expected): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->method('getParams')->willReturn($params);

        $this->assertSame($expected, PlatformHelper::getColumnPlatformOptions($connection));
    }

    /**
     * @return iterable<string, array{object, array<string, mixed>, array<string, string>}>
     */
    public static function provideGetColumnPlatformOptionsCases(): iterable
    {
        $mysqlOptions = ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'];
        $pgOptions = ['charset' => 'UTF8', 'collate' => 'UTF8'];

        yield 'MySQL returns defaultTableOptions' => [
            new MySQLPlatform(),
            ['defaultTableOptions' => $mysqlOptions],
            $mysqlOptions,
        ];

        yield 'MariaDB returns defaultTableOptions' => [
            new MariaDBPlatform(),
            ['defaultTableOptions' => $mysqlOptions],
            $mysqlOptions,
        ];

        // Regression test for issue #241: PostgreSQL must return [] so that the schema
        // comparator does not see per-column charset/collation as a difference.
        yield 'PostgreSQL returns empty array even when defaultTableOptions are set' => [
            new PostgreSQLPlatform(),
            ['defaultTableOptions' => $pgOptions],
            [],
        ];

        yield 'MySQL returns empty array when no defaultTableOptions' => [
            new MySQLPlatform(),
            [],
            [],
        ];
    }
}
