<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Auditing\Attribute;

use DH\Auditor\Provider\Doctrine\Auditing\Attribute\AttributeLoader;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Attribute\DiffLabelEntity;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Attribute\DummyCategoryResolver;
use DH\Auditor\Tests\Provider\Doctrine\Traits\EntityManagerInterfaceTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class AttributeLoaderTest extends TestCase
{
    use EntityManagerInterfaceTrait;

    public function testLoadEntitiesWithoutAnnotationAndAttribute(): void
    {
        $entityManager = $this->createEntityManager(
            [
                __DIR__.'/../../../../../src/Provider/Doctrine/Auditing/Attribute',
                __DIR__.'/../../Traits',
            ],
            'default'
        );

        $attributeLoader = new AttributeLoader($entityManager);
        $loaded = $attributeLoader->load();
        $this->assertCount(0, $loaded, 'No annotation loaded using attribute driver');
    }

    public function testLoadEntitiesWithAttributesOnly(): void
    {
        $entityManager = $this->createEntityManager(
            [
                __DIR__.'/../../../../../src/Provider/Doctrine/Auditing/Attribute',
                __DIR__.'/../../Fixtures/Entity/Attribute',
            ],
            'default'
        );
        $attributeLoader = new AttributeLoader($entityManager);
        $loaded = $attributeLoader->load();
        $this->assertCount(3, $loaded);
    }

    public function testDiffLabelResolversAreLoaded(): void
    {
        $entityManager = $this->createEntityManager(
            [
                __DIR__.'/../../../../../src/Provider/Doctrine/Auditing/Attribute',
                __DIR__.'/../../Fixtures/Entity/Attribute',
            ],
            'default'
        );
        $attributeLoader = new AttributeLoader($entityManager);
        $loaded = $attributeLoader->load();

        $this->assertArrayHasKey(DiffLabelEntity::class, $loaded);
        $this->assertArrayHasKey('diff_label_resolvers', $loaded[DiffLabelEntity::class]);
        $this->assertSame(
            ['categoryId' => DummyCategoryResolver::class],
            $loaded[DiffLabelEntity::class]['diff_label_resolvers']
        );
    }

    public function testEntitiesWithoutDiffLabelHaveEmptyResolvers(): void
    {
        $entityManager = $this->createEntityManager(
            [
                __DIR__.'/../../../../../src/Provider/Doctrine/Auditing/Attribute',
                __DIR__.'/../../Fixtures/Entity/Attribute',
            ],
            'default'
        );
        $attributeLoader = new AttributeLoader($entityManager);
        $loaded = $attributeLoader->load();

        foreach ($loaded as $entityClass => $config) {
            if (DiffLabelEntity::class !== $entityClass) {
                $this->assertArrayHasKey('diff_label_resolvers', $config, "{$entityClass} must have diff_label_resolvers key");
                $this->assertSame([], $config['diff_label_resolvers'], "{$entityClass} must have empty diff_label_resolvers");
            }
        }
    }
}
