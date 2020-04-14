<?php

namespace DH\Auditor\Tests\Traits;

use DH\Auditor\Auditor;
use DH\Auditor\Configuration;
use DH\Auditor\Provider\ProviderInterface;
use DH\Auditor\Tests\Fixtures\DummyProvider;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

trait AuditorTrait
{
    use AuditorConfigurationTrait;

    private function createAuditor(
        ?Configuration $configuration = null,
        ?ProviderInterface $provider = null,
        ?EventDispatcherInterface $dispatcher = null
    ): Auditor {
        return new Auditor(
            $configuration ?? $this->createAuditorConfiguration(),
            $provider ?? new DummyProvider(),
            $dispatcher ?? new EventDispatcher()
        );
    }
}
