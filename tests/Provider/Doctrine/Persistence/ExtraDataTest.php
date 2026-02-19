<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Model\Entry;
use DH\Auditor\Model\TransactionType;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\JsonFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog\Author;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * End-to-end tests for the extra_data feature:
 *   Entity flush → LifecycleEvent dispatched → listener enriches extra_data
 *   → audit entry stored → Reader queries extra_data via JsonFilter
 */
#[Small]
final class ExtraDataTest extends TestCase
{
    use DefaultSchemaSetupTrait;

    /**
     * Without any listener, extra_data must be NULL in the stored audit entry.
     */
    public function testExtraDataIsNullByDefault(): void
    {
        $em = $this->provider->getAuditingServiceForEntity(Author::class)->getEntityManager();
        $reader = new Reader($this->provider);

        $author = new Author();
        $author->setFullname('No Extra')->setEmail('noextra@example.com');
        $em->persist($author);
        $em->flush();

        $entries = $reader->createQuery(Author::class)->execute();
        $this->assertCount(1, $entries);
        $this->assertNull($entries[0]->extraData, 'extra_data must be NULL when no listener sets it.');
    }

    /**
     * A LifecycleEvent listener can enrich extra_data before the entry is persisted.
     * The stored entry must reflect the data set by the listener.
     */
    public function testListenerCanEnrichExtraData(): void
    {
        $dispatcher = $this->provider->getAuditor()->getEventDispatcher();

        // Register a listener at priority 10 (before AuditEventSubscriber at -1_000_000)
        $dispatcher->addListener(
            LifecycleEvent::class,
            static function (LifecycleEvent $event): void {
                if (Author::class !== ($event->getPayload()['entity'] ?? null)) {
                    return;
                }

                $payload = $event->getPayload();
                $payload['extra_data'] = json_encode(['department' => 'engineering', 'level' => 3], JSON_THROW_ON_ERROR);
                $event->setPayload($payload);
            },
            10
        );

        $em = $this->provider->getAuditingServiceForEntity(Author::class)->getEntityManager();
        $reader = new Reader($this->provider);

        $author = new Author();
        $author->setFullname('Jane Extra')->setEmail('jane.extra@example.com');
        $em->persist($author);
        $em->flush();

        $entries = $reader->createQuery(Author::class)->execute();
        $this->assertCount(1, $entries);

        $extra = $entries[0]->extraData;
        $this->assertNotNull($extra, 'extra_data must not be NULL after listener enrichment.');
        $this->assertSame('engineering', $extra['department']);
        $this->assertSame(3, $extra['level']);
    }

    /**
     * The listener has access to the audited entity via $event->entity,
     * so it can read its state when enriching extra_data.
     */
    public function testListenerReceivesAuditedEntityReference(): void
    {
        $dispatcher = $this->provider->getAuditor()->getEventDispatcher();

        $dispatcher->addListener(
            LifecycleEvent::class,
            static function (LifecycleEvent $event): void {
                if (null === $event->entity || !$event->entity instanceof Author) {
                    return;
                }

                $payload = $event->getPayload();
                // Encode a property from the entity itself
                $payload['extra_data'] = json_encode(['fullname' => $event->entity->getFullname()], JSON_THROW_ON_ERROR);
                $event->setPayload($payload);
            },
            10
        );

        $em = $this->provider->getAuditingServiceForEntity(Author::class)->getEntityManager();
        $reader = new Reader($this->provider);

        $author = new Author();
        $author->setFullname('Entity Access Test')->setEmail('entity@example.com');
        $em->persist($author);
        $em->flush();

        $entries = $reader->createQuery(Author::class)->execute();
        $this->assertCount(1, $entries);

        $this->assertSame('Entity Access Test', $entries[0]->extraData['fullname'], 'Listener must receive the audited entity and be able to read its state.');
    }

    /**
     * extra_data is stored per audit entry, so different operations on the
     * same entity can carry different extra_data.
     */
    public function testExtraDataIsStoredPerEntry(): void
    {
        $dispatcher = $this->provider->getAuditor()->getEventDispatcher();
        $callCount = 0;

        $dispatcher->addListener(
            LifecycleEvent::class,
            static function (LifecycleEvent $event) use (&$callCount): void {
                if (Author::class !== ($event->getPayload()['entity'] ?? null)) {
                    return;
                }

                ++$callCount;
                $payload = $event->getPayload();
                $payload['extra_data'] = json_encode(['call' => $callCount], JSON_THROW_ON_ERROR);
                $event->setPayload($payload);
            },
            10
        );

        $em = $this->provider->getAuditingServiceForEntity(Author::class)->getEntityManager();
        $reader = new Reader($this->provider);

        $author = new Author();
        $author->setFullname('Multi-Entry')->setEmail('multi@example.com');
        $em->persist($author);
        $em->flush();

        $author->setFullname('Multi-Entry Updated');
        $em->flush();

        $entries = $reader->createQuery(Author::class)->execute();
        $this->assertCount(2, $entries, 'INSERT + UPDATE must each produce an audit entry.');

        // Each entry carries its own extra_data value
        $calls = array_map(static fn (Entry $e) => $e->extraData['call'], $entries);
        sort($calls);
        $this->assertSame([1, 2], $calls, 'Each audit entry must have its own extra_data snapshot.');
    }

    /**
     * JsonFilter must be able to find entries by a top-level extra_data key.
     */
    public function testJsonFilterFindsEntriesByExtraDataKey(): void
    {
        $dispatcher = $this->provider->getAuditor()->getEventDispatcher();

        $dispatcher->addListener(
            LifecycleEvent::class,
            static function (LifecycleEvent $event): void {
                if (Author::class !== ($event->getPayload()['entity'] ?? null)) {
                    return;
                }

                $payload = $event->getPayload();
                $payload['extra_data'] = json_encode(['team' => 'backend'], JSON_THROW_ON_ERROR);
                $event->setPayload($payload);
            },
            10
        );

        $em = $this->provider->getAuditingServiceForEntity(Author::class)->getEntityManager();

        $author1 = new Author();
        $author1->setFullname('Backend Dev')->setEmail('backend@example.com');
        $em->persist($author1);

        $author2 = new Author();
        $author2->setFullname('Backend Dev 2')->setEmail('backend2@example.com');
        $em->persist($author2);

        $em->flush();

        $reader = new Reader($this->provider);
        $entries = $reader
            ->createQuery(Author::class)
            ->addFilter(new JsonFilter('extra_data', 'team', 'backend'))
            ->execute()
        ;

        $this->assertCount(2, $entries, 'JsonFilter must return all entries matching the extra_data key value.');

        foreach ($entries as $entry) {
            $this->assertSame('backend', $entry->extraData['team']);
        }
    }

    /**
     * JsonFilter with a non-matching value must return no results.
     */
    public function testJsonFilterReturnsEmptyForNonMatchingValue(): void
    {
        $dispatcher = $this->provider->getAuditor()->getEventDispatcher();

        $dispatcher->addListener(
            LifecycleEvent::class,
            static function (LifecycleEvent $event): void {
                if (Author::class !== ($event->getPayload()['entity'] ?? null)) {
                    return;
                }

                $payload = $event->getPayload();
                $payload['extra_data'] = json_encode(['team' => 'frontend'], JSON_THROW_ON_ERROR);
                $event->setPayload($payload);
            },
            10
        );

        $em = $this->provider->getAuditingServiceForEntity(Author::class)->getEntityManager();

        $author = new Author();
        $author->setFullname('Frontend Dev')->setEmail('frontend@example.com');
        $em->persist($author);
        $em->flush();

        $reader = new Reader($this->provider);
        $entries = $reader
            ->createQuery(Author::class)
            ->addFilter(new JsonFilter('extra_data', 'team', 'backend'))
            ->execute()
        ;

        $this->assertCount(0, $entries, 'JsonFilter must not match an entry whose extra_data value differs.');
    }

    /**
     * Entries created without a listener have extraData === null.
     * This validates the default null extra_data behaviour end-to-end.
     */
    public function testEntriesWithoutListenerHaveNullExtraData(): void
    {
        $em = $this->provider->getAuditingServiceForEntity(Author::class)->getEntityManager();

        // Persist two authors without any listener (extra_data stays NULL)
        for ($i = 0; $i < 2; ++$i) {
            $a = new Author();
            $a->setFullname('No-extra '.$i)->setEmail(\sprintf('noextra%d@example.com', $i));
            $em->persist($a);
        }

        $em->flush();

        $reader = new Reader($this->provider);
        $entries = $reader->createQuery(Author::class)->execute();

        $this->assertCount(2, $entries, 'Both authors must produce an audit entry.');

        foreach ($entries as $entry) {
            $this->assertNull($entry->extraData, 'extra_data must be NULL when no listener enriches it.');
        }
    }

    /**
     * A listener that only enriches INSERT events must leave UPDATE entries with NULL extra_data.
     */
    public function testListenerCanBeSelectiveByTransactionType(): void
    {
        $dispatcher = $this->provider->getAuditor()->getEventDispatcher();

        $dispatcher->addListener(
            LifecycleEvent::class,
            static function (LifecycleEvent $event): void {
                $payload = $event->getPayload();

                if (Author::class !== ($payload['entity'] ?? null)) {
                    return;
                }

                // $payload['type'] holds the string value (e.g. 'insert', 'update')
                if (TransactionType::INSERT !== $payload['type']) {
                    return;
                }

                $payload['extra_data'] = json_encode(['action' => 'insert_only'], JSON_THROW_ON_ERROR);
                $event->setPayload($payload);
            },
            10
        );

        $em = $this->provider->getAuditingServiceForEntity(Author::class)->getEntityManager();
        $reader = new Reader($this->provider);

        $author = new Author();
        $author->setFullname('Selective')->setEmail('selective@example.com');
        $em->persist($author);
        $em->flush();

        $author->setFullname('Selective Updated');
        $em->flush();

        $entries = $reader->createQuery(Author::class)->execute();
        $this->assertCount(2, $entries);

        $byType = [];
        foreach ($entries as $entry) {
            $byType[$entry->type] = $entry;
        }

        $this->assertNotNull($byType[TransactionType::INSERT]->extraData, 'INSERT entry must have extra_data set by the listener.');
        $this->assertNull($byType[TransactionType::UPDATE]->extraData, 'UPDATE entry must have NULL extra_data when listener ignores it.');
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            Author::class => ['enabled' => true],
        ]);
    }
}
