<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Auditing\Transaction;

use DateTime;
use DH\Auditor\Model\Transaction;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionProcessor;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use DH\Auditor\Tests\Traits\ReflectionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class TransactionProcessorTest extends TestCase
{
    use ReflectionTrait;
    use DefaultSchemaSetupTrait;

    public function testInsert(): void
    {
        $processor = new TransactionProcessor($this->provider);
        $reader = new Reader($this->provider);
        $method = $this->reflectMethod(TransactionProcessor::class, 'insert');

        $author = new Author();
        $author
            ->setId(1)
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;

        $method->invokeArgs($processor, [
            $this->provider->getEntityManagerForEntity(Author::class),
            $author,
            [
                'fullname' => [null, 'John Doe'],
                'email' => [null, 'john.doe@gmail.com'],
            ],
            'what-a-nice-transaction-hash',
        ]);

        $audits = $reader->createQuery(Author::class)->execute();
        self::assertCount(1, $audits, 'TransactionProcessor::insert() creates an audit entry.');

        $entry = array_shift($audits);
        self::assertSame(1, $entry->getId(), 'audit entry ID is ok.');
        self::assertSame(Transaction::INSERT, $entry->getType(), 'audit entry type is ok.');
        self::assertSame('1', $entry->getUserId(), 'audit entry blame_id is ok.');
        self::assertSame('dark.vador', $entry->getUsername(), 'audit entry blame_user is ok.');
        self::assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        self::assertSame([
            'email' => [
                'new' => 'john.doe@gmail.com',
                'old' => null,
            ],
            'fullname' => [
                'new' => 'John Doe',
                'old' => null,
            ],
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testUpdate(): void
    {
        $processor = new TransactionProcessor($this->provider);
        $reader = new Reader($this->provider);
        $method = $this->reflectMethod(TransactionProcessor::class, 'update');

        $author = new Author();
        $author
            ->setId(1)
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;

        $method->invokeArgs($processor, [
            $this->provider->getEntityManagerForEntity(Author::class),
            $author,
            [
                'fullname' => ['John Doe', 'Dark Vador'],
                'email' => ['john.doe@gmail.com', 'dark.vador@gmail.com'],
            ],
            'what-a-nice-transaction-hash',
        ]);

        $audits = $reader->createQuery(Author::class)->execute();
        self::assertCount(1, $audits, 'TransactionProcessor::update() creates an audit entry.');

        $entry = array_shift($audits);
        self::assertSame(1, $entry->getId(), 'audit entry ID is ok.');
        self::assertSame(Transaction::UPDATE, $entry->getType(), 'audit entry type is ok.');
        self::assertSame('1', $entry->getUserId(), 'audit entry blame_id is ok.');
        self::assertSame('dark.vador', $entry->getUsername(), 'audit entry blame_user is ok.');
        self::assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        self::assertSame([
            'email' => [
                'new' => 'dark.vador@gmail.com',
                'old' => 'john.doe@gmail.com',
            ],
            'fullname' => [
                'new' => 'Dark Vador',
                'old' => 'John Doe',
            ],
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testRemove(): void
    {
        $processor = new TransactionProcessor($this->provider);
        $reader = new Reader($this->provider);
        $method = $this->reflectMethod(TransactionProcessor::class, 'remove');
        $entityManager = $this->provider->getEntityManagerForEntity(Author::class);

        $author = new Author();
        $author
            ->setId(1)
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;

        $method->invokeArgs($processor, [$entityManager, $author, 1, 'what-a-nice-transaction-hash']);

        $audits = $reader->createQuery(Author::class)->execute();
        self::assertCount(1, $audits, 'TransactionProcessor::remove() creates an audit entry.');

        $entry = array_shift($audits);
        self::assertSame(1, $entry->getId(), 'audit entry ID is ok.');
        self::assertSame(Transaction::REMOVE, $entry->getType(), 'audit entry type is ok.');
        self::assertSame('1', $entry->getUserId(), 'audit entry blame_id is ok.');
        self::assertSame('dark.vador', $entry->getUsername(), 'audit entry blame_user is ok.');
        self::assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        self::assertSame([
            'class' => Author::class,
            'id' => 1,
            'label' => Author::class.'#1',
            'table' => $entityManager->getClassMetadata(Author::class)->getTableName(),
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testAssociateOneToMany(): void
    {
        $processor = new TransactionProcessor($this->provider);
        $reader = new Reader($this->provider);
        $method = $this->reflectMethod(TransactionProcessor::class, 'associate');
        $entityManager = $this->provider->getEntityManagerForEntity(Post::class);

        $author = new Author();
        $author
            ->setId(1)
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;

        $post = new Post();
        $post
            ->setId(1)
            ->setAuthor($author)
            ->setTitle('First post')
            ->setBody('Here is the body')
            ->setCreatedAt(new DateTime())
        ;

        $mapping = [
            'fieldName' => 'posts',
            'mappedBy' => 'author',
            'targetEntity' => Post::class,
            'cascade' => [
                0 => 'persist',
                1 => 'remove',
            ],
            'orphanRemoval' => false,
            'fetch' => 2,
            'type' => 4,
            'inversedBy' => null,
            'isOwningSide' => false,
            'sourceEntity' => Author::class,
            'isCascadeRemove' => true,
            'isCascadePersist' => true,
            'isCascadeRefresh' => false,
            'isCascadeMerge' => false,
            'isCascadeDetach' => false,
        ];

        $method->invokeArgs($processor, [$entityManager, $author, $post, $mapping, 'what-a-nice-transaction-hash']);

        $audits = $reader->createQuery(Author::class)->execute();
        self::assertCount(1, $audits, 'TransactionProcessor::associate() creates an audit entry.');

        $entry = array_shift($audits);
        self::assertSame(1, $entry->getId(), 'audit entry ID is ok.');
        self::assertSame(Transaction::ASSOCIATE, $entry->getType(), 'audit entry type is ok.');
        self::assertSame('1', $entry->getUserId(), 'audit entry blame_id is ok.');
        self::assertSame('dark.vador', $entry->getUsername(), 'audit entry blame_user is ok.');
        self::assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        self::assertSame([
            'source' => [
                'class' => Author::class,
                'id' => 1,
                'label' => Author::class.'#1',
                'table' => $entityManager->getClassMetadata(Author::class)->getTableName(),
            ],
            'target' => [
                'class' => Post::class,
                'id' => 1,
                'label' => (string) $post,
                'table' => $entityManager->getClassMetadata(Post::class)->getTableName(),
            ],
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testDissociateOneToMany(): void
    {
        $processor = new TransactionProcessor($this->provider);
        $reader = new Reader($this->provider);
        $method = $this->reflectMethod(TransactionProcessor::class, 'dissociate');
        $entityManager = $this->provider->getEntityManagerForEntity(Author::class);

        $author = new Author();
        $author
            ->setId(1)
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;

        $post = new Post();
        $post
            ->setId(1)
            ->setAuthor($author)
            ->setTitle('First post')
            ->setBody('Here is the body')
            ->setCreatedAt(new DateTime())
        ;

        $mapping = [
            'fieldName' => 'posts',
            'mappedBy' => 'author',
            'targetEntity' => Post::class,
            'cascade' => ['persist', 'remove'],
            'orphanRemoval' => false,
            'fetch' => 2,
            'type' => 4,
            'inversedBy' => null,
            'isOwningSide' => false,
            'sourceEntity' => Author::class,
            'isCascadeRemove' => true,
            'isCascadePersist' => true,
            'isCascadeRefresh' => false,
            'isCascadeMerge' => false,
            'isCascadeDetach' => false,
        ];

        $method->invokeArgs($processor, [$entityManager, $author, $post, $mapping, 'what-a-nice-transaction-hash']);

        $audits = $reader->createQuery(Author::class)->execute();
        self::assertCount(1, $audits, 'TransactionProcessor::dissociate() creates an audit entry.');

        $entry = array_shift($audits);
        self::assertSame(1, $entry->getId(), 'audit entry ID is ok.');
        self::assertSame(Transaction::DISSOCIATE, $entry->getType(), 'audit entry type is ok.');
        self::assertSame('1', $entry->getUserId(), 'audit entry blame_id is ok.');
        self::assertSame('dark.vador', $entry->getUsername(), 'audit entry blame_user is ok.');
        self::assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        self::assertSame([
            'source' => [
                'class' => Author::class,
                'id' => 1,
                'label' => Author::class.'#1',
                'table' => $entityManager->getClassMetadata(Author::class)->getTableName(),
            ],
            'target' => [
                'class' => Post::class,
                'id' => 1,
                'label' => (string) $post,
                'table' => $entityManager->getClassMetadata(Post::class)->getTableName(),
            ],
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testAssociateManyToMany(): void
    {
        $processor = new TransactionProcessor($this->provider);
        $reader = new Reader($this->provider);
        $method = $this->reflectMethod(TransactionProcessor::class, 'associate');
        $entityManager = $this->provider->getEntityManagerForEntity(Author::class);

        $author = new Author();
        $author
            ->setId(1)
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;

        $post = new Post();
        $post
            ->setId(1)
            ->setAuthor($author)
            ->setTitle('First post')
            ->setBody('Here is the body')
            ->setCreatedAt(new DateTime())
        ;

        $tag1 = new Tag();
        $tag1
            ->setId(1)
            ->setTitle('techno')
        ;

        $tag2 = new Tag();
        $tag2
            ->setId(2)
            ->setTitle('house')
        ;

        $post->addTag($tag1);
        $post->addTag($tag2);

        $mapping = [
            'fieldName' => 'tags',
            'joinTable' => [
                'name' => 'post__tag',
                'schema' => null,
                'joinColumns' => [
                    [
                        'name' => 'post_id',
                        'unique' => false,
                        'nullable' => false,
                        'onDelete' => null,
                        'columnDefinition' => null,
                        'referencedColumnName' => 'id',
                    ],
                ],
                'inverseJoinColumns' => [
                    [
                        'name' => 'tag_id',
                        'unique' => false,
                        'nullable' => false,
                        'onDelete' => null,
                        'columnDefinition' => null,
                        'referencedColumnName' => 'id',
                    ],
                ],
            ],
            'targetEntity' => Tag::class,
            'mappedBy' => null,
            'inversedBy' => 'posts',
            'cascade' => ['persist', 'remove'],
            'orphanRemoval' => false,
            'fetch' => 2,
            'type' => 8,
            'isOwningSide' => true,
            'sourceEntity' => Post::class,
            'isCascadeRemove' => true,
            'isCascadePersist' => true,
            'isCascadeRefresh' => false,
            'isCascadeMerge' => false,
            'isCascadeDetach' => false,
            'joinTableColumns' => ['post_id', 'tag_id'],
            'relationToSourceKeyColumns' => [
                'post_id' => 'id',
            ],
            'relationToTargetKeyColumns' => [
                'tag_id' => 'id',
            ],
        ];

        $method->invokeArgs($processor, [$entityManager, $post, $tag1, $mapping, 'what-a-nice-transaction-hash']);
        $method->invokeArgs($processor, [$entityManager, $post, $tag2, $mapping, 'what-a-nice-transaction-hash']);

        $audits = $reader->createQuery(Post::class)->execute();
        self::assertCount(2, $audits, 'TransactionProcessor::associate() creates an audit entry per association.');

        $entry = array_shift($audits);
        self::assertSame(2, $entry->getId(), 'audit entry ID is ok.');
        self::assertSame(Transaction::ASSOCIATE, $entry->getType(), 'audit entry type is ok.');
        self::assertSame('1', $entry->getUserId(), 'audit entry blame_id is ok.');
        self::assertSame('dark.vador', $entry->getUsername(), 'audit entry blame_user is ok.');
        self::assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        self::assertSame([
            'source' => [
                'class' => Post::class,
                'id' => 1,
                'label' => (string) $post,
                'table' => $entityManager->getClassMetadata(Post::class)->getTableName(),
            ],
            'target' => [
                'class' => Tag::class,
                'id' => 2,
                'label' => Tag::class.'#2',
                'table' => $entityManager->getClassMetadata(Tag::class)->getTableName(),
            ],
            'table' => 'post__tag',
        ], $entry->getDiffs(), 'audit entry diffs is ok.');

        $entry = array_shift($audits);
        self::assertSame(1, $entry->getId(), 'audit entry ID is ok.');
        self::assertSame(Transaction::ASSOCIATE, $entry->getType(), 'audit entry type is ok.');
        self::assertSame('1', $entry->getUserId(), 'audit entry blame_id is ok.');
        self::assertSame('dark.vador', $entry->getUsername(), 'audit entry blame_user is ok.');
        self::assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        self::assertSame([
            'source' => [
                'class' => Post::class,
                'id' => 1,
                'label' => (string) $post,
                'table' => $entityManager->getClassMetadata(Post::class)->getTableName(),
            ],
            'target' => [
                'class' => Tag::class,
                'id' => 1,
                'label' => Tag::class.'#1',
                'table' => $entityManager->getClassMetadata(Tag::class)->getTableName(),
            ],
            'table' => 'post__tag',
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testDissociateManyToMany(): void
    {
        $processor = new TransactionProcessor($this->provider);
        $reader = new Reader($this->provider);
        $associateMethod = $this->reflectMethod(TransactionProcessor::class, 'associate');
        $dissociateMethod = $this->reflectMethod(TransactionProcessor::class, 'dissociate');
        $entityManager = $this->provider->getEntityManagerForEntity(Author::class);

        $author = new Author();
        $author
            ->setId(1)
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;

        $post = new Post();
        $post
            ->setId(1)
            ->setAuthor($author)
            ->setTitle('First post')
            ->setBody('Here is the body')
            ->setCreatedAt(new DateTime())
        ;

        $tag1 = new Tag();
        $tag1
            ->setId(1)
            ->setTitle('techno')
        ;

        $tag2 = new Tag();
        $tag2
            ->setId(2)
            ->setTitle('house')
        ;

        $post->addTag($tag1);
        $post->addTag($tag2);

        $mapping = [
            'fieldName' => 'tags',
            'joinTable' => [
                'name' => 'post__tag',
                'schema' => null,
                'joinColumns' => [
                    [
                        'name' => 'post_id',
                        'unique' => false,
                        'nullable' => false,
                        'onDelete' => null,
                        'columnDefinition' => null,
                        'referencedColumnName' => 'id',
                    ],
                ],
                'inverseJoinColumns' => [
                    [
                        'name' => 'tag_id',
                        'unique' => false,
                        'nullable' => false,
                        'onDelete' => null,
                        'columnDefinition' => null,
                        'referencedColumnName' => 'id',
                    ],
                ],
            ],
            'targetEntity' => Tag::class,
            'mappedBy' => null,
            'inversedBy' => 'posts',
            'cascade' => ['persist', 'remove'],
            'orphanRemoval' => false,
            'fetch' => 2,
            'type' => 8,
            'isOwningSide' => true,
            'sourceEntity' => Post::class,
            'isCascadeRemove' => true,
            'isCascadePersist' => true,
            'isCascadeRefresh' => false,
            'isCascadeMerge' => false,
            'isCascadeDetach' => false,
            'joinTableColumns' => ['post_id', 'tag_id'],
            'relationToSourceKeyColumns' => [
                'post_id' => 'id',
            ],
            'relationToTargetKeyColumns' => [
                'tag_id' => 'id',
            ],
        ];

        $associateMethod->invokeArgs($processor, [$entityManager, $post, $tag1, $mapping, 'what-a-nice-transaction-hash']);
        $associateMethod->invokeArgs($processor, [$entityManager, $post, $tag2, $mapping, 'what-a-nice-transaction-hash']);

        $dissociateMethod->invokeArgs($processor, [$entityManager, $post, $tag2, $mapping, 'what-a-nice-transaction-hash']);

        $audits = $reader->createQuery(Post::class)->execute();
        self::assertCount(3, $audits, 'TransactionProcessor::dissociate() creates an audit entry.');

        $entry = array_shift($audits);
        self::assertSame(3, $entry->getId(), 'audit entry ID is ok.');
        self::assertSame(Transaction::DISSOCIATE, $entry->getType(), 'audit entry type is ok.');
        self::assertSame('1', $entry->getUserId(), 'audit entry blame_id is ok.');
        self::assertSame('dark.vador', $entry->getUsername(), 'audit entry blame_user is ok.');
        self::assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        self::assertSame([
            'source' => [
                'class' => Post::class,
                'id' => 1,
                'label' => 'First post',
                'table' => $entityManager->getClassMetadata(Post::class)->getTableName(),
            ],
            'target' => [
                'class' => Tag::class,
                'id' => 2,
                'label' => Tag::class.'#2',
                'table' => $entityManager->getClassMetadata(Tag::class)->getTableName(),
            ],
            'table' => 'post__tag',
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
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
