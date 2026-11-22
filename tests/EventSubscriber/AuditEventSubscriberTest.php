<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\EventSubscriber;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\EventSubscriber\AuditEventSubscriber;
use DH\Auditor\Exception\ProviderException;
use DH\Auditor\Tests\Fixtures\EventSubscriber\CustomAuditEventSubscriber;
use DH\Auditor\Tests\Fixtures\Provider\AuditNoStorageProvider;
use DH\Auditor\Tests\Fixtures\Provider\FailingStorageProvider;
use DH\Auditor\Tests\Fixtures\Provider\OtherFailingStorageProvider;
use DH\Auditor\Tests\Fixtures\Provider\RecordingStorageProvider;
use DH\Auditor\Tests\Traits\AuditorTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class AuditEventSubscriberTest extends TestCase
{
    use AuditorTrait;

    public function testOnAuditEvent(): void
    {
        $payload = [
            'entity' => AuditEventSubscriber::class,
            'table' => '',
            'schema_version' => 2,
            'type' => '',
            'object_id' => '',
            'discriminator' => '',
            'transaction_id' => '',
            'diffs' => '',
            'extra_data' => '',
            'blame_id' => '',
            'blame' => '',
            'created_at' => '',
        ];

        $auditor = $this->createAuditor();
        $dispatcher = $auditor->getEventDispatcher();
        $subscriber = new AuditEventSubscriber($auditor);
        $dispatcher->addListener(LifecycleEvent::class, $subscriber, -1_000_000);

        $event = $dispatcher->dispatch(new LifecycleEvent($payload));

        $this->assertInstanceOf(LifecycleEvent::class, $event);
    }

    public function testCustomAuditEventSubscriber(): void
    {
        $payload = [
            'entity' => AuditEventSubscriber::class,
            'table' => '',
            'schema_version' => 2,
            'type' => '',
            'object_id' => '',
            'discriminator' => '',
            'transaction_id' => '',
            'diffs' => '',
            'extra_data' => '',
            'blame_id' => '',
            'blame' => '',
            'created_at' => '',
        ];

        $auditor = $this->createAuditor();
        $dispatcher = $auditor->getEventDispatcher();

        $subscriber = new AuditEventSubscriber($auditor);
        $dispatcher->addListener(LifecycleEvent::class, $subscriber, -1_000_000);

        $customSubscriber = new CustomAuditEventSubscriber();
        $dispatcher->addListener(LifecycleEvent::class, $customSubscriber);

        $event = $dispatcher->dispatch(new LifecycleEvent($payload));

        // CustomAuditEventSubscriber modifie le payload pour mettre sa propre classe
        $this->assertSame(CustomAuditEventSubscriber::class, $event->getPayload()['entity']);
    }

    public function testThrowsWhenTheOnlyStorageProviderFails(): void
    {
        $auditor = $this->createAuditor();
        $auditor->registerProvider(new FailingStorageProvider());

        try {
            $auditor->getEventDispatcher()->dispatch(new LifecycleEvent($this->createPayload()));
            $this->fail('A ProviderException should have been thrown.');
        } catch (ProviderException $providerException) {
            $this->assertStringContainsString(FailingStorageProvider::class.': '.FailingStorageProvider::class.' failed', $providerException->getMessage());
            $this->assertInstanceOf(\RuntimeException::class, $providerException->getPrevious());
            $this->assertSame(FailingStorageProvider::class.' failed', $providerException->getPrevious()->getMessage());
        }
    }

    public function testThrowsWhenEveryStorageProviderFails(): void
    {
        $auditor = $this->createAuditor();
        $auditor->registerProvider(new FailingStorageProvider());
        $auditor->registerProvider(new OtherFailingStorageProvider());

        try {
            $auditor->getEventDispatcher()->dispatch(new LifecycleEvent($this->createPayload()));
            $this->fail('A ProviderException should have been thrown.');
        } catch (ProviderException $providerException) {
            $this->assertStringContainsString(FailingStorageProvider::class.': ', $providerException->getMessage());
            $this->assertStringContainsString(OtherFailingStorageProvider::class.': ', $providerException->getMessage());
            $this->assertInstanceOf(\RuntimeException::class, $providerException->getPrevious());
            $this->assertSame(FailingStorageProvider::class.' failed', $providerException->getPrevious()->getMessage());
        }
    }

    public function testDoesNotThrowWhenAnotherStorageProviderPersisted(): void
    {
        $auditor = $this->createAuditor();
        $recording = new RecordingStorageProvider();
        $auditor->registerProvider(new FailingStorageProvider());
        $auditor->registerProvider($recording);

        $event = $auditor->getEventDispatcher()->dispatch(new LifecycleEvent($this->createPayload()));

        $this->assertSame([$event], $recording->persisted);
    }

    public function testDoesNotThrowWithoutStorageProvider(): void
    {
        $auditor = $this->createAuditor();
        $auditor->registerProvider(new AuditNoStorageProvider());

        $event = $auditor->getEventDispatcher()->dispatch(new LifecycleEvent($this->createPayload()));

        $this->assertInstanceOf(LifecycleEvent::class, $event);
    }

    /**
     * @return array<string, mixed>
     */
    private function createPayload(): array
    {
        return [
            'entity' => AuditEventSubscriber::class,
            'table' => '',
            'schema_version' => 2,
            'type' => '',
            'object_id' => '',
            'discriminator' => '',
            'transaction_id' => '',
            'diffs' => '',
            'extra_data' => '',
            'blame_id' => '',
            'blame' => '',
            'created_at' => '',
        ];
    }
}
