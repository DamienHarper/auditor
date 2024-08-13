<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Auditing\Annotation;

use DH\Auditor\Provider\Doctrine\Auditing\Annotation\AnnotationLoader;
use DH\Auditor\Tests\Provider\Doctrine\Traits\EntityManagerInterfaceTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
#[CoversNothing]
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
