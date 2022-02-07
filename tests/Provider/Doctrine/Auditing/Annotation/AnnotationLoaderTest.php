<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Auditing\Annotation;

use DH\Auditor\Provider\Doctrine\Auditing\Annotation\AnnotationLoader;
use DH\Auditor\Tests\Provider\Doctrine\Traits\EntityManagerInterfaceTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 */
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
            null,
            false
        );

        $annotationLoader = new AnnotationLoader($entityManager);
        $loaded = $annotationLoader->load();
        self::assertCount(0, $loaded, 'No annotation loaded using annotation driver');

        if (\PHP_VERSION_ID >= 80000) {
            $entityManager = $this->createEntityManager(
                [
                    __DIR__.'/../../../../../src/Provider/Doctrine/Auditing/Annotation',
                    __DIR__.'/../../Traits',
                ],
                'default',
                null,
                true
            );

            $annotationLoader = new AnnotationLoader($entityManager);
            $loaded = $annotationLoader->load();
            self::assertCount(0, $loaded, 'No annotation loaded using attribute driver');
        }
    }

    public function testLoadEntitiesWithAnnotationsOnly(): void
    {
        $entityManager = $this->createEntityManager(
            [
                __DIR__.'/../../../../../src/Provider/Doctrine/Auditing/Annotation',
                __DIR__.'/../../Fixtures/Entity/Annotation',
            ],
            'default',
            null,
            false
        );
        $annotationLoader = new AnnotationLoader($entityManager);
        $loaded = $annotationLoader->load();
        self::assertCount(2, $loaded);
    }

    public function testLoadEntitiesWithAttributesOnly(): void
    {
        if (\PHP_VERSION_ID < 80000) {
            self::markTestSkipped('PHP 8.0+ is required.');
        }

        $entityManager = $this->createEntityManager(
            [
                __DIR__.'/../../../../../src/Provider/Doctrine/Auditing/Annotation',
                __DIR__.'/../../Fixtures/Entity/Attribute',
            ],
            'default',
            null,
            true
        );
        $annotationLoader = new AnnotationLoader($entityManager);
        $loaded = $annotationLoader->load();
        self::assertCount(2, $loaded);
    }

    public function testLoadEntitiesWithAnnotationsOnlyButNoAnnotationReader(): void
    {
        if (\PHP_VERSION_ID < 80000) {
            self::markTestSkipped('PHP 8.0+ is required.');
        }

        $entityManager = $this->createEntityManager(
            [
                __DIR__.'/../../../../../src/Provider/Doctrine/Auditing/Annotation',
                __DIR__.'/../../Fixtures/Entity/Annotation',
            ],
            'default',
            null,
            false
        );
        $annotationLoader = new AnnotationLoader($entityManager, true);
        $loaded = $annotationLoader->load();
        self::assertCount(0, $loaded);
    }
}
