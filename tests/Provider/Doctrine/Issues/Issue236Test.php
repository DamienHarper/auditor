<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for issue #236: incorrect table names when entities define a `schema`
 * (database name on MySQL/MariaDB) and the platform returns false for supportsSchemas().
 *
 * Before the fix, SchemaManager::resolveTableName() used `__` as separator for platforms
 * where supportsSchemas() === false (MySQL/MariaDB), producing names like `mydb__user`
 * instead of `mydb.user`. MySQL and MariaDB support cross-database access via the
 * `database.table` dot notation, so the dot separator must always be used.
 *
 * TableSchemaListener was also incorrectly rewriting Doctrine class metadata to use the
 * `__` separator, causing all Doctrine queries to fail for entities with a schema defined,
 * because the table `mydb__user` does not exist in the current database.
 *
 * @see https://github.com/DamienHarper/auditor/issues/236
 *
 * @internal
 */
#[Small]
final class Issue236Test extends TestCase
{
    use DefaultSchemaSetupTrait;

    /**
     * resolveTableName() must always use the dot separator, even on platforms where
     * supportsSchemas() returns false (MySQL, MariaDB).
     */
    #[DataProvider('provideResolveTableNameCases')]
    public function testResolveTableNameAlwaysUsesDotSeparator(
        object $platform,
        string $tableName,
        string $namespaceName,
        string $expected
    ): void {
        $manager = new SchemaManager($this->provider);

        $result = $manager->resolveTableName($tableName, $namespaceName, $platform);

        $this->assertSame($expected, $result);
        $this->assertStringNotContainsString('__', $result, 'Table name must not use __ separator.');
    }

    /**
     * @return iterable<string, array{object, string, string, string}>
     */
    public static function provideResolveTableNameCases(): iterable
    {
        // MySQL/MariaDB: supportsSchemas() === false, but dot notation must still be used
        yield 'MySQL with schema' => [new MySQLPlatform(), 'user', 'mydb', 'mydb.user'];

        yield 'MariaDB with schema' => [new MariaDBPlatform(), 'post', 'blog', 'blog.post'];

        // PostgreSQL: supportsSchemas() === true, dot notation is the natural behavior
        yield 'PostgreSQL with schema' => [new PostgreSQLPlatform(), 'user', 'public', 'public.user'];

        // Empty schema: no prefix added regardless of platform
        yield 'MySQL without schema' => [new MySQLPlatform(), 'user', '', 'user'];

        yield 'MariaDB without schema' => [new MariaDBPlatform(), 'post', '', 'post'];
    }

    private function configureEntities(): void {}
}