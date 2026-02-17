<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Model\TransactionType;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue249\Bar;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue249\Foo;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\SchemaSetupTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for issue #249.
 *
 * When an audited entity uses a ManyToOne relationship as its @Id
 * (foreign key as primary key), auditor must not throw a ReflectionException.
 *
 * Root cause: DoctrineHelper::getReflectionPropertyValue() was calling
 * $meta->getReflectionProperty($name)->getValue($entity), which could return
 * a ReflectionProperty declared on the wrong class when the ID is an association.
 * The fix uses $meta->getPropertyAccessor($name)->getValue($entity) instead.
 *
 * @internal
 */
#[Small]
final class Issue249Test extends TestCase
{
    use ReaderTrait;
    use SchemaSetupTrait;

    public function testInsertingEntityWithManyToOneIdDoesNotThrowReflectionException(): void
    {
        $em = $this->provider->getStorageServiceForEntity(Foo::class)->getEntityManager();

        $foo = new Foo('foo1');
        $em->persist($foo);
        $em->flush();

        // This must NOT throw a ReflectionException when the auditor reads
        // the ID value of $bar (which is a ManyToOne association).
        $bar = new Bar($foo, 'user1');
        $em->persist($bar);
        $em->flush();

        $reader = $this->createReader();
        $audits = $reader->createQuery(Bar::class)->execute();

        $this->assertCount(1, $audits, 'One audit entry must be created for Bar.');
        $this->assertSame(TransactionType::INSERT, $audits[0]->type, 'The audit entry must be an INSERT.');
    }

    private function createAndInitDoctrineProvider(): void
    {
        $auditor = $this->createAuditor();
        $this->provider = new DoctrineProvider($this->createProviderConfiguration());

        $entityManager = $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Attribute',
            __DIR__.'/../Fixtures/Issue249',
        ]);
        $this->provider->registerStorageService(new StorageService('default', $entityManager));
        $this->provider->registerAuditingService(new AuditingService('default', $entityManager));

        $auditor->registerProvider($this->provider);
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Foo::class => ['enabled' => true],
            Bar::class => ['enabled' => true],
        ]);
    }
}
