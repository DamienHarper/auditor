<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits;

use DH\Auditor\Provider\Doctrine\Configuration;

trait ProviderConfigurationTrait
{
    private function createProviderConfiguration(array $options = []): Configuration
    {
        return new Configuration(
            array_merge([
                'table_prefix' => '',
                'table_suffix' => '_audit',
                'ignored_columns' => [],
                'entities' => [],
                'enabled_viewer' => true,
            ], $options)
        );
    }
}
