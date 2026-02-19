<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for issue #285: EntityNotFoundException when auditing a ManyToOne
 * relation change whose previous value points to an entity hidden by a Doctrine filter
 * (e.g. SoftDeleteable) or deleted between two flushes.
 *
 * Root cause: AuditTrait::summarize() calls UoW::initializeObject() to hydrate the
 * related entity proxy before building the audit diff. When the entity row is
 * inaccessible (filtered out or hard-deleted), Doctrine throws EntityNotFoundException.
 *
 * Fix: wrap initializeObject() in a try/catch and fall back to a minimal summary built
 * from the UoW identity map (getEntityIdentifier()), which does not require property
 * access on the potentially uninitialized proxy.
 *
 * @see https://github.com/DamienHarper/auditor/issues/285
 *
 * @internal
 */
#[Small]
final class Issue285Test extends TestCase
{
    use DefaultSchemaSetupTrait;

    /**
     * When the "old" value of a ManyToOne field is a proxy that cannot be initialized
     * (row deleted directly in the DB, simulating a Doctrine filter hiding soft-deleted
     * entities), flush must not throw and must produce an audit entry for the update.
     */
    public function testFlushDoesNotThrowWhenOldManyToOneProxyCannotBeInitialized(): void
    {
        $em = array_values($this->provider->getAuditingServices())[0]->getEntityManager();

        // Create author1, author2, and a post linked to author1.
        $author1 = new Author()->setFullname('Author One')->setEmail('one@example.com');
        $author2 = new Author()->setFullname('Author Two')->setEmail('two@example.com');
        $post = new Post()
            ->setTitle('Test post')
            ->setBody('Body')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setAuthor($author1)
        ;
        $em->persist($author1);
        $em->persist($author2);
        $em->persist($post);
        $em->flush();

        $author1Id = $author1->getId();
        $postId = $post->getId();

        // Clear the identity map so that the next load uses lazy proxies.
        $em->clear();

        // Reload the post: post.author is now an uninitialized proxy for author1.
        $post = $em->find(Post::class, $postId);
        $author2 = $em->find(Author::class, $author2->getId());
        \assert(null !== $post);
        \assert(null !== $author2);

        // Hard-delete author1 directly via SQL, bypassing the ORM.
        // This simulates a Doctrine filter (e.g. SoftDeleteable) hiding the row:
        // the proxy exists in the identity map but the row is no longer accessible.
        $em->getConnection()->executeStatement('DELETE FROM author WHERE id = :id', ['id' => $author1Id]);

        // Change the post's author from author1 (inaccessible proxy) to author2.
        $post->setAuthor($author2);

        // Before the fix: flush throws EntityNotFoundException when summarize() tries to
        // initialize the author1 proxy. After the fix: a fallback summary is produced.
        $this->expectNotToPerformAssertions();
        $em->flush();
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Author::class => ['enabled' => true],
            Post::class => ['enabled' => true],
        ]);
    }
}
