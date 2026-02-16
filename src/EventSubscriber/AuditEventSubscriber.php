<?php

declare(strict_types=1);

namespace DH\Auditor\EventSubscriber;

use DH\Auditor\Auditor;
use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Tests\EventSubscriber\AuditEventSubscriberTest;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * @see AuditEventSubscriberTest
 */
#[AsEventListener(event: LifecycleEvent::class, priority: -1_000_000)]
final readonly class AuditEventSubscriber
{
    public function __construct(private Auditor $auditor) {}

    public function __invoke(LifecycleEvent $event): LifecycleEvent
    {
        foreach ($this->auditor->getProviders() as $provider) {
            if ($provider->supportsStorage()) {
                try {
                    $provider->persist($event);
                } catch (\Exception) {
                    // do nothing to ensure other providers are called
                }
            }
        }

        return $event;
    }
}
