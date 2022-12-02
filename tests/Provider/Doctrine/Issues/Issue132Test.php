<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue132\DummyEntity;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
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

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            DummyEntity::class => ['enabled' => true],
        ]);
    }
}
