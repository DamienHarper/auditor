<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Fixtures\EventSubscriber;

use DH\Auditor\Event\LifecycleEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class CustomAuditEventSubscriber implements EventSubscriberInterface
{
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
