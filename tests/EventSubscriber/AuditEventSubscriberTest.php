<?php

namespace DH\Auditor\Tests\EventSubscriber;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\EventSubscriber\AuditEventSubscriber;
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
        $auditor = $this->createAuditor();
        $dispatcher = $auditor->getEventDispatcher();
        $subscriber = new AuditEventSubscriber($auditor);
        $dispatcher->addSubscriber($subscriber);
        $dispatcher->dispatch(new LifecycleEvent(['fake payload']));

        self::assertArrayHasKey(LifecycleEvent::class, AuditEventSubscriber::getSubscribedEvents());
    }
}
