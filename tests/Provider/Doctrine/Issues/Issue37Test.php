<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue37\Locale;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue37\User;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\SchemaSetupTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class Issue37Test extends TestCase
{
    use SchemaSetupTrait;
    use ReaderTrait;

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
        self::assertCount(2, $audits, 'results count ok.');
        self::assertSame('en_US', $audits[0]->getObjectId(), 'AuditEntry::object_id is a string.');
        self::assertSame('fr_FR', $audits[1]->getObjectId(), 'AuditEntry::object_id is a string.');

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
        self::assertCount(2, $audits, 'results count ok.');
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
//        $this->provider->registerEntityManager(
//            $this->createEntityManager([
//                __DIR__.'/../../../../src/Provider/Doctrine/Auditing/Annotation',
//                __DIR__.'/../Fixtures/Issue37',
//            ]),
//            DoctrineProvider::BOTH,
//            'default'
//        );

        $auditor->registerProvider($this->provider);
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Locale::class => ['enabled' => true],
            User::class => ['enabled' => true],
        ]);
    }

//    public function testIssue40(): void
//    {
//        $em = $this->getEntityManager();
//        $reader = $this->getReader($this->getAuditConfiguration());
//
//        $coreCase = new CoreCase();
//        $coreCase->type = 'type1';
//        $coreCase->status = 'status1';
//        $em->persist($coreCase);
//        $this->flushAll($entityManagers);
//
//        $dieselCase = new DieselCase();
//        $dieselCase->coreCase = $coreCase;
//        $dieselCase->setName('yo');
//        $em->persist($dieselCase);
//        $this->flushAll($entityManagers);
//
//        $audits = $reader->getAudits(CoreCase::class);
//        self::assertCount(1, $audits, 'results count ok.');
//        self::assertSame(Reader::INSERT, $audits[0]->getType(), 'AuditReader::INSERT operation.');
//
//        $audits = $reader->getAudits(DieselCase::class);
//        self::assertCount(1, $audits, 'results count ok.');
//        self::assertSame(Reader::INSERT, $audits[0]->getType(), 'AuditReader::INSERT operation.');
//    }
}
