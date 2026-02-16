<?php

declare(strict_types=1);

namespace DH\Auditor;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\EventSubscriber\AuditEventSubscriber;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Exception\ProviderException;
use DH\Auditor\Provider\ProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @see Tests\AuditorTest
 */
final class Auditor
{
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

    /**
     * @throws \ReflectionException
     */
    public function __construct(private readonly Configuration $configuration, private readonly EventDispatcherInterface $dispatcher)
    {
        // Attach persistence event listener to provided dispatcher
        $this->dispatcher->addListener(LifecycleEvent::class, new AuditEventSubscriber($this), -1_000_000);
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
            throw new InvalidArgumentException(\sprintf('Unknown provider "%s"', $name));
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
            throw new ProviderException(\sprintf('Provider "%s" does not support storage and auditing.', $provider::class));
        }

        $this->providers[$provider::class] = $provider;
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
            throw new ProviderException(\sprintf('Provider "%s" does not support storage.', $provider::class));
        }

        $this->storageProviders[$provider::class] = $provider;

        return $this;
    }

    /**
     * @throws ProviderException
     */
    public function disableStorage(ProviderInterface $provider): self
    {
        if (!$provider->supportsStorage()) {
            throw new ProviderException(\sprintf('Provider "%s" does not support storage.', $provider::class));
        }

        if (1 === \count($this->storageProviders)) {
            throw new ProviderException('At least one storage provider must be enabled.');
        }

        unset($this->storageProviders[$provider::class]);

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function isStorageEnabled(ProviderInterface $provider): bool
    {
        $key = $provider::class;
        if (!$this->hasProvider($key)) {
            throw new InvalidArgumentException(\sprintf('Unknown provider "%s"', $key));
        }

        return \array_key_exists($key, $this->storageProviders);
    }

    /**
     * @throws ProviderException
     */
    public function enableAuditing(ProviderInterface $provider): self
    {
        if (!$provider->supportsAuditing()) {
            throw new ProviderException(\sprintf('Provider "%s" does not support audit hooks.', $provider::class));
        }

        $this->auditProviders[$provider::class] = $provider;

        return $this;
    }

    /**
     * @throws ProviderException
     */
    public function disableAuditing(ProviderInterface $provider): self
    {
        if (!$provider->supportsAuditing()) {
            throw new ProviderException(\sprintf('Provider "%s" does not support audit hooks.', $provider::class));
        }

        if (1 === \count($this->auditProviders)) {
            throw new ProviderException('At least one auditing provider must be enabled.');
        }

        unset($this->auditProviders[$provider::class]);

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function isAuditingEnabled(ProviderInterface $provider): bool
    {
        if (!$this->hasProvider($provider::class)) {
            throw new InvalidArgumentException(\sprintf('Unknown provider "%s"', $provider::class));
        }

        return \array_key_exists($provider::class, $this->auditProviders);
    }
}
