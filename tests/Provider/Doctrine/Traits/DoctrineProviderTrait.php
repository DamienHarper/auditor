<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits;

use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Tests\Traits\AuditorTrait;

trait DoctrineProviderTrait
{
    use AuditorTrait;
    use EntityManagerInterfaceTrait;
    use ProviderConfigurationTrait;

    private function createUnregisteredDoctrineProvider(?Configuration $configuration = null): DoctrineProvider
    {
        $em = $this->createEntityManager();

        return new DoctrineProvider(
            $configuration ?? $this->createProviderConfiguration(),
            [$em],
            [$em]
        );
    }

    private function createDoctrineProvider(?Configuration $configuration = null): DoctrineProvider
    {
        $em = $this->createEntityManager();
        $auditor = $this->createAuditor();
        $provider = new DoctrineProvider(
            $configuration ?? $this->createProviderConfiguration(),
            [$em],
            [$em]
        );
        $auditor->registerProvider($provider);

        return $provider;
    }
}
