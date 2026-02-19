<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Model\Entry;
use DH\Auditor\Model\TransactionType;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Post;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Tag;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for issue #234: ManyToMany association/dissociation changes are not logged
 * when only the owning-side entity is audited (e.g. unidirectional ManyToMany, or bidirectional
 * where only the owner carries the #[Auditable] attribute).
 *
 * Before the fix, TransactionHydrator required BOTH the owner and the target entity to be
 * audited before recording an association/dissociation. This silently dropped changes for the
 * common case where only the owning side is auditable.
 *
 * The audit entry is always written to the owner's audit table, so only the owner needs to
 * be audited.
 *
 * @see https://github.com/DamienHarper/auditor/issues/234
 *
 * @internal
 */
#[Small]
final class Issue234Test extends TestCase
{
    use DefaultSchemaSetupTrait;

    /**
     * Adding an item to a ManyToMany collection must produce an ASSOCIATE audit entry
     * even when only the owning-side entity (Post) is audited and the target (Tag) is not.
     */
    public function testManyToManyAssociationIsAuditedWhenOnlyOwnerIsAuditable(): void
    {
        // Only the owning side is audited — Tag is intentionally NOT registered.
        $this->provider->getConfiguration()->setEntities([
            Post::class => ['enabled' => true],
        ]);

        $em = $this->provider->getAuditingServiceForEntity(Post::class)->getEntityManager();
        $reader = new Reader($this->provider);

        $tag = new Tag();
        $tag->setTitle('php');

        $em->persist($tag);

        $post = new Post();
        $post->setTitle('Issue 234')->setBody('Body')->setCreatedAt(new \DateTimeImmutable());
        $em->persist($post);
        $em->flush();

        // Associate the tag — only Post is audited, Tag is not.
        $post->addTag($tag);
        $em->flush();

        $postEntries = $reader->createQuery(Post::class)->execute();
        $types = array_map(static fn (Entry $e): string => $e->type, $postEntries);

        $this->assertContains(
            TransactionType::ASSOCIATE,
            $types,
            'ASSOCIATE entry must be created even when the target entity (Tag) is not audited.'
        );
    }

    /**
     * Removing an item from a ManyToMany collection must produce a DISSOCIATE audit entry
     * even when only the owning-side entity (Post) is audited and the target (Tag) is not.
     */
    public function testManyToManyDissociationIsAuditedWhenOnlyOwnerIsAuditable(): void
    {
        // Only the owning side is audited — Tag is intentionally NOT registered.
        $this->provider->getConfiguration()->setEntities([
            Post::class => ['enabled' => true],
        ]);

        $em = $this->provider->getAuditingServiceForEntity(Post::class)->getEntityManager();
        $reader = new Reader($this->provider);

        $tag = new Tag();
        $tag->setTitle('php');

        $em->persist($tag);

        $post = new Post();
        $post->setTitle('Issue 234')->setBody('Body')->setCreatedAt(new \DateTimeImmutable());
        $post->getTags()->add($tag);
        $em->persist($post);
        $em->flush();

        // Dissociate the tag — only Post is audited, Tag is not.
        $post->removeTag($tag);
        $em->flush();

        $postEntries = $reader->createQuery(Post::class)->execute();
        $types = array_map(static fn (Entry $e): string => $e->type, $postEntries);

        $this->assertContains(
            TransactionType::DISSOCIATE,
            $types,
            'DISSOCIATE entry must be created even when the target entity (Tag) is not audited.'
        );
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Post::class => ['enabled' => true],
            Tag::class => ['enabled' => true],
        ]);
    }
}
