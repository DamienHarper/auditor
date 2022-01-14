<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Reader;

use DateTimeImmutable;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Model\Entry;
use DH\Auditor\Model\Transaction;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\DateRangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;
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
 *
 * @small
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
            self::assertMatchesRegularExpression('#\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}#', $audits[0]->getCreatedAt());
        }

        $expected = [
            'Inserted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Author#2: [email: chuck.norris@gmail.com, fullname: Chuck Norris]',
            'Inserted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Author#1: [email: john.doe@gmail.com, fullname: John]',
            'Inserted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Author#3: [email: luke.skywalker@gmail.com, fullname: Luke Skywalker]',
            'Updated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Author#1: [fullname: John => John Doe]',
            'Deleted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Author#3',
        ];

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Author::class)->resetOrderBy()->execute();
        for ($i = 0; $i < 5; ++$i) {
            self::assertSame($expected[$i], self::explain($audits[$i], Author::class));
        }

        $expected = [
            'Inserted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#3: [author: DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Author#2, body: Here is another body, created_at: 2020-01-17 22:17:34, title: Third post]',
            'Inserted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#2: [author: DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Author#1, body: Here is another body, created_at: 2020-01-17 22:17:34, title: Second post]',
            'Inserted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#1: [author: DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Author#1, body: Here is the body, created_at: 2020-01-17 22:17:34, title: First post]',
            'Inserted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#4: [author: DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Author#3, body: Here is the body, created_at: 2020-01-17 22:17:34, title: Fourth post]',
            'Associated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#4 (Fourth post) to DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#2 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#2)',
            'Associated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#4 (Fourth post) to DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#4 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#4)',
            'Associated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#4 (Fourth post) to DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#5 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#5)',
            'Associated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#3 (Third post) to DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#1 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#1)',
            'Associated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#3 (Third post) to DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#3 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#3)',
            'Associated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#3 (Third post) to DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#5 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#5)',
            'Associated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#1 (First post) to DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#1 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#1)',
            'Associated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#1 (First post) to DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#2 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#2)',
            'Dissociated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#4 (Fourth post) from DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#4 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#4)',
            'Dissociated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#4 (Fourth post) from DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#5 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#5)',
            'Updated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#4: [author: DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Author#3 => null]',
        ];

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Post::class)->resetOrderBy()->execute();
        for ($i = 0; $i < 15; ++$i) {
            self::assertSame($expected[$i], self::explain($audits[$i], Post::class));
        }

        $expected = [
            'Inserted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Comment#3: [author: Mario, body: Second comment about post #3, created_at: 2020-01-17 22:17:34, post: Third post]',
            'Inserted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Comment#2: [author: Yoshi, body: First comment about post #3, created_at: 2020-01-17 22:17:34, post: Third post]',
            'Inserted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Comment#1: [author: Dark Vador, body: First comment about post #1, created_at: 2020-01-17 22:17:34, post: First post]',
        ];

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Comment::class)->resetOrderBy()->execute();
        for ($i = 0; $i < 3; ++$i) {
            self::assertSame($expected[$i], self::explain($audits[$i], Comment::class));
        }

        $expected = [
            'Inserted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#5: [title: gabber]',
            'Inserted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#4: [title: jungle]',
            'Inserted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#3: [title: hardcore]',
            'Inserted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#2: [title: house]',
            'Inserted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#1: [title: techno]',
            'Associated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#5 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#5) to DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#3 (Third post)',
            'Associated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#5 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#5) to DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#4 (Fourth post)',
            'Associated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#4 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#4) to DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#4 (Fourth post)',
            'Associated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#3 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#3) to DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#3 (Third post)',
            'Associated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#2 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#2) to DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#1 (First post)',
            'Associated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#2 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#2) to DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#4 (Fourth post)',
            'Associated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#1 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#1) to DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#1 (First post)',
            'Associated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#1 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#1) to DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#3 (Third post)',
            'Dissociated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#5 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#5) from DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#4 (Fourth post)',
            'Dissociated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#4 (DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Tag#4) from DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Post#4 (Fourth post)',
        ];

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Tag::class)->resetOrderBy()->execute();
        for ($i = 0; $i < 15; ++$i) {
            self::assertSame($expected[$i], self::explain($audits[$i], Tag::class));
        }

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

    public function testMultipleFilters(): void
    {
        $reader = $this->createReader();

        $query = $reader->createQuery(Author::class);
        $audits = $query->execute();
        self::assertCount(5, $audits);

        $query = $reader->createQuery(Author::class);
        $query->addFilter(new SimpleFilter('object_id', 1));
        $query->addFilter(new SimpleFilter('object_id', 2));
        $audits = $query->execute();
        self::assertCount(3, $audits);
    }

    /**
     * @depends testGetAudits
     */
    public function testGetAuditsByDate(): void
    {
        $reader = $this->createReader();

        $expected = [
            'Inserted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Author#2: [email: chuck.norris@gmail.com, fullname: Chuck Norris]',
            'Inserted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Author#1: [email: john.doe@gmail.com, fullname: John]',
            'Inserted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Author#3: [email: luke.skywalker@gmail.com, fullname: Luke Skywalker]',
            'Updated DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Author#1: [fullname: John => John Doe]',
            'Deleted DH\\Auditor\\Tests\\Provider\\Doctrine\\Fixtures\\Entity\\Standard\\Blog\\Author#3',
        ];

        /** @var Entry[] $audits */
        $audits = $reader
            ->createQuery(Author::class)
            ->addFilter(new DateRangeFilter(Query::CREATED_AT, new DateTimeImmutable('-1 day')))
            ->resetOrderBy()
            ->execute()
        ;
        for ($i = 0; $i < 5; ++$i) {
            self::assertSame($expected[$i], self::explain($audits[$i], Author::class));
        }

        /** @var Entry[] $audits */
        $audits = $reader
            ->createQuery(Author::class)
            ->addFilter(new DateRangeFilter(Query::CREATED_AT, new DateTimeImmutable('-5 days'), new DateTimeImmutable('-4 days')))
            ->execute()
        ;
        self::assertCount(0, $audits, 'result count is ok.');

        /** @var Entry[] $audits */
        $audits = $reader
            ->createQuery(Author::class, ['page_size' => 2])
            ->addFilter(new DateRangeFilter(Query::CREATED_AT, new DateTimeImmutable('-1 day')))
            ->execute()
        ;
        self::assertCount(2, $audits, 'result count is ok.');

        $this->expectException(InvalidArgumentException::class);
        $reader
            ->createQuery(Post::class)
            ->addFilter(new DateRangeFilter(Query::CREATED_AT, new DateTimeImmutable('now'), new DateTimeImmutable('-1 day')))
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
            ->setCreatedAt(new DateTimeImmutable())
        ;

        $post2 = new Post();
        $post2
            ->setAuthor($author)
            ->setTitle('Second post')
            ->setBody('Here is another body')
            ->setCreatedAt(new DateTimeImmutable())
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
            ->setCreatedAt(new DateTimeImmutable())
        ;

        $post2 = new Post();
        $post2
            ->setAuthor($author)
            ->setTitle('Second post')
            ->setBody('Here is another body')
            ->setCreatedAt(new DateTimeImmutable())
        ;

        $storageService->getEntityManager()->persist($post1);
        $storageService->getEntityManager()->persist($post2);
        $storageService->getEntityManager()->flush();

        /** @var Entry[] $audits */
        $audits = $reader->createQuery(Post::class)->execute();
        $hash = $audits[0]->getTransactionHash();

        $author->removePost($post2);
        $storageService->getEntityManager()->remove($post2);
        $storageService->getEntityManager()->flush();

        $reader = $this->createReader();
        $audits = $reader->getAuditsByTransactionHash($hash);

        self::assertCount(2, $audits, 'Reader::getAllAuditsByTransactionHash() is ok.');
        self::assertCount(1, $audits[Author::class], 'Reader::getAllAuditsByTransactionHash() is ok.');
        self::assertCount(2, $audits[Post::class], 'Reader::getAllAuditsByTransactionHash() is ok.');
    }

    protected function explain(Entry $entry, string $class, bool $verbose = true): string
    {
        $diff = $entry->getDiffs() ?? [];

        switch ($entry->getType()) {
            case Transaction::REMOVE:
                return 'Deleted '.$class.'#'.$entry->getObjectId();

            case Transaction::UPDATE:
                $changeset = static function (array $diff) use ($verbose) {
                    $changes = [];
                    foreach ($diff as $key => $value) {
                        $old = $value['old'] ?? 'null';
                        $old = \is_array($old) ? $old['label'] : $old;

                        $new = $value['new'] ?? 'null';
                        $new = \is_array($new) ? $new['label'] : $new;

                        $changes[] = $verbose ? ($key.': '.$old.' => '.$new) : $key;
                    }

                    return implode(', ', $changes);
                };

                return 'Updated '.$class.'#'.$entry->getObjectId().': ['.$changeset($diff).']';

            case Transaction::INSERT:
                $changeset = static function (array $diff) use ($verbose) {
                    $changes = [];
                    foreach ($diff as $key => $value) {
                        $old = $value['old'] ?? 'null';
                        $old = \is_array($old) ? $old['label'] : $old;

                        $new = $value['new'] ?? 'null';
                        $new = \is_array($new) ? $new['label'] : $new;

                        $changes[] = $verbose ? ($key.': '.$new) : $key;
                    }

                    return implode(', ', $changes);
                };

                return 'Inserted '.$class.'#'.$entry->getObjectId().': ['.$changeset($diff).']';

            case Transaction::DISSOCIATE:
                $source = $diff['source']['class'].'#'.$diff['source']['id'].($verbose ? ' ('.$diff['source']['label'].')' : '');
                $target = $diff['target']['class'].'#'.$diff['target']['id'].($verbose ? ' ('.$diff['target']['label'].')' : '');

                return 'Dissociated '.$source.' from '.$target;

            case Transaction::ASSOCIATE:
                $source = $diff['source']['class'].'#'.$diff['source']['id'].($verbose ? ' ('.$diff['source']['label'].')' : '');
                $target = $diff['target']['class'].'#'.$diff['target']['id'].($verbose ? ' ('.$diff['target']['label'].')' : '');

                return 'Associated '.$source.' to '.$target;

            default:
                return 'Unknown';
        }
    }
}
