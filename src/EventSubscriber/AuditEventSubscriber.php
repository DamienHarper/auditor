<?php

declare(strict_types=1);

namespace DH\Auditor\EventSubscriber;

use DH\Auditor\Auditor;
use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Exception\ProviderException;
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

    /**
     * @throws ProviderException when every storage provider failed to persist the audit entry
     */
    public function __invoke(LifecycleEvent $event): LifecycleEvent
    {
        $persisted = false;
        $failures = [];

        foreach ($this->auditor->getProviders() as $provider) {
            if (!$provider->supportsStorage() || !$this->auditor->isStorageEnabled($provider)) {
                continue;
            }

            try {
                $provider->persist($event);
                $persisted = true;
            } catch (\Exception $exception) {
                // keep going so that the other providers still get the audit entry
                $failures[$provider::class] = $exception;
            }
        }

        if (!$persisted && [] !== $failures) {
            throw $this->createFailureException($failures);
        }

        return $event;
    }

    /**
     * @param non-empty-array<class-string, \Exception> $failures
     */
    private function createFailureException(array $failures): ProviderException
    {
        $reasons = [];
        foreach ($failures as $providerClass => $exception) {
            $reasons[] = \sprintf('%s: %s', $providerClass, $exception->getMessage());
        }

        return new ProviderException(
            \sprintf('No storage provider could persist the audit entry (%s).', implode('; ', $reasons)),
            0,
            reset($failures)
        );
    }
}
