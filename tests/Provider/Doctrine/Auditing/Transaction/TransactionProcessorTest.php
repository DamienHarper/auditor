<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Auditing\Transaction;

use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionProcessor;
use DH\Auditor\Provider\Doctrine\Model\Transaction;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use DH\Auditor\Tests\Traits\ReflectionTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class TransactionProcessorTest extends TestCase
{
    use DefaultSchemaSetupTrait;
    use ReflectionTrait;

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

        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity(Author::class);
        $entityManager = $storageService->getEntityManager();
        $method->invokeArgs($processor, [
            $entityManager,
            $author,
            [
                'fullname' => [null, 'John Doe'],
                'email' => [null, 'john.doe@gmail.com'],
            ],
            'what-a-nice-transaction-hash',
        ]);

        $audits = $reader->createQuery(Author::class)->execute();
        $this->assertCount(1, $audits, 'TransactionProcessor::insert() creates an audit entry.');

        $entry = array_shift($audits);
        $this->assertSame(1, $entry->getId(), 'audit entry ID is ok.');
        $this->assertSame(Transaction::INSERT, $entry->getType(), 'audit entry type is ok.');
        $this->assertContainsEquals($entry->getUserId(), ['1', '2'], 'audit entry blame_id is ok.');
        $this->assertContainsEquals($entry->getUsername(), ['dark.vador', 'anakin.skywalker'], 'audit entry blame_user is ok.');
        $this->assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        $this->assertSame([
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

        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity(Author::class);
        $entityManager = $storageService->getEntityManager();
        $method->invokeArgs($processor, [
            $entityManager,
            $author,
            [
                'fullname' => ['John Doe', 'Dark Vador'],
                'email' => ['john.doe@gmail.com', 'dark.vador@gmail.com'],
            ],
            'what-a-nice-transaction-hash',
        ]);

        $audits = $reader->createQuery(Author::class)->execute();
        $this->assertCount(1, $audits, 'TransactionProcessor::update() creates an audit entry.');

        $entry = array_shift($audits);
        $this->assertSame(1, $entry->getId(), 'audit entry ID is ok.');
        $this->assertSame(Transaction::UPDATE, $entry->getType(), 'audit entry type is ok.');
        $this->assertContainsEquals($entry->getUserId(), ['1', '2'], 'audit entry blame_id is ok.');
        $this->assertContainsEquals($entry->getUsername(), ['dark.vador', 'anakin.skywalker'], 'audit entry blame_user is ok.');
        $this->assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        $this->assertSame([
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

        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity(Author::class);
        $entityManager = $storageService->getEntityManager();

        $author = new Author();
        $author
            ->setId(1)
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;

        $method->invokeArgs($processor, [$entityManager, $author, 1, 'what-a-nice-transaction-hash']);

        $audits = $reader->createQuery(Author::class)->execute();
        $this->assertCount(1, $audits, 'TransactionProcessor::remove() creates an audit entry.');

        $entry = array_shift($audits);
        $this->assertSame(1, $entry->getId(), 'audit entry ID is ok.');
        $this->assertSame(Transaction::REMOVE, $entry->getType(), 'audit entry type is ok.');
        $this->assertContainsEquals($entry->getUserId(), ['1', '2'], 'audit entry blame_id is ok.');
        $this->assertContainsEquals($entry->getUsername(), ['dark.vador', 'anakin.skywalker'], 'audit entry blame_user is ok.');
        $this->assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        $this->assertSame([
            'class' => Author::class,
            'id' => 1,
            'label' => 'John Doe',  // Author::class.'#1',
            'table' => $entityManager->getClassMetadata(Author::class)->getTableName(),
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testAssociateOneToMany(): void
    {
        $processor = new TransactionProcessor($this->provider);
        $reader = new Reader($this->provider);
        $method = $this->reflectMethod(TransactionProcessor::class, 'associate');

        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity(Post::class);
        $entityManager = $storageService->getEntityManager();

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
            ->setCreatedAt(new \DateTimeImmutable())
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
        $this->assertCount(1, $audits, 'TransactionProcessor::associate() creates an audit entry.');

        $entry = array_shift($audits);
        $this->assertSame(1, $entry->getId(), 'audit entry ID is ok.');
        $this->assertSame(Transaction::ASSOCIATE, $entry->getType(), 'audit entry type is ok.');
        $this->assertContainsEquals($entry->getUserId(), ['1', '2'], 'audit entry blame_id is ok.');
        $this->assertContainsEquals($entry->getUsername(), ['dark.vador', 'anakin.skywalker'], 'audit entry blame_user is ok.');
        $this->assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        $this->assertSame([
            'is_owning_side' => false,
            'source' => [
                'class' => Author::class,
                'field' => 'posts',
                'id' => 1,
                'label' => 'John Doe',  // Author::class.'#1',
                'table' => $entityManager->getClassMetadata(Author::class)->getTableName(),
            ],
            'target' => [
                'class' => Post::class,
                'field' => 'author',
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

        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity(Author::class);
        $entityManager = $storageService->getEntityManager();

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
            ->setCreatedAt(new \DateTimeImmutable())
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
        $this->assertCount(1, $audits, 'TransactionProcessor::dissociate() creates an audit entry.');

        $entry = array_shift($audits);
        $this->assertSame(1, $entry->getId(), 'audit entry ID is ok.');
        $this->assertSame(Transaction::DISSOCIATE, $entry->getType(), 'audit entry type is ok.');
        $this->assertContainsEquals($entry->getUserId(), ['1', '2'], 'audit entry blame_id is ok.');
        $this->assertContainsEquals($entry->getUsername(), ['dark.vador', 'anakin.skywalker'], 'audit entry blame_user is ok.');
        $this->assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        $this->assertSame([
            'is_owning_side' => false,
            'source' => [
                'class' => Author::class,
                'field' => 'posts',
                'id' => 1,
                'label' => 'John Doe',  // Author::class.'#1',
                'table' => $entityManager->getClassMetadata(Author::class)->getTableName(),
            ],
            'target' => [
                'class' => Post::class,
                'field' => 'author',
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

        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity(Author::class);
        $entityManager = $storageService->getEntityManager();

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
            ->setCreatedAt(new \DateTimeImmutable())
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
        $this->assertCount(2, $audits, 'TransactionProcessor::associate() creates an audit entry per association.');

        $entry = array_shift($audits);
        $this->assertSame(2, $entry->getId(), 'audit entry ID is ok.');
        $this->assertSame(Transaction::ASSOCIATE, $entry->getType(), 'audit entry type is ok.');
        $this->assertContainsEquals($entry->getUserId(), ['1', '2'], 'audit entry blame_id is ok.');
        $this->assertContainsEquals($entry->getUsername(), ['dark.vador', 'anakin.skywalker'], 'audit entry blame_user is ok.');
        $this->assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        $this->assertSame([
            'is_owning_side' => true,
            'source' => [
                'class' => Post::class,
                'field' => 'tags',
                'id' => 1,
                'label' => (string) $post,
                'table' => $entityManager->getClassMetadata(Post::class)->getTableName(),
            ],
            'table' => 'post__tag',
            'target' => [
                'class' => Tag::class,
                'field' => 'posts',
                'id' => 2,
                'label' => 'house',     // Tag::class.'#2',
                'table' => $entityManager->getClassMetadata(Tag::class)->getTableName(),
            ],
        ], $entry->getDiffs(), 'audit entry diffs is ok.');

        $entry = array_shift($audits);
        $this->assertSame(1, $entry->getId(), 'audit entry ID is ok.');
        $this->assertSame(Transaction::ASSOCIATE, $entry->getType(), 'audit entry type is ok.');
        $this->assertContainsEquals($entry->getUserId(), ['1', '2'], 'audit entry blame_id is ok.');
        $this->assertContainsEquals($entry->getUsername(), ['dark.vador', 'anakin.skywalker'], 'audit entry blame_user is ok.');
        $this->assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        $this->assertSame([
            'is_owning_side' => true,
            'source' => [
                'class' => Post::class,
                'field' => 'tags',
                'id' => 1,
                'label' => (string) $post,
                'table' => $entityManager->getClassMetadata(Post::class)->getTableName(),
            ],
            'table' => 'post__tag',
            'target' => [
                'class' => Tag::class,
                'field' => 'posts',
                'id' => 1,
                'label' => 'techno',    // Tag::class.'#1',
                'table' => $entityManager->getClassMetadata(Tag::class)->getTableName(),
            ],
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testDissociateManyToMany(): void
    {
        $processor = new TransactionProcessor($this->provider);
        $reader = new Reader($this->provider);
        $associateMethod = $this->reflectMethod(TransactionProcessor::class, 'associate');
        $dissociateMethod = $this->reflectMethod(TransactionProcessor::class, 'dissociate');

        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity(Author::class);
        $entityManager = $storageService->getEntityManager();

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
            ->setCreatedAt(new \DateTimeImmutable())
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
        $this->assertCount(3, $audits, 'TransactionProcessor::dissociate() creates an audit entry.');

        $entry = array_shift($audits);
        $this->assertSame(3, $entry->getId(), 'audit entry ID is ok.');
        $this->assertSame(Transaction::DISSOCIATE, $entry->getType(), 'audit entry type is ok.');
        $this->assertContainsEquals($entry->getUserId(), ['1', '2'], 'audit entry blame_id is ok.');
        $this->assertContainsEquals($entry->getUsername(), ['dark.vador', 'anakin.skywalker'], 'audit entry blame_user is ok.');
        $this->assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        $this->assertSame([
            'is_owning_side' => true,
            'source' => [
                'class' => Post::class,
                'field' => 'tags',
                'id' => 1,
                'label' => 'First post',
                'table' => $entityManager->getClassMetadata(Post::class)->getTableName(),
            ],
            'table' => 'post__tag',
            'target' => [
                'class' => Tag::class,
                'field' => 'posts',
                'id' => 2,
                'label' => 'house',     // Tag::class.'#2',
                'table' => $entityManager->getClassMetadata(Tag::class)->getTableName(),
            ],
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testProcessInsertions(): void
    {
        $reader = new Reader($this->provider);
        $processor = new TransactionProcessor($this->provider);

        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity(Author::class);
        $entityManager = $storageService->getEntityManager();

        $transaction = new Transaction($entityManager);

        $author = new Author();
        $author
            ->setId(1)
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;

        $transaction->insert(
            $author,
            [
                'fullname' => [null, 'John Doe'],
                'email' => [null, 'john.doe@gmail.com'],
            ],
        );

        $processor->process($transaction);

        $audits = $reader->createQuery(Author::class)->execute();
        $this->assertCount(1, $audits, 'TransactionProcessor::processInsertions() creates an "'.Transaction::INSERT.'" audit entry.');
    }

    public function testProcessUpdates(): void
    {
        $reader = new Reader($this->provider);
        $processor = new TransactionProcessor($this->provider);

        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity(Author::class);
        $entityManager = $storageService->getEntityManager();

        $transaction = new Transaction($entityManager);

        $author = new Author();
        $author
            ->setId(1)
            ->setFullname('John Doze')
            ->setEmail('john.doze@gmail.com')
        ;

        $transaction->update(
            $author,
            [
                'fullname' => ['John Doe', 'John Doze'],
                'email' => ['john.doe@gmail.com', 'john.doze@gmail.com'],
            ],
        );

        $processor->process($transaction);

        $audits = $reader->createQuery(Author::class)->execute();
        $this->assertCount(1, $audits, 'TransactionProcessor::processUpdates() creates an "'.Transaction::UPDATE.'" audit entry.');

        $transaction->reset();

        $transaction->update(
            $author,
            [],
        );

        $processor->process($transaction);

        $audits = $reader->createQuery(Author::class)->execute();
        $this->assertCount(1, $audits, 'TransactionProcessor::processUpdates() does not create an "'.Transaction::UPDATE.'" audit entry with empty diffs.');
    }

    public function testProcessAssociations(): void
    {
        $reader = new Reader($this->provider);
        $processor = new TransactionProcessor($this->provider);

        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity(Author::class);
        $entityManager = $storageService->getEntityManager();

        $transaction = new Transaction($entityManager);

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
            ->setCreatedAt(new \DateTimeImmutable())
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

        $transaction->associate(
            $author,
            $post,
            $mapping,
        );

        $processor->process($transaction);

        $audits = $reader->createQuery(Author::class)->execute();
        $this->assertCount(1, $audits, 'TransactionProcessor::processAssociations() creates an "'.Transaction::ASSOCIATE.'" audit entry.');
    }

    public function testProcessDissociations(): void
    {
        $reader = new Reader($this->provider);
        $processor = new TransactionProcessor($this->provider);

        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity(Author::class);
        $entityManager = $storageService->getEntityManager();

        $transaction = new Transaction($entityManager);

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
            ->setCreatedAt(new \DateTimeImmutable())
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

        $transaction->dissociate(
            $author,
            $post,
            $mapping,
        );

        $processor->process($transaction);

        $audits = $reader->createQuery(Author::class)->execute();
        $this->assertCount(1, $audits, 'TransactionProcessor::processDissociations() creates an "'.Transaction::DISSOCIATE.'" audit entry.');
    }

    public function testProcessDeletions(): void
    {
        $reader = new Reader($this->provider);
        $processor = new TransactionProcessor($this->provider);

        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity(Author::class);
        $entityManager = $storageService->getEntityManager();

        $transaction = new Transaction($entityManager);

        $author = new Author();
        $author
            ->setId(1)
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;

        $transaction->remove(
            $author,
            1,
        );

        $processor->process($transaction);

        $audits = $reader->createQuery(Author::class)->execute();
        $this->assertCount(1, $audits, 'TransactionProcessor::processDeletions() creates a "'.Transaction::REMOVE.'" audit entry.');
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
