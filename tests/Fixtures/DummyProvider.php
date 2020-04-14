<?php

namespace DH\Auditor\Tests\Fixtures;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Provider\AbstractProvider;

class DummyProvider extends AbstractProvider
{
    public function persist(LifecycleEvent $event): void
    {
        // TODO: Implement persist() method.
    }
}
