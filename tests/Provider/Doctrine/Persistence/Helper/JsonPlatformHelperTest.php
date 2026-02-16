<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Helper;

use DH\Auditor\Provider\Doctrine\Persistence\Helper\JsonPlatformHelper;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ConnectionTrait;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class JsonPlatformHelperTest extends TestCase
{
    use ConnectionTrait;

    public function testGetMinimumVersions(): void
    {
        $versions = JsonPlatformHelper::getMinimumVersions();

        $this->assertArrayHasKey('mysql', $versions);
        $this->assertArrayHasKey('mariadb', $versions);
        $this->assertArrayHasKey('postgresql', $versions);
        $this->assertArrayHasKey('sqlite', $versions);

        $this->assertSame('5.7.0', $versions['mysql']);
        $this->assertSame('10.2.3', $versions['mariadb']);
        $this->assertSame('9.4.0', $versions['postgresql']);
        $this->assertSame('3.38.0', $versions['sqlite']);
    }

    public function testIsJsonSearchSupportedWithCurrentConnection(): void
    {
        $connection = $this->createConnection();

        // Should return a boolean (the actual value depends on the database being used)
        $result = JsonPlatformHelper::isJsonSearchSupported($connection);

        $this->assertIsBool($result);
    }

    public function testBuildJsonExtractSqlWithCurrentConnection(): void
    {
        $connection = $this->createConnection();
        $platform = $connection->getDatabasePlatform();

        $sql = JsonPlatformHelper::buildJsonExtractSql($connection, 'extra_data', 'department');

        // Verify that SQL is generated and contains expected elements
        $this->assertNotEmpty($sql);
        $this->assertStringContainsString('extra_data', $sql);

        // Platform-specific assertions
        if ($platform instanceof SQLitePlatform) {
            $this->assertStringContainsString('json_extract', $sql);
            $this->assertStringContainsString('$.department', $sql);
        } elseif ($platform instanceof MySQLPlatform || $platform instanceof MariaDBPlatform) {
            $this->assertStringContainsString('JSON_EXTRACT', $sql);
            $this->assertStringContainsString('JSON_UNQUOTE', $sql);
            $this->assertStringContainsString('$.department', $sql);
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $this->assertStringContainsString("->>'department'", $sql);
        }
    }

    public function testBuildJsonExtractSqlNestedPath(): void
    {
        $connection = $this->createConnection();
        $platform = $connection->getDatabasePlatform();

        $sql = JsonPlatformHelper::buildJsonExtractSql($connection, 'extra_data', 'user.role');

        $this->assertNotEmpty($sql);
        $this->assertStringContainsString('extra_data', $sql);

        // Platform-specific assertions for nested paths
        if ($platform instanceof SQLitePlatform) {
            $this->assertStringContainsString('$.user.role', $sql);
        } elseif ($platform instanceof MySQLPlatform || $platform instanceof MariaDBPlatform) {
            $this->assertStringContainsString('$.user.role', $sql);
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $this->assertStringContainsString('{user,role}', $sql);
        }
    }

    public function testGetPlatformName(): void
    {
        $connection = $this->createConnection();

        $name = JsonPlatformHelper::getPlatformName($connection);

        $this->assertNotEmpty($name);
        $this->assertContains($name, ['MySQL', 'MariaDB', 'PostgreSQL', 'SQLite']);
    }

    public function testBuildFallbackLikePatternString(): void
    {
        $pattern = JsonPlatformHelper::buildFallbackLikePattern('department', 'IT');

        $this->assertSame('%"department":"IT"%', $pattern);
    }

    public function testBuildFallbackLikePatternNumeric(): void
    {
        $pattern = JsonPlatformHelper::buildFallbackLikePattern('count', 42);

        $this->assertSame('%"count":42%', $pattern);
    }

    public function testBuildFallbackLikePatternBoolean(): void
    {
        $patternTrue = JsonPlatformHelper::buildFallbackLikePattern('active', true);
        $patternFalse = JsonPlatformHelper::buildFallbackLikePattern('active', false);

        $this->assertSame('%"active":true%', $patternTrue);
        $this->assertSame('%"active":false%', $patternFalse);
    }

    public function testBuildFallbackLikePatternNull(): void
    {
        $pattern = JsonPlatformHelper::buildFallbackLikePattern('deleted_at', null);

        $this->assertSame('%"deleted_at":null%', $pattern);
    }
}
