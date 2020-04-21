<?php

namespace DH\Auditor\Provider;

use DH\Auditor\Auditor;
use DH\Auditor\Event\LifecycleEvent;

interface ProviderInterface
{
    public function setAuditor(Auditor $auditor): self;

    public function isRegistered(): bool;

    public function persist(LifecycleEvent $event): void;

    /**
     * Provider supports audit storage.
     */
    public function supportsStorage(): bool;

    /**
     * Provider support audit events.
     */
    public function supportsAuditing(): bool;
}
