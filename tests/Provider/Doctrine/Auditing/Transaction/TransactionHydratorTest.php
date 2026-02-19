<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Auditing\Transaction;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Model\Entry;
use DH\Auditor\Model\TransactionType;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Integration tests for TransactionHydrator via the full flush pipeline.
 * The hydrator runs inside the onFlush event; we verify its behaviour through
 * the audit entries it ultimately produces.
 */
#[Small]
final class TransactionHydratorTest extends TestCase
{
    use DefaultSchemaSetupTrait;

    /**
     * An audited entity flushed alone produces exactly one INSERT audit entry.
     */
    public function testAuditedInsertionIsHydrated(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Author::class => ['enabled' => true],
        ]);

        $em = $this->provider->getAuditingServiceForEntity(Author::class)->getEntityManager();
        $reader = new Reader($this->provider);

        $author = new Author();
        $author->setFullname('Jane Doe')->setEmail('jane@example.com');
        $em->persist($author);
        $em->flush();

        $entries = $reader->createQuery(Author::class)->execute();
        $this->assertCount(1, $entries, 'One INSERT entry expected for a single persisted Author.');
        $this->assertSame(TransactionType::INSERT, $entries[0]->type);
    }

    /**
     * A non-audited entity flushed alongside an audited entity must not
     * produce any audit entry for itself.
     */
    public function testNonAuditedEntityIsSkipped(): void
    {
        // Author is audited, Tag is NOT registered
        $this->provider->getConfiguration()->setEntities([
            Author::class => ['enabled' => true],
        ]);

        $auditingService = $this->provider->getAuditingServiceForEntity(Author::class);
        $em = $auditingService->getEntityManager();
        $reader = new Reader($this->provider);

        $author = new Author();
        $author->setFullname('Luke Skywalker')->setEmail('luke@rebellion.org');
        $em->persist($author);

        // Tag is not audited — persisted in the same flush
        $tag = new Tag();
        $tag->setTitle('star-wars');

        $em->persist($tag);

        $em->flush();

        // Only Author should have an audit entry
        $authorEntries = $reader->createQuery(Author::class)->execute();
        $this->assertCount(1, $authorEntries, 'Non-audited Tag must not produce an audit entry.');
        $this->assertSame(TransactionType::INSERT, $authorEntries[0]->type);

        // Tag is not auditable — Reader should throw
        $this->expectException(InvalidArgumentException::class);
        $reader->createQuery(Tag::class)->execute();
    }

    /**
     * Multiple audited insertions in one flush must all be hydrated.
     */
    public function testMultipleAuditedInsertionsInOneFlush(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Author::class => ['enabled' => true],
        ]);

        $em = $this->provider->getAuditingServiceForEntity(Author::class)->getEntityManager();
        $reader = new Reader($this->provider);

        for ($i = 0; $i < 3; ++$i) {
            $author = new Author();
            $author->setFullname('Author '.$i)->setEmail(\sprintf('author%d@example.com', $i));
            $em->persist($author);
        }

        $em->flush();

        $entries = $reader->createQuery(Author::class)->execute();
        $this->assertCount(3, $entries, 'All 3 audited insertions must produce audit entries.');

        foreach ($entries as $entry) {
            $this->assertSame(TransactionType::INSERT, $entry->type);
        }
    }

    /**
     * An UPDATE operation on an audited entity must be hydrated as UPDATE.
     */
    public function testAuditedUpdateIsHydrated(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Author::class => ['enabled' => true],
        ]);

        $em = $this->provider->getAuditingServiceForEntity(Author::class)->getEntityManager();
        $reader = new Reader($this->provider);

        $author = new Author();
        $author->setFullname('Original Name')->setEmail('orig@example.com');
        $em->persist($author);
        $em->flush();

        $author->setFullname('Updated Name');
        $em->flush();

        $entries = $reader->createQuery(Author::class)->execute();
        $this->assertCount(2, $entries, 'One INSERT + one UPDATE entry expected.');

        $types = array_map(static fn (Entry $e): string => $e->type, $entries);
        $this->assertContains(TransactionType::INSERT, $types);
        $this->assertContains(TransactionType::UPDATE, $types);
    }

    /**
     * A DELETE operation on an audited entity must be hydrated as REMOVE.
     */
    public function testAuditedDeletionIsHydrated(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Author::class => ['enabled' => true],
        ]);

        $em = $this->provider->getAuditingServiceForEntity(Author::class)->getEntityManager();
        $reader = new Reader($this->provider);

        $author = new Author();
        $author->setFullname('To Be Deleted')->setEmail('delete@example.com');
        $em->persist($author);
        $em->flush();

        $em->remove($author);
        $em->flush();

        $entries = $reader->createQuery(Author::class)->execute();
        $this->assertCount(2, $entries, 'One INSERT + one REMOVE entry expected.');

        $types = array_map(static fn (Entry $e): string => $e->type, $entries);
        $this->assertContains(TransactionType::INSERT, $types);
        $this->assertContains(TransactionType::REMOVE, $types);
    }

    /**
     * A ManyToMany association between two audited entities must produce
     * ASSOCIATE entries via hydrateWithScheduledCollectionUpdates().
     */
    public function testAuditedManyToManyAssociationIsHydrated(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Post::class => ['enabled' => true],
            Tag::class => ['enabled' => true],
        ]);

        $em = $this->provider->getAuditingServiceForEntity(Post::class)->getEntityManager();
        $reader = new Reader($this->provider);

        $tag = new Tag();
        $tag->setTitle('php');

        $em->persist($tag);

        $post = new Post();
        $post->setTitle('Post')->setBody('Body')->setCreatedAt(new \DateTimeImmutable());
        $em->persist($post);
        $em->flush();

        $post->addTag($tag);
        $em->flush();

        $postEntries = $reader->createQuery(Post::class)->execute();
        $types = array_map(static fn (Entry $e): string => $e->type, $postEntries);
        $this->assertContains(TransactionType::ASSOCIATE, $types, 'ManyToMany association must produce an ASSOCIATE entry.');
    }

    /**
     * A ManyToMany dissociation must produce DISSOCIATE entries via
     * hydrateWithScheduledCollectionUpdates().
     */
    public function testAuditedManyToManyDissociationIsHydrated(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Post::class => ['enabled' => true],
            Tag::class => ['enabled' => true],
        ]);

        $em = $this->provider->getAuditingServiceForEntity(Post::class)->getEntityManager();
        $reader = new Reader($this->provider);

        $tag = new Tag();
        $tag->setTitle('php');

        $em->persist($tag);

        $post = new Post();
        $post->setTitle('Post')->setBody('Body')->setCreatedAt(new \DateTimeImmutable());
        $post->addTag($tag);
        $em->persist($post);
        $em->flush();

        $post->removeTag($tag);
        $em->flush();

        $postEntries = $reader->createQuery(Post::class)->execute();
        $types = array_map(static fn (Entry $e): string => $e->type, $postEntries);
        $this->assertContains(TransactionType::DISSOCIATE, $types, 'ManyToMany dissociation must produce a DISSOCIATE entry.');
    }

    /**
     * A flush with no scheduled operations must produce no audit entries.
     */
    public function testEmptyFlushProducesNoAuditEntries(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Author::class => ['enabled' => true],
        ]);

        $em = $this->provider->getAuditingServiceForEntity(Author::class)->getEntityManager();
        $reader = new Reader($this->provider);

        // flush with nothing scheduled
        $em->flush();

        $entries = $reader->createQuery(Author::class)->execute();
        $this->assertCount(0, $entries, 'An empty flush must produce no audit entries.');
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Author::class => ['enabled' => true],
            Post::class => ['enabled' => true],
            Tag::class => ['enabled' => true],
        ]);
    }
}
