<?php

namespace DH\Auditor\Tests\Fixtures\Provider;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Provider\AbstractProvider;

class NoStorageNoAuditProvider extends AbstractProvider
{
    public function persist(LifecycleEvent $event): void
    {
        // TODO: Implement persist() method.
    }

    public function supportsStorage(): bool
    {
        return false;
    }

    public function supportsAuditing(): bool
    {
        return false;
    }
}
