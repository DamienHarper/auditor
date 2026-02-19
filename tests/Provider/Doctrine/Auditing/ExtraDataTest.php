<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Auditing;

use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\SchemaSetupTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the extra_data provider feature.
 *
 * @see https://github.com/DamienHarper/auditor-bundle/issues/594
 *
 * @internal
 */
#[Small]
final class ExtraDataTest extends TestCase
{
    use ReaderTrait;
    use SchemaSetupTrait;

    /**
     * When no extra_data provider is configured, extra_data must be null.
     */
    public function testExtraDataIsNullWhenNoProviderIsConfigured(): void
    {
        $storageServices = [
            Post::class => $this->provider->getStorageServiceForEntity(Post::class),
        ];
        $entityManager = $storageServices[Post::class]->getEntityManager();
        $reader = $this->createReader();

        $post = new Post();
        $post->setTitle('Test post')->setBody('body')->setCreatedAt(new \DateTimeImmutable());
        $entityManager->persist($post);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(Post::class)->execute();
        $this->assertCount(1, $audits);
        $this->assertNull($audits[0]->extraData, 'extra_data must be null when no provider is configured.');
    }

    /**
     * When an extra_data provider is configured, its return value must appear in every audit entry.
     */
    public function testExtraDataIsPopulatedWhenProviderIsConfigured(): void
    {
        // Register a provider that returns a fixed payload
        $this->provider->getAuditor()->getConfiguration()->setExtraDataProvider(
            static fn (): array => ['route' => 'app_post_create', 'custom' => 42]
        );

        $storageServices = [
            Post::class => $this->provider->getStorageServiceForEntity(Post::class),
        ];
        $entityManager = $storageServices[Post::class]->getEntityManager();
        $reader = $this->createReader();

        $post = new Post();
        $post->setTitle('Extra data post')->setBody('body')->setCreatedAt(new \DateTimeImmutable());
        $entityManager->persist($post);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(Post::class)->execute();
        $this->assertCount(1, $audits);
        $this->assertSame(
            ['route' => 'app_post_create', 'custom' => 42],
            $audits[0]->extraData,
            'extra_data must contain the value returned by the provider.'
        );
    }

    /**
     * When an extra_data provider returns null, extra_data must be null.
     */
    public function testExtraDataIsNullWhenProviderReturnsNull(): void
    {
        $this->provider->getAuditor()->getConfiguration()->setExtraDataProvider(
            static fn (): ?array => null
        );

        $storageServices = [
            Post::class => $this->provider->getStorageServiceForEntity(Post::class),
        ];
        $entityManager = $storageServices[Post::class]->getEntityManager();
        $reader = $this->createReader();

        $post = new Post();
        $post->setTitle('Null extra data')->setBody('body')->setCreatedAt(new \DateTimeImmutable());
        $entityManager->persist($post);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(Post::class)->execute();
        $this->assertCount(1, $audits);
        $this->assertNull($audits[0]->extraData, 'extra_data must be null when provider returns null.');
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Post::class => ['enabled' => true],
        ]);
    }
}
