<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Reader;

use DateTime;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Model\Entry;
use DH\Auditor\Model\Transaction;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\BlogSchemaSetupTrait;
use DH\Auditor\Tests\Traits\ReflectionTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidArgumentException as OptionsResolverInvalidArgumentException;

/**
 * @internal
 */
final class ReaderTest extends TestCase
{
    use BlogSchemaSetupTrait;
    use ReaderTrait;
    use ReflectionTrait;

    public function testCheckAuditable(): void
    {
        $entities = [
            Post::class => null,
            Comment::class => null,
        ];

        $this->provider->getConfiguration()->setEntities($entities);
        $reader = $this->createReader();

        // below should not throw exception as Post is auditable
        $reflectedMethod = $this->reflectMethod($reader, 'checkAuditable');
        $reflectedMethod->invokeArgs($reader, [Post::class]);

        // ensure an exception is thrown with an undefined entity
        $this->expectException(InvalidArgumentException::class);
        $reflectedMethod->invokeArgs($reader, ['FakeEntity']);
    }

    public function testGetEntityTableName(): void
    {
        $entities = [
            Post::class => null,
            Comment::class => null,
        ];

        $this->provider->getConfiguration()->setEntities($entities);
        $reader = $this->createReader();

        self::assertSame('post', $reader->getEntityTableName(Post::class), 'tablename is ok.');
        self::assertSame('comment', $reader->getEntityTableName(Comment::class), 'tablename is ok.');
    }

    /**
     * @depends testGetEntityTableName
     */
    public function testGetEntityTableAuditName(): void
    {
        $entities = [
            Post::class => null,
            Comment::class => null,
        ];

        $this->provider->getConfiguration()->setEntities($entities);
        $reader = $this->createReader();

        self::assertSame('post_audit', $reader->getEntityAuditTableName(Post::class), 'tablename is ok.');
        self::assertSame('comment_audit', $reader->getEntityAuditTableName(Comment::class), 'tablename is ok.');
    }

    public function testGetAudits(): void
    {
        $reader = $this->createReader();

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Author::class)->execute();
        self::assertIsInt($audits[0]->getId());
        self::assertIsString($audits[0]->getObjectId());
        self::assertNull($audits[0]->getDiscriminator());
        self::assertIsString($audits[0]->getTransactionHash());
        self::assertIsArray($audits[0]->getDiffs());
        self::assertIsString($audits[0]->getUserId());
        self::assertIsString($audits[0]->getUsername());
        self::assertIsString($audits[0]->getUserFqdn());
        self::assertSame('main', $audits[0]->getUserFirewall());
        self::assertIsString($audits[0]->getIp());
        if (method_exists(self::class, 'assertMatchesRegularExpression')) {
            self::assertMatchesRegularExpression('#\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}#', $audits[0]->getCreatedAt());
        } else {
            self::assertRegExp('#\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}#', $audits[0]->getCreatedAt());
        }

        $i = 0;
        self::assertCount(5, $audits, 'result count is ok.');
        self::assertSame(Transaction::REMOVE, $audits[$i++]->getType(), 'entry'.$i.' is a remove operation.');
        self::assertSame(Transaction::UPDATE, $audits[$i++]->getType(), 'entry'.$i.' is an update operation.');
        self::assertSame(Transaction::INSERT, $audits[$i++]->getType(), 'entry'.$i.' is an insert operation.');
        self::assertSame(Transaction::INSERT, $audits[$i++]->getType(), 'entry'.$i.' is an insert operation.');
        self::assertSame(Transaction::INSERT, $audits[$i++]->getType(), 'entry'.$i.' is an insert operation.');

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Post::class)->execute();

        $i = 0;
        self::assertCount(15, $audits, 'result count is ok.');
        self::assertSame(Transaction::UPDATE, $audits[$i++]->getType(), 'entry'.$i.' is an update operation.');
        self::assertSame(Transaction::DISSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is a dissociate operation.');
        self::assertSame(Transaction::DISSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is a dissociate operation.');
        self::assertSame(Transaction::ASSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is an associate operation.');
        self::assertSame(Transaction::ASSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is an associate operation.');
        self::assertSame(Transaction::ASSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is an associate operation.');
        self::assertSame(Transaction::ASSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is an associate operation.');
        self::assertSame(Transaction::ASSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is an associate operation.');
        self::assertSame(Transaction::ASSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is an associate operation.');
        self::assertSame(Transaction::ASSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is an associate operation.');
        self::assertSame(Transaction::ASSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is an associate operation.');
        self::assertSame(Transaction::INSERT, $audits[$i++]->getType(), 'entry'.$i.' is an insert operation.');
        self::assertSame(Transaction::INSERT, $audits[$i++]->getType(), 'entry'.$i.' is an insert operation.');
        self::assertSame(Transaction::INSERT, $audits[$i++]->getType(), 'entry'.$i.' is an insert operation.');
        self::assertSame(Transaction::INSERT, $audits[$i++]->getType(), 'entry'.$i.' is an insert operation.');

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Comment::class)->execute();

        $i = 0;
        self::assertCount(3, $audits, 'result count is ok.');
        self::assertSame(Transaction::INSERT, $audits[$i++]->getType(), 'entry'.$i.' is an insert operation.');
        self::assertSame(Transaction::INSERT, $audits[$i++]->getType(), 'entry'.$i.' is an insert operation.');
        self::assertSame(Transaction::INSERT, $audits[$i++]->getType(), 'entry'.$i.' is an insert operation.');

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Tag::class)->execute();

        $i = 0;
        self::assertCount(15, $audits, 'result count is ok.');
        self::assertSame(Transaction::DISSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is a dissociate operation.');
        self::assertSame(Transaction::DISSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is a dissociate operation.');
        self::assertSame(Transaction::ASSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is an associate operation.');
        self::assertSame(Transaction::ASSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is an associate operation.');
        self::assertSame(Transaction::ASSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is an associate operation.');
        self::assertSame(Transaction::ASSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is an associate operation.');
        self::assertSame(Transaction::ASSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is an associate operation.');
        self::assertSame(Transaction::ASSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is an associate operation.');
        self::assertSame(Transaction::ASSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is an associate operation.');
        self::assertSame(Transaction::ASSOCIATE, $audits[$i++]->getType(), 'entry'.$i.' is an associate operation.');
        self::assertSame(Transaction::INSERT, $audits[$i++]->getType(), 'entry'.$i.' is an insert operation.');
        self::assertSame(Transaction::INSERT, $audits[$i++]->getType(), 'entry'.$i.' is an insert operation.');
        self::assertSame(Transaction::INSERT, $audits[$i++]->getType(), 'entry'.$i.' is an insert operation.');
        self::assertSame(Transaction::INSERT, $audits[$i++]->getType(), 'entry'.$i.' is an insert operation.');
        self::assertSame(Transaction::INSERT, $audits[$i++]->getType(), 'entry'.$i.' is an insert operation.');

        $this->expectException(OptionsResolverInvalidArgumentException::class);
        $reader->createQuery(Author::class, ['page' => 0])->execute();
        $reader->createQuery(Author::class, ['page' => -1])->execute();
        $reader->createQuery(Author::class, ['page_size' => 0])->execute();
        $reader->createQuery(Author::class, ['page_size' => -1])->execute();
    }

    /**
     * @depends testGetAudits
     */
    public function testGetAuditsPager(): void
    {
        $reader = $this->createReader();

        /** @var Entry[] $audits */
        $pager = $reader->paginate($reader->createQuery(Author::class), 1, 2);
        self::assertIsArray($pager);
        self::assertFalse($pager['hasPreviousPage'], 'Pager is at page 1.');
        self::assertTrue($pager['hasNextPage'], 'Pager has next page.');
        self::assertTrue($pager['haveToPaginate'], 'Pager has to paginate.');
        self::assertSame(3, $pager['numPages'], 'Pager has 3 pages.');
    }

    /**
     * @depends testGetAudits
     */
    public function testGetAuditsByDate(): void
    {
        $reader = $this->createReader();

        /** @var Entry[] $audits */
        $audits = $reader
            ->createQuery(Author::class)
            ->addDateRangeFilter(Query::CREATED_AT, new DateTime('-1 day'))
            ->execute()
        ;

        $i = 0;
        self::assertCount(5, $audits, 'result count is ok.');
        self::assertSame(Transaction::REMOVE, $audits[$i++]->getType(), 'entry'.$i.' is a remove operation.');
        self::assertSame(Transaction::UPDATE, $audits[$i++]->getType(), 'entry'.$i.' is an update operation.');
        self::assertSame(Transaction::INSERT, $audits[$i++]->getType(), 'entry'.$i.' is an insert operation.');
        self::assertSame(Transaction::INSERT, $audits[$i++]->getType(), 'entry'.$i.' is an insert operation.');
        self::assertSame(Transaction::INSERT, $audits[$i++]->getType(), 'entry'.$i.' is an insert operation.');

        /** @var Entry[] $audits */
        $audits = $reader
            ->createQuery(Author::class)
            ->addDateRangeFilter(Query::CREATED_AT, new DateTime('-5 days'), new DateTime('-4 days'))
            ->execute()
        ;
        self::assertCount(0, $audits, 'result count is ok.');

        /** @var Entry[] $audits */
        $audits = $reader
            ->createQuery(Author::class, ['page_size' => 2])
            ->addDateRangeFilter(Query::CREATED_AT, new DateTime('-1 day'))
            ->execute()
        ;
        self::assertCount(2, $audits, 'result count is ok.');

        $this->expectException(InvalidArgumentException::class);
        $reader
            ->createQuery(Post::class)
            ->addDateRangeFilter(Query::CREATED_AT, new DateTime('now'), new DateTime('-1 day'))
            ->execute()
        ;
    }

    /**
     * @depends testGetAudits
     */
    public function testGetAuditsCount(): void
    {
        $reader = $this->createReader();

        /** @var Entry[] $audits */
        $count = $reader->createQuery(Author::class)->count();
        self::assertSame(5, $count, 'count is ok.');
    }

    /**
     * @depends testGetAudits
     */
    public function testGetAuditsHonorsId(): void
    {
        $reader = $this->createReader();

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Author::class, ['object_id' => 1])->execute();
        self::assertCount(2, $audits, 'result count is ok.');

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Post::class, ['object_id' => 1])->execute();
        self::assertCount(3, $audits, 'result count is ok.');

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Comment::class, ['object_id' => 1])->execute();
        self::assertCount(1, $audits, 'result count is ok.');

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Post::class, ['object_id' => 0])->execute();
        self::assertSame([], $audits, 'no result when id is invalid.');
    }

    /**
     * @depends testGetAudits
     */
    public function testGetAuditsHonorsPageSize(): void
    {
        $reader = $this->createReader();

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Author::class, ['page_size' => 2])->execute();
        self::assertCount(2, $audits, 'result count is ok.');

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Author::class, ['page' => 2, 'page_size' => 2])->execute();
        self::assertCount(2, $audits, 'result count is ok.');

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Author::class, ['page' => 3, 'page_size' => 2])->execute();
        self::assertCount(1, $audits, 'result count is ok.');
    }

    public function testReaderHonorsPaging(): void
    {
        $reader = $this->createReader();

        $this->expectException(OptionsResolverInvalidArgumentException::class);
        $reader->createQuery(Author::class, ['page' => 1, 'page_size' => 0])->execute();
        $reader->createQuery(Author::class, ['page' => 1, 'page_size' => -1])->execute();
    }

    /**
     * @depends testGetAudits
     */
    public function testGetAuditsHonorsFilter(): void
    {
        $reader = $this->createReader();

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Author::class, ['type' => Transaction::UPDATE])->execute();
        self::assertCount(1, $audits, 'result count is ok.');

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Author::class, ['type' => Transaction::INSERT])->execute();
        self::assertCount(3, $audits, 'result count is ok.');

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Author::class, ['type' => Transaction::REMOVE])->execute();
        self::assertCount(1, $audits, 'result count is ok.');

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Author::class, ['type' => Transaction::ASSOCIATE])->execute();
        self::assertCount(0, $audits, 'result count is ok.');

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Author::class, ['type' => Transaction::DISSOCIATE])->execute();
        self::assertCount(0, $audits, 'result count is ok.');
    }

    /**
     * @depends testGetAudits
     */
    public function testGetAuditByTransactionHash(): void
    {
        $reader = $this->createReader();
        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity(Author::class);

        $author = new Author();
        $author
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;
        $storageService->getEntityManager()->persist($author);

        $post1 = new Post();
        $post1
            ->setAuthor($author)
            ->setTitle('First post')
            ->setBody('Here is the body')
            ->setCreatedAt(new DateTime())
        ;

        $post2 = new Post();
        $post2
            ->setAuthor($author)
            ->setTitle('Second post')
            ->setBody('Here is another body')
            ->setCreatedAt(new DateTime())
        ;

        $storageService->getEntityManager()->persist($post1);
        $storageService->getEntityManager()->persist($post2);
        $storageService->getEntityManager()->flush();

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Post::class)->execute();
        $hash = $audits[0]->getTransactionHash();

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Post::class, ['transaction_hash' => $hash])->execute();
        self::assertCount(2, $audits, 'result count is ok.');
    }

    /**
     * @depends testGetAuditByTransactionHash
     */
    public function testGetAllAuditsByTransactionHash(): void
    {
        $reader = $this->createReader();
        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity(Author::class);

        $author = new Author();
        $author
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;
        $storageService->getEntityManager()->persist($author);

        $post1 = new Post();
        $post1
            ->setAuthor($author)
            ->setTitle('First post')
            ->setBody('Here is the body')
            ->setCreatedAt(new DateTime())
        ;

        $post2 = new Post();
        $post2
            ->setAuthor($author)
            ->setTitle('Second post')
            ->setBody('Here is another body')
            ->setCreatedAt(new DateTime())
        ;

        $storageService->getEntityManager()->persist($post1);
        $storageService->getEntityManager()->persist($post2);
        $storageService->getEntityManager()->flush();

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Post::class)->execute();
        $hash = $audits[0]->getTransactionHash();

        $storageService->getEntityManager()->remove($post2);
        $storageService->getEntityManager()->flush();

        $reader = $this->createReader();
        $audits = $reader->getAuditsByTransactionHash($hash);

        self::assertCount(2, $audits, 'AuditReader::getAllAuditsByTransactionHash() is ok.');
        self::assertCount(1, $audits[Author::class], 'AuditReader::getAllAuditsByTransactionHash() is ok.');
        self::assertCount(2, $audits[Post::class], 'AuditReader::getAllAuditsByTransactionHash() is ok.');
    }
}
