<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Traits\Schema;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;

trait DefaultSchemaSetupTrait
{
    use SchemaSetupTrait;

    private DoctrineProvider $provider;

    private function createAndInitDoctrineProvider(): void
    {
        $this->provider = $this->createDoctrineProvider();
    }
}
