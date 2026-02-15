<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Fixtures\EventSubscriber;

use DH\Auditor\Event\LifecycleEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: LifecycleEvent::class)]
final class CustomAuditEventSubscriber
{
    public function __invoke(LifecycleEvent $event): LifecycleEvent
    {
        $payload = $event->getPayload();
        $payload['entity'] = self::class;
        $event->setPayload($payload);

        return $event;
    }
}
