<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Provider\Doctrine\Persistence\Helper\SchemaHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use DH\Auditor\Tests\Traits\ReflectionTrait;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for issue #276: audit:schema:update always produces ALTER TABLE queries
 * on MySQL even when the audit table is already up-to-date.
 *
 * Root cause: processColumns() drops and re-adds ALL existing STRING columns with
 * platformOptions: [] (no charset/collation). MySQL introspects those columns with
 * explicit charset/collation in their platformOptions. The Doctrine schema comparator
 * therefore always sees a difference and emits a no-op CHANGE column statement.
 *
 * Fix: when the expected platformOptions are empty ([]), preserve the column's existing
 * platformOptions so the desired schema stays identical to the introspected one.
 *
 * @see https://github.com/DamienHarper/auditor/issues/276
 *
 * @internal
 */
#[Small]
final class Issue276Test extends TestCase
{
    use DefaultSchemaSetupTrait;
    use ReflectionTrait;

    /**
     * processColumns() must not clear platformOptions on existing STRING columns when the
     * expected column definition does not specify any (platformOptions: []).
     *
     * On MySQL, introspected columns carry explicit charset/collation in their platformOptions.
     * Clearing them to [] causes a false-positive ALTER TABLE on every audit:schema:update run.
     */
    public function testProcessColumnsPreservesExistingPlatformOptionsWhenNoneAreConfigured(): void
    {
        $connection = $this->provider->getStorageServiceForEntity(Post::class)
            ->getEntityManager()
            ->getConnection()
        ;

        // Simulate what MySQL's schema introspector returns: every STRING column carries
        // explicit charset + collation in its platformOptions.
        $simulatedPlatformOptions = ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_0900_ai_ci'];

        // Build a table that looks like a MySQL-introspected audit table.
        $table = new Table('post_audit');
        foreach (SchemaHelper::getAuditTableColumns() as $columnName => $struct) {
            $options = $struct['options'];
            if (Types::STRING === $struct['type']) {
                $options['platformOptions'] = $simulatedPlatformOptions;
            }

            $table->addColumn($columnName, $struct['type'], $options);
        }

        // getAuditTableColumns() with no argument returns platformOptions: [] for STRING columns,
        // which is the case for MySQL users who have NOT configured defaultTableOptions.
        $expectedColumns = SchemaHelper::getAuditTableColumns();

        $schemaManager = new SchemaManager($this->provider);
        $method = $this->reflectMethod($schemaManager, 'processColumns');
        $method->invoke($schemaManager, $table, $table->getColumns(), $expectedColumns, $connection);

        // After processColumns, STRING columns must still carry their original platformOptions.
        // Without the fix, processColumns re-adds them with platformOptions: [], wiping the
        // charset/collation and causing a false-positive ALTER on every subsequent run.
        $stringColumnNames = array_keys(array_filter(
            SchemaHelper::getAuditTableColumns(),
            static fn (array $col): bool => Types::STRING === $col['type']
        ));

        foreach ($stringColumnNames as $columnName) {
            $this->assertSame(
                $simulatedPlatformOptions,
                $table->getColumn($columnName)->getPlatformOptions(),
                \sprintf(
                    'platformOptions for STRING column "%s" must be preserved when expected options do not override them (#276).',
                    $columnName
                )
            );
        }
    }

    /**
     * processColumns() must still add new columns that are missing from the table —
     * the fix for #276 must not prevent legitimate schema updates (e.g. adding extra_data).
     */
    public function testProcessColumnsStillAddsNewColumnsWhenMissing(): void
    {
        $connection = $this->provider->getStorageServiceForEntity(Post::class)
            ->getEntityManager()
            ->getConnection()
        ;

        // Build a table that is missing the extra_data column (simulating a pre-v4 audit table).
        $table = new Table('post_audit');
        foreach (SchemaHelper::getAuditTableColumns() as $columnName => $struct) {
            if ('extra_data' === $columnName) {
                continue; // Intentionally omit this column
            }

            $table->addColumn($columnName, $struct['type'], $struct['options']);
        }

        $this->assertFalse($table->hasColumn('extra_data'), 'Precondition: extra_data must not exist before processColumns.');

        $schemaManager = new SchemaManager($this->provider);
        $method = $this->reflectMethod($schemaManager, 'processColumns');
        $method->invoke($schemaManager, $table, $table->getColumns(), SchemaHelper::getAuditTableColumns(), $connection);

        $this->assertTrue($table->hasColumn('extra_data'), 'processColumns() must add missing columns (extra_data) even after the #276 fix.');
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Post::class => ['enabled' => true],
        ]);
    }
}
