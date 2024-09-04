<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Auditing\Annotation;

use DH\Auditor\Provider\Doctrine\Auditing\Annotation\AnnotationLoader;
use DH\Auditor\Provider\Doctrine\Auditing\Annotation\Auditable;
use DH\Auditor\Provider\Doctrine\Auditing\Annotation\Security;
use DH\Auditor\Provider\Doctrine\Auditing\DBAL\Middleware\AuditorConnection;
use DH\Auditor\Provider\Doctrine\Auditing\DBAL\Middleware\AuditorDriver;
use DH\Auditor\Provider\Doctrine\Auditing\DBAL\Middleware\AuditorMiddleware;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Tests\Provider\Doctrine\Traits\EntityManagerInterfaceTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
#[CoversClass(AnnotationLoader::class)]
#[CoversClass(Auditable::class)]
#[CoversClass(Security::class)]
#[CoversClass(AuditorConnection::class)]
#[CoversClass(AuditorDriver::class)]
#[CoversClass(AuditorMiddleware::class)]
#[CoversClass(DoctrineHelper::class)]
final class AnnotationLoaderTest extends TestCase
{
    use EntityManagerInterfaceTrait;

    public function testLoadEntitiesWithoutAnnotationAndAttribute(): void
    {
        $entityManager = $this->createEntityManager(
            [
                __DIR__.'/../../../../../src/Provider/Doctrine/Auditing/Annotation',
                __DIR__.'/../../Traits',
            ],
            'default',
            null
        );

        $annotationLoader = new AnnotationLoader($entityManager);
        $loaded = $annotationLoader->load();
        $this->assertCount(0, $loaded, 'No annotation loaded using attribute driver');
    }

    public function testLoadEntitiesWithAttributesOnly(): void
    {
        $entityManager = $this->createEntityManager(
            [
                __DIR__.'/../../../../../src/Provider/Doctrine/Auditing/Annotation',
                __DIR__.'/../../Fixtures/Entity/Attribute',
            ],
            'default',
            null
        );
        $annotationLoader = new AnnotationLoader($entityManager);
        $loaded = $annotationLoader->load();
        $this->assertCount(2, $loaded);
    }
}
