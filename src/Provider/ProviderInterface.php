<?php

namespace DH\Auditor\Provider;

use DH\Auditor\Auditor;
use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Provider\Service\AuditingServiceInterface;
use DH\Auditor\Provider\Service\StorageServiceInterface;

interface ProviderInterface
{
    public function setAuditor(Auditor $auditor): self;

    public function getAuditor(): Auditor;

    public function getConfiguration(): ConfigurationInterface;

    public function isRegistered(): bool;

    public function registerStorageService(StorageServiceInterface $service): self;

    public function registerAuditingService(AuditingServiceInterface $service): self;

    public function persist(LifecycleEvent $event): void;

    /**
     * @return StorageServiceInterface[]
     */
    public function getStorageServices(): array;

    /**
     * @return AuditingServiceInterface[]
     */
    public function getAuditingServices(): array;

    /**
     * Provider supports audit storage.
     */
    public function supportsStorage(): bool;

    /**
     * Provider support audit events.
     */
    public function supportsAuditing(): bool;
}
