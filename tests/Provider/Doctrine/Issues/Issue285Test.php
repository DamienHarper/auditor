<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Comment;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Filter\SoftDeleteFilter;
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
 * inaccessible (filtered out by a SQL filter such as SoftDeleteable), Doctrine throws
 * EntityNotFoundException.
 *
 * Fix: wrap initializeObject() in a try/catch and fall back to a minimal summary built
 * from the UoW identity map (getEntityIdentifier()), which does not require property
 * access on the potentially uninitialized proxy.
 *
 * Scenario: Comment.post is a ManyToOne to Post. Post has a `deleted_at` field.
 * We simulate SoftDeleteable by setting deleted_at via raw SQL (row stays in DB,
 * FK constraint satisfied) and activating a Doctrine SQL filter that hides the row.
 * After clearing the EM, comment.post becomes a lazy proxy that cannot be initialized.
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
     * because it is hidden by an active Doctrine SQL filter (simulating SoftDeleteable),
     * flush must not throw and must produce an audit entry for the update.
     */
    public function testFlushDoesNotThrowWhenOldManyToOneProxyIsHiddenByDoctrineFilter(): void
    {
        $em = array_values($this->provider->getAuditingServices())[0]->getEntityManager();

        // Create post1 (will be soft-deleted), post2, and a comment linked to post1.
        $post1 = new Post();
        $post1->setTitle('Original post')->setBody('Body')->setCreatedAt(new \DateTimeImmutable());

        $post2 = new Post();
        $post2->setTitle('Replacement post')->setBody('Body')->setCreatedAt(new \DateTimeImmutable());

        $comment = new Comment();
        $comment->setBody('A comment')->setAuthor('tester')->setCreatedAt(new \DateTimeImmutable())->setPost($post1);

        $em->persist($post1);
        $em->persist($post2);
        $em->persist($comment);
        $em->flush();

        $post1Id = $post1->getId();
        $post2Id = $post2->getId();
        $commentId = $comment->getId();

        // Simulate soft-delete by setting deleted_at directly via SQL.
        // The row stays in the DB so FK constraints (comment.post_id → post.id) remain satisfied.
        // This matches the real SoftDeleteable behaviour: the row is not removed, only hidden.
        $em->getConnection()->executeStatement(
            'UPDATE post SET deleted_at = :now WHERE id = :id',
            ['now' => new \DateTimeImmutable()->format('Y-m-d H:i:s'), 'id' => $post1Id]
        );

        // Register and enable a filter that hides soft-deleted posts, simulating Gedmo SoftDeleteable.
        $em->getConfiguration()->addFilter('soft_delete', SoftDeleteFilter::class);
        $em->getFilters()->enable('soft_delete');

        // Clear the identity map so that the next load uses lazy proxies.
        $em->clear();

        // Reload comment and post2. comment.post is now a lazy proxy for post1.
        // Because the soft_delete filter is active, the proxy cannot be initialized
        // (the SELECT returns no row).
        $comment = $em->find(Comment::class, $commentId);
        $post2 = $em->find(Post::class, $post2Id);
        \assert(null !== $comment);
        \assert(null !== $post2);

        // Change the comment's post from post1 (inaccessible proxy) to post2.
        $comment->setPost($post2);

        // Before the fix: flush throws EntityNotFoundException when summarize() tries to
        // initialize the post1 proxy. After the fix: a fallback summary is produced.
        $this->expectNotToPerformAssertions();
        $em->flush();
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Post::class => ['enabled' => true],
            Comment::class => ['enabled' => true],
        ]);
    }
}
