<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Traits\Schema;

use DateTimeImmutable;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;

trait BlogSchemaSetupTrait
{
    use SchemaSetupTrait;

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Author::class => [
                'enabled' => true,
                'roles' => [
                    'view' => ['ROLE1', 'ROLE2'],
                ],
            ],
            Post::class => ['enabled' => true],
            Comment::class => ['enabled' => true],
            Tag::class => ['enabled' => true],
        ]);
    }

    /**
     * ++Author 1
     *   +Post 1
     *      +Comment 1
     *   +Post 2
     * +Author 2
     *   +Post 3
     *      +Comment 2
     *      +Comment 3
     * +Author 3
     *   +Post 4
     * +Tag 1
     * +Tag 2
     * +Tag 3
     * +Tag 4
     * +Tag 5
     * +PostTag 1.1
     * +PostTag 1.2
     * +PostTag 3.1
     * +PostTag 3.3
     * +PostTag 3.5
     * +-PostTag 4.4
     * +-PostTag 4.5
     * Author 3
     *   -Post 4
     * -Author 3.
     */
    private function setupEntities(): void
    {
        $auditingServices = [
            Author::class => $this->provider->getAuditingServiceForEntity(Author::class),
            Post::class => $this->provider->getAuditingServiceForEntity(Post::class),
            Comment::class => $this->provider->getAuditingServiceForEntity(Comment::class),
            Tag::class => $this->provider->getAuditingServiceForEntity(Tag::class),
        ];

        $author1 = new Author();
        $author1
            ->setFullname('John')
            ->setEmail('john.doe@gmail.com')
        ;
        $auditingServices[Author::class]->getEntityManager()->persist($author1);

        $post1 = new Post();
        $post1
            ->setAuthor($author1)
            ->setTitle('First post')
            ->setBody('Here is the body')
            ->setCreatedAt(new DateTimeImmutable('2020-01-17 22:17:34'))
        ;
        $auditingServices[Post::class]->getEntityManager()->persist($post1);

        $comment1 = new Comment();
        $comment1
            ->setPost($post1)
            ->setBody('First comment about post #1')
            ->setAuthor('Dark Vador')
            ->setCreatedAt(new DateTimeImmutable('2020-01-17 22:17:34'))
        ;
        $auditingServices[Comment::class]->getEntityManager()->persist($comment1);

        $post2 = new Post();
        $post2
            ->setAuthor($author1)
            ->setTitle('Second post')
            ->setBody('Here is another body')
            ->setCreatedAt(new DateTimeImmutable('2020-01-17 22:17:34'))
        ;
        $auditingServices[Post::class]->getEntityManager()->persist($post2);

        $author2 = new Author();
        $author2
            ->setFullname('Chuck Norris')
            ->setEmail('chuck.norris@gmail.com')
        ;
        $auditingServices[Author::class]->getEntityManager()->persist($author2);

        $post3 = new Post();
        $post3
            ->setAuthor($author2)
            ->setTitle('Third post')
            ->setBody('Here is another body')
            ->setCreatedAt(new DateTimeImmutable('2020-01-17 22:17:34'))
        ;
        $auditingServices[Post::class]->getEntityManager()->persist($post3);

        $comment2 = new Comment();
        $comment2
            ->setPost($post3)
            ->setBody('First comment about post #3')
            ->setAuthor('Yoshi')
            ->setCreatedAt(new DateTimeImmutable('2020-01-17 22:17:34'))
        ;
        $auditingServices[Comment::class]->getEntityManager()->persist($comment2);

        $comment3 = new Comment();
        $comment3
            ->setPost($post3)
            ->setBody('Second comment about post #3')
            ->setAuthor('Mario')
            ->setCreatedAt(new DateTimeImmutable('2020-01-17 22:17:34'))
        ;
        $auditingServices[Comment::class]->getEntityManager()->persist($comment3);

        $this->flushAll($auditingServices);

        $author1->setFullname('John Doe');
        $auditingServices[Author::class]->getEntityManager()->persist($author1);

        $author3 = new Author();
        $author3
            ->setFullname('Luke Skywalker')
            ->setEmail('luke.skywalker@gmail.com')
        ;
        $auditingServices[Author::class]->getEntityManager()->persist($author3);

        $post4 = new Post();
        $post4
            ->setAuthor($author3)
            ->setTitle('Fourth post')
            ->setBody('Here is the body')
            ->setCreatedAt(new DateTimeImmutable('2020-01-17 22:17:34'))
        ;
        $auditingServices[Post::class]->getEntityManager()->persist($post4);

        $tag1 = new Tag();
        $tag1->setTitle('techno');
        $auditingServices[Tag::class]->getEntityManager()->persist($tag1);

        $tag2 = new Tag();
        $tag2->setTitle('house');
        $auditingServices[Tag::class]->getEntityManager()->persist($tag2);

        $tag3 = new Tag();
        $tag3->setTitle('hardcore');
        $auditingServices[Tag::class]->getEntityManager()->persist($tag3);

        $tag4 = new Tag();
        $tag4->setTitle('jungle');
        $auditingServices[Tag::class]->getEntityManager()->persist($tag4);

        $tag5 = new Tag();
        $tag5->setTitle('gabber');
        $auditingServices[Tag::class]->getEntityManager()->persist($tag5);

        $this->flushAll($auditingServices);

        $post1
            ->addTag($tag1)
            ->addTag($tag2)
        ;
        $post3
            ->addTag($tag1)
            ->addTag($tag3)
            ->addTag($tag5)
        ;
        $post4
            ->addTag($tag2)
            ->addTag($tag4)
            ->addTag($tag5)
        ;

        $this->flushAll($auditingServices);

        $post4
            ->removeTag($tag4)
            ->removeTag($tag5)
        ;
        $this->flushAll($auditingServices);

        $author3->removePost($post4);   // same as $post4->setAuthor(null); but takes care of bidirectional relationship
        $this->flushAll($auditingServices);

        $auditingServices[Author::class]->getEntityManager()->remove($author3);
        $this->flushAll($auditingServices);
    }
}
