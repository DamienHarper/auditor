<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;

trait ReaderTrait
{
//    use DoctrineProviderTrait;

    /**
     * Creates a Reader.
     */
    private function createReader(?DoctrineProvider $provider = null): Reader
    {
        return new Reader($provider ?? $this->provider ?? $this->createDoctrineProvider());
    }
}
