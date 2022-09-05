<?php

declare(strict_types=1);

namespace DH\Auditor;

use DH\Auditor\EventSubscriber\AuditEventSubscriber;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Exception\ProviderException;
use DH\Auditor\Provider\ProviderInterface;
use ReflectionException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @see \DH\Auditor\Tests\AuditorTest
 */
class Auditor
{
    private Configuration $configuration;

    /**
     * @var ProviderInterface[]
     */
    private array $providers = [];

    /**
     * @var ProviderInterface[]
     */
    private array $storageProviders = [];

    /**
     * @var ProviderInterface[]
     */
    private array $auditProviders = [];

    private \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher;

    /**
     * @throws ReflectionException
     */
    public function __construct(Configuration $configuration, EventDispatcherInterface $dispatcher)
    {
        $this->configuration = $configuration;
        $this->dispatcher = $dispatcher;

        // Attach persistence event subscriber to provided dispatcher
        $dispatcher->addSubscriber(new AuditEventSubscriber($this));
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->dispatcher;
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    /**
     * @return ProviderInterface[]
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getProvider(string $name): ProviderInterface
    {
        if (!$this->hasProvider($name)) {
            throw new InvalidArgumentException(sprintf('Unknown provider "%s"', $name));
        }

        return $this->providers[$name];
    }

    public function hasProvider(string $name): bool
    {
        return \array_key_exists($name, $this->providers);
    }

    /**
     * @throws ProviderException
     */
    public function registerProvider(ProviderInterface $provider): self
    {
        if (!$provider->supportsStorage() && !$provider->supportsAuditing()) {
            throw new ProviderException(sprintf('Provider "%s" does not support storage and auditing.', \get_class($provider)));
        }

        $this->providers[\get_class($provider)] = $provider;
        $provider->setAuditor($this);

        if ($provider->supportsStorage()) {
            $this->enableStorage($provider);
        }

        if ($provider->supportsAuditing()) {
            $this->enableAuditing($provider);
        }

        return $this;
    }

    /**
     * @throws ProviderException
     */
    public function enableStorage(ProviderInterface $provider): self
    {
        if (!$provider->supportsStorage()) {
            throw new ProviderException(sprintf('Provider "%s" does not support storage.', \get_class($provider)));
        }

        $this->storageProviders[\get_class($provider)] = $provider;

        return $this;
    }

    /**
     * @throws ProviderException
     */
    public function disableStorage(ProviderInterface $provider): self
    {
        if (!$provider->supportsStorage()) {
            throw new ProviderException(sprintf('Provider "%s" does not support storage.', \get_class($provider)));
        }

        if (1 === \count($this->storageProviders)) {
            throw new ProviderException('At least one storage provider must be enabled.');
        }

        unset($this->storageProviders[\get_class($provider)]);

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function isStorageEnabled(ProviderInterface $provider): bool
    {
        $key = \get_class($provider);
        if (!$this->hasProvider($key)) {
            throw new InvalidArgumentException(sprintf('Unknown provider "%s"', $key));
        }

        return \array_key_exists($key, $this->storageProviders);
    }

    /**
     * @throws ProviderException
     */
    public function enableAuditing(ProviderInterface $provider): self
    {
        if (!$provider->supportsAuditing()) {
            throw new ProviderException(sprintf('Provider "%s" does not support audit hooks.', \get_class($provider)));
        }

        $this->auditProviders[\get_class($provider)] = $provider;

        return $this;
    }

    /**
     * @throws ProviderException
     */
    public function disableAuditing(ProviderInterface $provider): self
    {
        if (!$provider->supportsAuditing()) {
            throw new ProviderException(sprintf('Provider "%s" does not support audit hooks.', \get_class($provider)));
        }

        if (1 === \count($this->auditProviders)) {
            throw new ProviderException('At least one auditing provider must be enabled.');
        }

        unset($this->auditProviders[\get_class($provider)]);

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function isAuditingEnabled(ProviderInterface $provider): bool
    {
        $key = \get_class($provider);
        if (!$this->hasProvider($key)) {
            throw new InvalidArgumentException(sprintf('Unknown provider "%s"', $key));
        }

        return \array_key_exists($key, $this->auditProviders);
    }
}
