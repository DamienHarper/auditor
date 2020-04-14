<?php

namespace DH\Auditor;

use DH\Auditor\Provider\ProviderInterface;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Auditor
{
    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var ProviderInterface
     */
    private $provider;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var bool
     */
    private $is_pre43_dispatcher;

    /**
     * @throws ReflectionException
     */
    public function __construct(Configuration $configuration, ProviderInterface $provider, EventDispatcherInterface $dispatcher)
    {
        $this->configuration = $configuration;
        $this->provider = $provider;
        $this->dispatcher = $dispatcher;

        $this->provider->setAuditor($this);

        $r = new ReflectionMethod($this->dispatcher, 'dispatch');
        $p = $r->getParameters();
        $this->is_pre43_dispatcher = 2 === \count($p) && 'event' !== $p[0]->name;
    }

    public function isPre43Dispatcher(): bool
    {
        return $this->is_pre43_dispatcher;
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function getProvider(): ProviderInterface
    {
        return $this->provider;
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->dispatcher;
    }
}
