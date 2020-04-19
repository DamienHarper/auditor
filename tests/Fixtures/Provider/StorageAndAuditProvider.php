<?php

namespace DH\Auditor\Tests\Fixtures\Provider;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Provider\AbstractProvider;

class StorageAndAuditProvider extends AbstractProvider
{
    public function persist(LifecycleEvent $event): void
    {
        // TODO: Implement persist() method.
    }

    public function supportsStorage(): bool
    {
        return true;
    }

    public function supportsAuditing(): bool
    {
        return true;
    }
}
