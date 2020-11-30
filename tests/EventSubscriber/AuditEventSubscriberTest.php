<?php

namespace DH\Auditor\Tests\EventSubscriber;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\EventSubscriber\AuditEventSubscriber;
use DH\Auditor\Tests\Fixtures\EventSubscriber\CustomAuditEventSubscriber;
use DH\Auditor\Tests\Traits\AuditorTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
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
        $dispatcher->addSubscriber($subscriber);
        $dispatcher->dispatch(new LifecycleEvent($payload));

        self::assertArrayHasKey(LifecycleEvent::class, AuditEventSubscriber::getSubscribedEvents());
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
        $dispatcher->addSubscriber($subscriber);

        $subscriber = new CustomAuditEventSubscriber($auditor);
        $dispatcher->addSubscriber($subscriber);

        $dispatcher->dispatch(new LifecycleEvent($payload));

        self::assertArrayHasKey(LifecycleEvent::class, CustomAuditEventSubscriber::getSubscribedEvents());
    }
}
