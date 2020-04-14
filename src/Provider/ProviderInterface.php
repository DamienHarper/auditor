<?php

namespace DH\Auditor\Provider;

use DH\Auditor\Auditor;
use DH\Auditor\Event\LifecycleEvent;

interface ProviderInterface
{
    public function setAuditor(Auditor $auditor): self;

    public function persist(LifecycleEvent $event): void;
}
