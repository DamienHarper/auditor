<?php

namespace DH\Auditor\EventSubscriber;

use DH\Auditor\Auditor;
use DH\Auditor\Event\LifecycleEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AuditEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var Auditor
     */
    private $auditor;

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
        $this->auditor->getProvider()->persist($event);

        return $event;
    }
}
