<?php

namespace DH\Auditor\EventSubscriber;

use DH\Auditor\Auditor;
use DH\Auditor\Event\LifecycleEvent;
use Exception;
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
            LifecycleEvent::class => [
                ['onAuditEvent', -1000000],  // should be fired last
            ],
        ];
    }

    public function onAuditEvent(LifecycleEvent $event): LifecycleEvent
    {
        foreach ($this->auditor->getProviders() as $provider) {
            if ($provider->supportsStorage()) {
                try {
                    $provider->persist($event);
                } catch (Exception $e) {
                    // do nothing to ensure other providers are called
                }
            }
        }

        return $event;
    }
}
