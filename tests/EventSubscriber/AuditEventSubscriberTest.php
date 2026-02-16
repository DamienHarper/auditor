<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\EventSubscriber;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\EventSubscriber\AuditEventSubscriber;
use DH\Auditor\Tests\Fixtures\EventSubscriber\CustomAuditEventSubscriber;
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
            'type' => '',
            'object_id' => '',
            'discriminator' => '',
            'transaction_hash' => '',
            'diffs' => '',
            'blame_id' => '',
            'blame_user' => '',
            'blame_user_fqdn' => '',
            'blame_user_firewall' => '',
            'ip' => '',
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
            'type' => '',
            'object_id' => '',
            'discriminator' => '',
            'transaction_hash' => '',
            'diffs' => '',
            'blame_id' => '',
            'blame_user' => '',
            'blame_user_fqdn' => '',
            'blame_user_firewall' => '',
            'ip' => '',
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
}
