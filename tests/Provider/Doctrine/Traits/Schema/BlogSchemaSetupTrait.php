<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits\Schema;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;

trait BlogSchemaSetupTrait
{
    use SchemaSetupTrait;

    /**
     * @var DoctrineProvider
     */
    private $provider;

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Author::class => ['enabled' => true],
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
     * +-Author 3
     *   +-Post 4
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
     * +-PostTag 4.5.
     */
    private function setupEntities(): void
    {
        $entityManagers = [
            Author::class => $this->provider->getEntityManagerForEntity(Author::class),
            Post::class => $this->provider->getEntityManagerForEntity(Post::class),
            Comment::class => $this->provider->getEntityManagerForEntity(Comment::class),
            Tag::class => $this->provider->getEntityManagerForEntity(Tag::class),
        ];

        $author1 = new Author();
        $author1
            ->setFullname('John')
            ->setEmail('john.doe@gmail.com')
        ;
        $entityManagers[Author::class]->persist($author1);

        $post1 = new Post();
        $post1
            ->setAuthor($author1)
            ->setTitle('First post')
            ->setBody('Here is the body')
            ->setCreatedAt(new \DateTime())
        ;
        $entityManagers[Post::class]->persist($post1);

        $comment1 = new Comment();
        $comment1
            ->setPost($post1)
            ->setBody('First comment about post #1')
            ->setAuthor('Dark Vador')
            ->setCreatedAt(new \DateTime())
        ;
        $entityManagers[Comment::class]->persist($comment1);

        $post2 = new Post();
        $post2
            ->setAuthor($author1)
            ->setTitle('Second post')
            ->setBody('Here is another body')
            ->setCreatedAt(new \DateTime())
        ;
        $entityManagers[Post::class]->persist($post2);

        $author2 = new Author();
        $author2
            ->setFullname('Chuck Norris')
            ->setEmail('chuck.norris@gmail.com')
        ;
        $entityManagers[Author::class]->persist($author2);

        $post3 = new Post();
        $post3
            ->setAuthor($author2)
            ->setTitle('Second post')
            ->setBody('Here is another body')
            ->setCreatedAt(new \DateTime())
        ;
        $entityManagers[Post::class]->persist($post3);

        $comment2 = new Comment();
        $comment2
            ->setPost($post3)
            ->setBody('First comment about post #3')
            ->setAuthor('Yoshi')
            ->setCreatedAt(new \DateTime())
        ;
        $entityManagers[Comment::class]->persist($comment2);

        $comment3 = new Comment();
        $comment3
            ->setPost($post3)
            ->setBody('Second comment about post #3')
            ->setAuthor('Mario')
            ->setCreatedAt(new \DateTime())
        ;
        $entityManagers[Comment::class]->persist($comment3);

        $this->flushAll($entityManagers);

        $author1->setFullname('John Doe');
        $entityManagers[Author::class]->persist($author1);

        $author3 = new Author();
        $author3
            ->setFullname('Luke Slywalker')
            ->setEmail('luck.skywalker@gmail.com')
        ;
        $entityManagers[Author::class]->persist($author3);

        $post4 = new Post();
        $post4
            ->setAuthor($author3)
            ->setTitle('First post')
            ->setBody('Here is the body')
            ->setCreatedAt(new \DateTime())
        ;
        $entityManagers[Post::class]->persist($post4);

        $tag1 = new Tag();
        $tag1->setTitle('techno');
        $entityManagers[Tag::class]->persist($tag1);

        $tag2 = new Tag();
        $tag2->setTitle('house');
        $entityManagers[Tag::class]->persist($tag2);

        $tag3 = new Tag();
        $tag3->setTitle('hardcore');
        $entityManagers[Tag::class]->persist($tag3);

        $tag4 = new Tag();
        $tag4->setTitle('jungle');
        $entityManagers[Tag::class]->persist($tag4);

        $tag5 = new Tag();
        $tag5->setTitle('gabber');
        $entityManagers[Tag::class]->persist($tag5);

        $this->flushAll($entityManagers);

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

        $this->flushAll($entityManagers);

        $post4
            ->removeTag($tag4)
            ->removeTag($tag5)
        ;
        $this->flushAll($entityManagers);

        $author3->removePost($post4);
        $this->flushAll($entityManagers);

        $entityManagers[Author::class]->remove($author3);
        $this->flushAll($entityManagers);
    }

    private function flushAll(array $entityManagers): void
    {
        foreach ($entityManagers as $entity => $entityManager) {
            $entityManager->flush();
        }
    }
}
