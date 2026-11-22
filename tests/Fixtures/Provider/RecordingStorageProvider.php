<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Fixtures\Provider;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Provider\AbstractProvider;
use DH\Auditor\Provider\ConfigurationInterface;

final class RecordingStorageProvider extends AbstractProvider
{
    /**
     * @var list<LifecycleEvent>
     */
    public array $persisted = [];

    #[\Override]
    public function getConfiguration(): ConfigurationInterface {}

    public function persist(LifecycleEvent $event): void
    {
        $this->persisted[] = $event;
    }

    public function supportsStorage(): bool
    {
        return true;
    }

    public function supportsAuditing(): bool
    {
        return false;
    }

    #[\Override]
    public function getStorageServices(): array {}

    #[\Override]
    public function getAuditingServices(): array {}
}
