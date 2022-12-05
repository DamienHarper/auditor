<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Provider\Doctrine\Persistence\Event\CreateSchemaListener;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue132\AbstractParentEntity;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue132\DummyEntity;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 */
final class Issue132Test extends TestCase
{
    use DefaultSchemaSetupTrait;

    public function testIssue132(): void
    {
        $manager = new SchemaManager($this->provider);
        $schema = $manager->createAuditTable(DummyEntity::class);
        self::assertThat('parent_entities_audit', self::callback(static function (string $tableName) use ($schema): bool {
            foreach ($schema->getTables() as $table) {
                if ($table->getName() === $tableName) {
                    return true;
                }
            }

            return false;
        }));
    }

    public function testIssue132SchemaListener(): void
    {
        $em = array_values($this->provider->getAuditingServices())[0]->getEntityManager();
        $meta = $em->getClassMetadata(AbstractParentEntity::class);
        $listener = new CreateSchemaListener($this->provider);
        $schema = new Schema();
        $tableName = $meta->getTableName();
        $args = new GenerateSchemaTableEventArgs($meta, $schema, new Table($tableName));
        $listener->postGenerateSchemaTable($args);

        $manager = new SchemaManager($this->provider);
        $auditTableName = $manager->computeAuditTablename($tableName, $this->provider->getConfiguration());
        self::assertTrue($schema->hasTable($auditTableName));
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            DummyEntity::class => ['enabled' => true],
        ]);
    }
}
