<?php

namespace DH\Auditor\Tests\Fixtures\Provider;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Provider\AbstractProvider;
use DH\Auditor\Provider\ConfigurationInterface;

class StorageAndAuditProvider extends AbstractProvider
{
    public function getConfiguration(): ConfigurationInterface
    {
    }

    public function persist(LifecycleEvent $event): void
    {
    }

    public function supportsStorage(): bool
    {
        return true;
    }

    public function supportsAuditing(): bool
    {
        return true;
    }

    public function getStorageServices(): array
    {
    }

    public function getAuditingServices(): array
    {
    }
}
