<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits;

use DH\Auditor\Provider\Doctrine\Annotation\AnnotationLoader;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Tests\Traits\AuditorTrait;

trait DoctrineProviderTrait
{
    use AuditorTrait;
    use EntityManagerInterfaceTrait;
    use ProviderConfigurationTrait;

    private function createDoctrineProvider(Configuration $configuration): DoctrineProvider
    {
        $auditor = $this->createAuditor(
            $this->createAuditorConfiguration(),
            new DoctrineProvider(
                $configuration ?? $this->createProviderConfiguration(),
                new AnnotationLoader($this->createEntityManager())
            )
        );

        return $auditor->getProvider();
    }
}
