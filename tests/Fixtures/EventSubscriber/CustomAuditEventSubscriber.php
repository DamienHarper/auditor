<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Fixtures\EventSubscriber;

use DH\Auditor\Auditor;
use DH\Auditor\Event\LifecycleEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomAuditEventSubscriber implements EventSubscriberInterface
{
    private Auditor $auditor;

    public function __construct(Auditor $auditor)
    {
        $this->auditor = $auditor;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LifecycleEvent::class => 'onAuditEvent',
        ];
    }

    public function onAuditEvent(LifecycleEvent $event): LifecycleEvent
    {
        $payload = $event->getPayload();
        $payload['entity'] = self::class;
        $event->setPayload($payload);

        return $event;
    }
}
