<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits\Schema;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;

trait DefaultSchemaSetupTrait
{
    use SchemaSetupTrait;

    /**
     * @var DoctrineProvider
     */
    private $provider;

    private function createAndInitDoctrineProvider(): void
    {
        $this->provider = $this->createDoctrineProvider();
    }
}
