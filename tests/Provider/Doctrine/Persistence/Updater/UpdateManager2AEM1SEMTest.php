<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Updater;

use DH\Auditor\Provider\Doctrine\Persistence\Updater\UpdateManager;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Tag;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\BlogSchemaSetupTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class UpdateManager2AEM1SEMTest extends TestCase
{
    use BlogSchemaSetupTrait;

    private function createAndInitDoctrineProvider(): void
    {
        $this->provider = $this->createDoctrineProviderWith2AEM1SEM();
    }

    public function testEntityManager(): void
    {
        $authorEM = $this->provider->getEntityManagerForEntity(Author::class);
        $postEM = $this->provider->getEntityManagerForEntity(Post::class);
        $commentEM = $this->provider->getEntityManagerForEntity(Comment::class);
        $tagEM = $this->provider->getEntityManagerForEntity(Tag::class);

        self::assertSame($authorEM, $postEM, 'Author and Post use the same storage entity manager.');
        self::assertSame($authorEM, $commentEM, 'Author and Comment do not use the same storage entity manager.');
        self::assertSame($authorEM, $tagEM, 'Author and Tag do not use the same storage entity manager.');
    }
}
