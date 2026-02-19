<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for issue #238: invalid audit table names when the entity table name
 * contains quoted identifiers (e.g. PostgreSQL reserved words such as "user").
 *
 * Before the fix, Reader::getEntityAuditTableName() and TransactionProcessor::audit()
 * used naive string concatenation that appended the suffix outside the closing quote,
 * producing `"user"_audit` instead of `"user_audit"`.
 *
 * @see https://github.com/DamienHarper/auditor/issues/238
 *
 * @internal
 */
#[Small]
final class Issue238Test extends TestCase
{
    use DefaultSchemaSetupTrait;

    #[DataProvider('provideComputeAuditTablenameHandlesQuotedIdentifiersCases')]
    public function testComputeAuditTablenameHandlesQuotedIdentifiers(
        string $tableName,
        string $prefix,
        string $suffix,
        string $expected
    ): void {
        $configuration = new Configuration(['table_prefix' => $prefix, 'table_suffix' => $suffix]);
        $manager = new SchemaManager($this->provider);

        $result = $manager->computeAuditTablename($tableName, $configuration);

        $this->assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function provideComputeAuditTablenameHandlesQuotedIdentifiersCases(): iterable
    {
        // Suffix appended INSIDE the closing quote, not after it
        yield 'double-quoted reserved word, default suffix' => ['"user"', '', '_audit', '"user_audit"'];

        yield 'double-quoted reserved word, prefix and suffix' => ['"user"', 'audit_', '_log', '"audit_user_log"'];

        // Schema + quoted table: suffix must stay inside the quotes
        yield 'schema + double-quoted reserved word' => ['public."user"', '', '_audit', 'public."user_audit"'];

        yield 'schema + double-quoted reserved word, prefix' => ['myschema."order"', 'audit_', '_suffix', 'myschema."audit_order_suffix"'];

        // Backtick-quoted name (MySQL reserved words)
        yield 'backtick-quoted reserved word' => ['`order`', '', '_audit', '`order_audit`'];

        // Unquoted name must still work
        yield 'plain table name' => ['post', '', '_audit', 'post_audit'];
    }

    private function configureEntities(): void {}
}
