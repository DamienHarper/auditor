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

    private function setupEntities(): void
    {
        $auditingServices = [
            Author::class => $this->provider->getAuditingServiceForEntity(Author::class),
            Post::class => $this->provider->getAuditingServiceForEntity(Post::class),
            Comment::class => $this->provider->getAuditingServiceForEntity(Comment::class),
            Tag::class => $this->provider->getAuditingServiceForEntity(Tag::class),
        ];

        // START TRANSACTION #1

        // Authors
        $author1 = new Author();
        $author1
            ->setFullname('John')
            ->setEmail('john.doe@gmail.com')
        ;
        $auditingServices[Author::class]->getEntityManager()->persist($author1);

        $author2 = new Author();
        $author2
            ->setFullname('Chuck Norris')
            ->setEmail('chuck.norris@gmail.com')
        ;
        $auditingServices[Author::class]->getEntityManager()->persist($author2);

        $author3 = new Author();
        $author3
            ->setFullname('Luke Skywalker')
            ->setEmail('luke.skywalker@gmail.com')
        ;
        $auditingServices[Author::class]->getEntityManager()->persist($author3);

        // Posts
        $post1 = new Post();
        $post1
            ->setTitle('First post')
            ->setBody('Here is the body')
            ->setCreatedAt(new DateTimeImmutable('2020-01-17 22:17:34'))
        ;
        $auditingServices[Post::class]->getEntityManager()->persist($post1);

        $post2 = new Post();
        $post2
            ->setTitle('Second post')
            ->setBody('Here is another body')
            ->setCreatedAt(new DateTimeImmutable('2020-01-17 22:17:34'))
        ;
        $auditingServices[Post::class]->getEntityManager()->persist($post2);

        $post3 = new Post();
        $post3
            ->setTitle('Third post')
            ->setBody('Here is another body')
            ->setCreatedAt(new DateTimeImmutable('2020-01-17 22:17:34'))
        ;
        $auditingServices[Post::class]->getEntityManager()->persist($post3);

        $post4 = new Post();
        $post4
            ->setTitle('Fourth post')
            ->setBody('Here is the body')
            ->setCreatedAt(new DateTimeImmutable('2020-01-17 22:17:34'))
        ;
        $auditingServices[Post::class]->getEntityManager()->persist($post4);

        // Comments
        $comment1 = new Comment();
        $comment1
            ->setBody('First comment about post #1')
            ->setAuthor('Dark Vador')
            ->setCreatedAt(new DateTimeImmutable('2020-01-17 22:17:34'))
        ;
        $auditingServices[Comment::class]->getEntityManager()->persist($comment1);

        $comment2 = new Comment();
        $comment2
            ->setBody('First comment about post #3')
            ->setAuthor('Yoshi')
            ->setCreatedAt(new DateTimeImmutable('2020-01-17 22:17:34'))
        ;
        $auditingServices[Comment::class]->getEntityManager()->persist($comment2);

        $comment3 = new Comment();
        $comment3
            ->setBody('Second comment about post #3')
            ->setAuthor('Mario')
            ->setCreatedAt(new DateTimeImmutable('2020-01-17 22:17:34'))
        ;
        $auditingServices[Comment::class]->getEntityManager()->persist($comment3);

        // Tags
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

        // END TRANSACTION #1

        // START TRANSACTION #2

        // Updates
        $author1->setFullname('John Doe');
        $auditingServices[Author::class]->getEntityManager()->persist($author1);

        $this->flushAll($auditingServices);

        // END TRANSACTION #2

        // START TRANSACTION #3

        // Association author<->post
        $post1->setAuthor($author1);
        $auditingServices[Post::class]->getEntityManager()->persist($post1);

        $post2->setAuthor($author1);
        $auditingServices[Post::class]->getEntityManager()->persist($post2);

        $author2->addPost($post3);
        $auditingServices[Author::class]->getEntityManager()->persist($author2);

        $post1->setCoauthor($author2);
        $auditingServices[Post::class]->getEntityManager()->persist($post1);

        $post3->setCoauthor($author3);
        $auditingServices[Post::class]->getEntityManager()->persist($post3);

        $author3->addPost($post4);
        $auditingServices[Author::class]->getEntityManager()->persist($author3);

        // Association post<->comment
        $post1->addComment($comment1);
        $auditingServices[Post::class]->getEntityManager()->persist($post1);

        $post3
            ->addComment($comment2)
            ->addComment($comment3)
        ;
        $auditingServices[Post::class]->getEntityManager()->persist($post3);

        $this->flushAll($auditingServices);

        // END TRANSACTION #3

        // START TRANSACTION #4

        // Association post<->tag
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

        // END TRANSACTION #4

        // START TRANSACTION #5

        // Dissociation post<->tag
        $post4
            ->removeTag($tag4)
            ->removeTag($tag5)
        ;

        // Dissociation author<->post
        $author3->removePost($post4);

        // Dissociation coauthor<->post
        $author3->removePost($post4);
        $post3->setCoauthor(null);

        // Delete author
        $auditingServices[Author::class]->getEntityManager()->detach($author3);
        $author3 = $auditingServices[Author::class]->getEntityManager()->find(Author::class, $author3->getId());
        $auditingServices[Author::class]->getEntityManager()->remove($author3);

        $this->flushAll($auditingServices);

        // END TRANSACTION #5
    }
}
