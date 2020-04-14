<?php

namespace DH\Auditor\Tests\Traits;

use DH\Auditor\Configuration;

trait AuditorConfigurationTrait
{
    private function createAuditorConfiguration(array $options = []): Configuration
    {
        return new Configuration(
            array_merge([
                'timezone' => 'UTC',
                'enabled' => true,
            ], $options)
        );
    }
}
