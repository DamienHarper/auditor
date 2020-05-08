<?php

namespace DH\Auditor\Tests\Fixtures\Provider;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Provider\AbstractProvider;
use DH\Auditor\Provider\ConfigurationInterface;

class NoStorageNoAuditProvider extends AbstractProvider
{
    public function getConfiguration(): ConfigurationInterface
    {
    }

    public function persist(LifecycleEvent $event): void
    {
    }

    public function supportsStorage(): bool
    {
        return false;
    }

    public function supportsAuditing(): bool
    {
        return false;
    }

    public function getStorageServices(): array
    {
    }

    public function getAuditingServices(): array
    {
    }
}
