<?php

namespace DH\Auditor\Tests\Traits;

use DH\Auditor\Auditor;
use DH\Auditor\Configuration;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

trait AuditorTrait
{
    use AuditorConfigurationTrait;

    private function createAuditor(?Configuration $configuration = null, ?EventDispatcherInterface $dispatcher = null): Auditor
    {
        return new Auditor(
            $configuration ?? $this->createAuditorConfiguration(),
            $dispatcher ?? new EventDispatcher()
        );
    }
}
