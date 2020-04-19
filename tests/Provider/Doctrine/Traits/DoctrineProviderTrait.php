<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits;

use DH\Auditor\Provider\Doctrine\Audit\Annotation\AnnotationLoader;
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
        return new DoctrineProvider(
            $configuration ?? $this->createProviderConfiguration(),
            new AnnotationLoader($this->createEntityManager())
        );
    }

    private function createDoctrineProvider(?Configuration $configuration = null): DoctrineProvider
    {
        $auditor = $this->createAuditor();
        $provider = new DoctrineProvider(
            $configuration ?? $this->createProviderConfiguration(),
            new AnnotationLoader($this->createEntityManager())
        );
        $auditor->registerProvider($provider);

        return $provider;
    }
}
