<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Model\TransactionType;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use Doctrine\ORM\Events;
use Gedmo\SoftDeleteable\SoftDeleteableListener;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for issue #296: soft-deleteable entities must produce exactly
 * one REMOVE audit entry, regardless of the order in which Gedmo's
 * SoftDeleteableListener and the auditor's DoctrineSubscriber are registered.
 *
 * Root cause: when the auditor's onFlush fires *before* Gedmo's onFlush, the
 * entity is captured from getScheduledEntityDeletions() in
 * TransactionHydrator::hydrateWithScheduledDeletions() AND again from Gedmo's
 * postSoftDelete event handler, resulting in two REMOVE audit entries.
 *
 * Fix: deduplicate in Transaction::remove() so that adding the same entity more
 * than once is a no-op.
 *
 * @see https://github.com/DamienHarper/auditor/issues/296
 *
 * @internal
 */
#[Small]
final class Issue296Test extends TestCase
{
    use DefaultSchemaSetupTrait;

    /**
     * Normal listener order (Gedmo registered before the auditor, as in the test fixtures).
     * Entity is captured once via postSoftDelete → exactly 2 audit entries: INSERT + REMOVE.
     */
    public function testSoftDeleteCreatesExactlyOneRemoveAuditWhenGedmoFiresFirst(): void
    {
        $em = $this->provider->getStorageServiceForEntity(Post::class)->getEntityManager();
        $reader = new Reader($this->provider);

        $post = new Post();
        $post->setTitle('To be soft-deleted')->setBody('body')->setCreatedAt(new \DateTimeImmutable());
        $em->persist($post);
        $em->flush();

        // Soft-delete: Gedmo converts the removal to an UPDATE on deleted_at
        $em->remove($post);
        $em->flush();

        $audits = $reader->createQuery(Post::class)->execute();

        $this->assertCount(2, $audits, 'Exactly 2 audit entries: INSERT + REMOVE.');
        $removeEntry = array_shift($audits);
        $this->assertSame(TransactionType::REMOVE, $removeEntry->type, 'Soft-delete is logged as a REMOVE entry.');
    }

    /**
     * Reversed listener order: auditor's DoctrineSubscriber fires before Gedmo's
     * SoftDeleteableListener.
     *
     * Without the fix: the entity is added to Transaction::removed twice
     * (once from hydrateWithScheduledDeletions, once from postSoftDelete),
     * resulting in 3 audit entries (INSERT + REMOVE + REMOVE).
     * With the fix: exactly 2 audit entries (INSERT + REMOVE).
     */
    public function testSoftDeleteCreatesExactlyOneRemoveAuditWhenAuditorFiresFirst(): void
    {
        $em = $this->provider->getStorageServiceForEntity(Post::class)->getEntityManager();
        $evm = $em->getEventManager();

        // Swap listener order so that the auditor's DoctrineSubscriber fires *before*
        // Gedmo's SoftDeleteableListener on the onFlush event.
        // Currently: [SoftDeleteableListener, DoctrineSubscriber]
        // After swap: [DoctrineSubscriber, SoftDeleteableListener]
        $softDeleteListener = null;
        foreach ($evm->getListeners(Events::onFlush) as $listener) {
            if ($listener instanceof SoftDeleteableListener) {
                $softDeleteListener = $listener;
                break;
            }
        }

        if (null !== $softDeleteListener) {
            $evm->removeEventListener([Events::onFlush], $softDeleteListener);
            // Re-register at the end so it fires AFTER DoctrineSubscriber
            $evm->addEventListener([Events::onFlush], $softDeleteListener);
        }

        $reader = new Reader($this->provider);

        $post = new Post();
        $post->setTitle('To be soft-deleted (reversed order)')->setBody('body')->setCreatedAt(new \DateTimeImmutable());
        $em->persist($post);
        $em->flush();

        $em->remove($post);
        $em->flush();

        $audits = $reader->createQuery(Post::class)->execute();

        // Must be exactly 2 entries (INSERT + REMOVE), not 3 (INSERT + REMOVE + REMOVE)
        $this->assertCount(2, $audits, 'Exactly 2 audit entries even when auditor fires before Gedmo.');
        $removeEntry = array_shift($audits);
        $this->assertSame(TransactionType::REMOVE, $removeEntry->type, 'Soft-delete is logged as a REMOVE entry.');
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Post::class => ['enabled' => true],
        ]);
    }
}
