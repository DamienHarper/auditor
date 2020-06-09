<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits\Schema;

use DateTime;
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
        $storageServices = [
            Author::class => $this->provider->getStorageServiceForEntity(Author::class),
            Post::class => $this->provider->getStorageServiceForEntity(Post::class),
            Comment::class => $this->provider->getStorageServiceForEntity(Comment::class),
            Tag::class => $this->provider->getStorageServiceForEntity(Tag::class),
        ];

        $author1 = new Author();
        $author1
            ->setFullname('John')
            ->setEmail('john.doe@gmail.com')
        ;
        $storageServices[Author::class]->getEntityManager()->persist($author1);

        $post1 = new Post();
        $post1
            ->setAuthor($author1)
            ->setTitle('First post')
            ->setBody('Here is the body')
            ->setCreatedAt(new DateTime())
        ;
        $storageServices[Post::class]->getEntityManager()->persist($post1);

        $comment1 = new Comment();
        $comment1
            ->setPost($post1)
            ->setBody('First comment about post #1')
            ->setAuthor('Dark Vador')
            ->setCreatedAt(new DateTime())
        ;
        $storageServices[Comment::class]->getEntityManager()->persist($comment1);

        $post2 = new Post();
        $post2
            ->setAuthor($author1)
            ->setTitle('Second post')
            ->setBody('Here is another body')
            ->setCreatedAt(new DateTime())
        ;
        $storageServices[Post::class]->getEntityManager()->persist($post2);

        $author2 = new Author();
        $author2
            ->setFullname('Chuck Norris')
            ->setEmail('chuck.norris@gmail.com')
        ;
        $storageServices[Author::class]->getEntityManager()->persist($author2);

        $post3 = new Post();
        $post3
            ->setAuthor($author2)
            ->setTitle('Second post')
            ->setBody('Here is another body')
            ->setCreatedAt(new DateTime())
        ;
        $storageServices[Post::class]->getEntityManager()->persist($post3);

        $comment2 = new Comment();
        $comment2
            ->setPost($post3)
            ->setBody('First comment about post #3')
            ->setAuthor('Yoshi')
            ->setCreatedAt(new DateTime())
        ;
        $storageServices[Comment::class]->getEntityManager()->persist($comment2);

        $comment3 = new Comment();
        $comment3
            ->setPost($post3)
            ->setBody('Second comment about post #3')
            ->setAuthor('Mario')
            ->setCreatedAt(new DateTime())
        ;
        $storageServices[Comment::class]->getEntityManager()->persist($comment3);

        $this->flushAll($storageServices);

        $author1->setFullname('John Doe');
        $storageServices[Author::class]->getEntityManager()->persist($author1);

        $author3 = new Author();
        $author3
            ->setFullname('Luke Slywalker')
            ->setEmail('luke.skywalker@gmail.com')
        ;
        $storageServices[Author::class]->getEntityManager()->persist($author3);

        $post4 = new Post();
        $post4
            ->setAuthor($author3)
            ->setTitle('First post')
            ->setBody('Here is the body')
            ->setCreatedAt(new DateTime())
        ;
        $storageServices[Post::class]->getEntityManager()->persist($post4);

        $tag1 = new Tag();
        $tag1->setTitle('techno');
        $storageServices[Tag::class]->getEntityManager()->persist($tag1);

        $tag2 = new Tag();
        $tag2->setTitle('house');
        $storageServices[Tag::class]->getEntityManager()->persist($tag2);

        $tag3 = new Tag();
        $tag3->setTitle('hardcore');
        $storageServices[Tag::class]->getEntityManager()->persist($tag3);

        $tag4 = new Tag();
        $tag4->setTitle('jungle');
        $storageServices[Tag::class]->getEntityManager()->persist($tag4);

        $tag5 = new Tag();
        $tag5->setTitle('gabber');
        $storageServices[Tag::class]->getEntityManager()->persist($tag5);

        $this->flushAll($storageServices);

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

        $this->flushAll($storageServices);

        $post4
            ->removeTag($tag4)
            ->removeTag($tag5)
        ;
        $this->flushAll($storageServices);

        $author3->removePost($post4);
        $this->flushAll($storageServices);

        $storageServices[Author::class]->getEntityManager()->remove($author3);
        $this->flushAll($storageServices);
    }
}
