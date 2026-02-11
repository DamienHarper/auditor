<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Fixtures\Provider;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Provider\AbstractProvider;
use DH\Auditor\Provider\ConfigurationInterface;

final class StorageNoAuditProvider extends AbstractProvider
{
    #[\Override]
    public function getConfiguration(): ConfigurationInterface {}

    public function persist(LifecycleEvent $event): void {}

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
