<?php

declare(strict_types=1);

namespace DH\Auditor\EventSubscriber;

use DH\Auditor\Auditor;
use DH\Auditor\Event\LifecycleEvent;
use Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @see \DH\Auditor\Tests\EventSubscriber\AuditEventSubscriberTest
 */
final class AuditEventSubscriber implements EventSubscriberInterface
{
    private Auditor $auditor;

    public function __construct(Auditor $auditor)
    {
        $this->auditor = $auditor;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LifecycleEvent::class => [
                ['onAuditEvent', -1_000_000],  // should be fired last
            ],
        ];
    }

    public function onAuditEvent(LifecycleEvent $event): LifecycleEvent
    {
        foreach ($this->auditor->getProviders() as $provider) {
            if ($provider->supportsStorage()) {
                try {
                    $provider->persist($event);
                } catch (Exception) {
                    // do nothing to ensure other providers are called
                }
            }
        }

        return $event;
    }
}
