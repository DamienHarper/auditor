<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits\Schema;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Tag;

trait BlogSchemaSetupTrait
{
    use SchemaSetupTrait;

    /**
     * @var DoctrineProvider
     */
    private $provider;

    private function createAndInitDoctrineProvider(): void
    {
        $this->provider = $this->createDoctrineProvider();
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Author::class => ['enabled' => true],
            Post::class => ['enabled' => true],
            Comment::class => ['enabled' => true],
            Tag::class => ['enabled' => true],
        ]);
    }
}
