<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Auditor;
use DH\Auditor\Configuration;
use DH\Auditor\Event\AuditEvent;
use DH\Auditor\Event\Dto\AbstractEventDto;
use DH\Auditor\Event\Dto\InsertEventDto;
use DH\Auditor\EventSubscriber\AuditEventSubscriber;
use DH\Auditor\Model\Entry;
use DH\Auditor\Model\Transaction;
use DH\Auditor\Provider\AbstractProvider;
use DH\Auditor\Provider\Doctrine\Auditing\Annotation\AnnotationLoader;
use DH\Auditor\Provider\Doctrine\Auditing\Event\DoctrineSubscriber;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\AuditorConnection;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\AuditorDriver;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\AuditorMiddleware;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\AuditTrait;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionHydrator;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionManager;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionProcessor;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Event\CreateSchemaListener;
use DH\Auditor\Provider\Doctrine\Persistence\Event\TableSchemaListener;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\PlatformHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\SchemaHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\DoctrineService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Provider\Service\AbstractService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue37\Locale;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue37\User;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\SchemaSetupTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
#[CoversClass(Issue37Test::class)]
#[CoversClass(Auditor::class)]
#[CoversClass(Configuration::class)]
#[CoversClass(AuditEventSubscriber::class)]
#[CoversClass(AuditEvent::class)]
#[CoversClass(AbstractEventDto::class)]
#[CoversClass(InsertEventDto::class)]
#[CoversClass(Entry::class)]
#[CoversClass(Transaction::class)]
#[CoversClass(AbstractProvider::class)]
#[CoversClass(AnnotationLoader::class)]
#[CoversClass(DoctrineSubscriber::class)]
#[CoversClass(AuditorConnection::class)]
#[CoversClass(AuditorDriver::class)]
#[CoversClass(AuditorMiddleware::class)]
#[CoversTrait(AuditTrait::class)]
#[CoversClass(TransactionHydrator::class)]
#[CoversClass(TransactionManager::class)]
#[CoversClass(TransactionProcessor::class)]
#[CoversClass(\DH\Auditor\Provider\Doctrine\Configuration::class)]
#[CoversClass(DoctrineProvider::class)]
#[CoversClass(\DH\Auditor\Provider\Doctrine\Model\Transaction::class)]
#[CoversClass(CreateSchemaListener::class)]
#[CoversClass(TableSchemaListener::class)]
#[CoversClass(DoctrineHelper::class)]
#[CoversClass(PlatformHelper::class)]
#[CoversClass(SchemaHelper::class)]
#[CoversClass(Query::class)]
#[CoversClass(Reader::class)]
#[CoversClass(SchemaManager::class)]
#[CoversClass(DoctrineService::class)]
#[CoversClass(AbstractService::class)]
final class Issue37Test extends TestCase
{
    use ReaderTrait;
    use SchemaSetupTrait;

    public function testIssue37(): void
    {
        $storageServices = [
            User::class => $this->provider->getStorageServiceForEntity(User::class),
            Locale::class => $this->provider->getStorageServiceForEntity(Locale::class),
        ];

        $reader = $this->createReader();

        $localeFR = new Locale();
        $localeFR
            ->setId('fr_FR')
            ->setName('Français')
        ;
        $storageServices[Locale::class]->getEntityManager()->persist($localeFR);
        $this->flushAll($storageServices);

        $localeEN = new Locale();
        $localeEN
            ->setId('en_US')
            ->setName('Français')
        ;
        $storageServices[Locale::class]->getEntityManager()->persist($localeEN);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(Locale::class)->execute();
        $this->assertCount(2, $audits, 'results count ok.');
        $this->assertSame('en_US', $audits[0]->getObjectId(), 'Entry::object_id is a string.');
        $this->assertSame('fr_FR', $audits[1]->getObjectId(), 'Entry::object_id is a string.');

        $user1 = new User();
        $user1
            ->setUsername('john.doe')
            ->setLocale($localeFR)
        ;
        $storageServices[User::class]->getEntityManager()->persist($user1);
        $this->flushAll($storageServices);

        $user2 = new User();
        $user2
            ->setUsername('dark.vador')
            ->setLocale($localeEN)
        ;
        $storageServices[User::class]->getEntityManager()->persist($user2);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(User::class)->execute();
        $this->assertCount(2, $audits, 'results count ok.');
    }

    private function createAndInitDoctrineProvider(): void
    {
        $auditor = $this->createAuditor();
        $this->provider = new DoctrineProvider($this->createProviderConfiguration());

        $entityManager = $this->createEntityManager([
            __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
            __DIR__.'/../Fixtures/Issue37',
        ]);
        $this->provider->registerStorageService(new StorageService('default', $entityManager));
        $this->provider->registerAuditingService(new AuditingService('default', $entityManager));

        $auditor->registerProvider($this->provider);
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Locale::class => ['enabled' => true],
            User::class => ['enabled' => true],
        ]);
    }
}
