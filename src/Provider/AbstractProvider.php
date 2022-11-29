<?php

declare(strict_types=1);

namespace DH\Auditor\Provider;

use DH\Auditor\Auditor;
use DH\Auditor\Exception\ProviderException;
use DH\Auditor\Provider\Service\AuditingServiceInterface;
use DH\Auditor\Provider\Service\StorageServiceInterface;

abstract class AbstractProvider implements ProviderInterface
{
    protected ?Auditor $auditor = null;

    protected ConfigurationInterface $configuration;

    /**
     * @var StorageServiceInterface[]
     */
    protected array $storageServices = [];

    /**
     * @var AuditingServiceInterface[]
     */
    protected array $auditingServices = [];

    public function getConfiguration(): ConfigurationInterface
    {
        return $this->configuration;
    }

    public function setAuditor(Auditor $auditor): ProviderInterface
    {
        $this->auditor = $auditor;

        return $this;
    }

    public function getAuditor(): Auditor
    {
        if (null === $this->auditor) {
            throw new ProviderException('This provider has not been registered.');
        }

        return $this->auditor;
    }

    public function isRegistered(): bool
    {
        return null !== $this->auditor;
    }

    public function registerStorageService(StorageServiceInterface $service): ProviderInterface
    {
        if (!$this->supportsStorage()) {
            throw new ProviderException('This provider does not provide storage services.');
        }

        if (\array_key_exists($service->getName(), $this->storageServices)) {
            throw new ProviderException(sprintf('A storage service named "%s" is already registered.', $service->getName()));
        }

        $this->storageServices[$service->getName()] = $service;

        return $this;
    }

    /**
     * @return StorageServiceInterface[]
     */
    public function getStorageServices(): array
    {
        return $this->storageServices;
    }

    public function registerAuditingService(AuditingServiceInterface $service): ProviderInterface
    {
        if (!$this->supportsAuditing()) {
            throw new ProviderException('This provider does not provide auditing services.');
        }

        if (\array_key_exists($service->getName(), $this->auditingServices)) {
            throw new ProviderException(sprintf('An auditing service named "%s" is already registered.', $service->getName()));
        }

        $this->auditingServices[$service->getName()] = $service;

        return $this;
    }

    /**
     * @return AuditingServiceInterface[]
     */
    public function getAuditingServices(): array
    {
        return $this->auditingServices;
    }
}
