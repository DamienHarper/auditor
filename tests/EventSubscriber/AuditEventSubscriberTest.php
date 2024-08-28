<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\EventSubscriber;

use DH\Auditor\Auditor;
use DH\Auditor\Configuration;
use DH\Auditor\Event\AuditEvent;
use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\EventSubscriber\AuditEventSubscriber;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\SchemaHelper;
use DH\Auditor\Tests\Fixtures\EventSubscriber\CustomAuditEventSubscriber;
use DH\Auditor\Tests\Traits\AuditorTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
#[CoversClass(AuditEventSubscriber::class)]
#[CoversClass(Auditor::class)]
#[CoversClass(Configuration::class)]
#[CoversClass(AuditEvent::class)]
#[CoversClass(DoctrineHelper::class)]
#[CoversClass(SchemaHelper::class)]
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

        $this->assertArrayHasKey(LifecycleEvent::class, AuditEventSubscriber::getSubscribedEvents());
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

        $this->assertArrayHasKey(LifecycleEvent::class, CustomAuditEventSubscriber::getSubscribedEvents());
    }
}
