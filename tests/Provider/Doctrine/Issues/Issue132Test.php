<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Auditor;
use DH\Auditor\Configuration;
use DH\Auditor\EventSubscriber\AuditEventSubscriber;
use DH\Auditor\Provider\AbstractProvider;
use DH\Auditor\Provider\Doctrine\Auditing\Annotation\AnnotationLoader;
use DH\Auditor\Provider\Doctrine\Auditing\Event\DoctrineSubscriber;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\AuditorConnection;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\AuditorDriver;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\AuditorMiddleware;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionHydrator;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionManager;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionProcessor;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Event\CreateSchemaListener;
use DH\Auditor\Provider\Doctrine\Persistence\Event\TableSchemaListener;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\PlatformHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\SchemaHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Provider\Doctrine\Service\DoctrineService;
use DH\Auditor\Provider\Service\AbstractService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue132\AbstractParentEntity;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue132\DummyEntity;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
#[CoversClass(DummyEntity::class)]
#[CoversClass(Auditor::class)]
#[CoversClass(Configuration::class)]
#[CoversClass(AuditEventSubscriber::class)]
#[CoversClass(AbstractProvider::class)]
#[CoversClass(AnnotationLoader::class)]
#[CoversClass(DoctrineSubscriber::class)]
#[CoversClass(TransactionHydrator::class)]
#[CoversClass(TransactionManager::class)]
#[CoversClass(TransactionProcessor::class)]
#[CoversClass(\DH\Auditor\Provider\Doctrine\Configuration::class)]
#[CoversClass(DoctrineProvider::class)]
#[CoversClass(CreateSchemaListener::class)]
#[CoversClass(TableSchemaListener::class)]
#[CoversClass(DoctrineHelper::class)]
#[CoversClass(PlatformHelper::class)]
#[CoversClass(SchemaHelper::class)]
#[CoversClass(SchemaManager::class)]
#[CoversClass(DoctrineService::class)]
#[CoversClass(AbstractService::class)]
#[CoversClass(AuditorConnection::class)]
#[CoversClass(AuditorDriver::class)]
#[CoversClass(AuditorMiddleware::class)]
final class Issue132Test extends TestCase
{
    use DefaultSchemaSetupTrait;

    public function testIssue132(): void
    {
        $manager = new SchemaManager($this->provider);
        $schema = $manager->createAuditTable(DummyEntity::class);
        $this->assertThat('parent_entities_audit', self::callback(static function (string $tableName) use ($schema): bool {
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
        $this->assertTrue($schema->hasTable($auditTableName));
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            DummyEntity::class => ['enabled' => true],
        ]);
    }
}
