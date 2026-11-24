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
#[AsEventListener(event: LifecycleEvent::class, priority: self::PRIORITY)]
final readonly class AuditEventSubscriber
{
    /**
     * Runs after any other LifecycleEvent listener, so that they can alter the payload before it is persisted.
     */
    public const int PRIORITY = -1_000_000;

    public function __construct(private Auditor $auditor) {}

    public function __invoke(LifecycleEvent $event): LifecycleEvent
    {
        foreach ($this->auditor->getProviders() as $provider) {
            if ($provider->supportsStorage() && $this->auditor->isStorageEnabled($provider)) {
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
