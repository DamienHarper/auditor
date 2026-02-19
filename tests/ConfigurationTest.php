<?php

declare(strict_types=1);

namespace DH\Auditor\Tests;

use DH\Auditor\Tests\Traits\AuditorConfigurationTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class ConfigurationTest extends TestCase
{
    use AuditorConfigurationTrait;

    public function testIsEnabledByDefault(): void
    {
        $configuration = $this->createAuditorConfiguration();

        $this->assertTrue($configuration->enabled, 'Auditor is enabled by default.');
    }

    public function testDisable(): void
    {
        $configuration = $this->createAuditorConfiguration();
        $configuration->disable();

        $this->assertFalse($configuration->enabled, 'Auditor is disabled.');
    }

    public function testEnable(): void
    {
        $configuration = $this->createAuditorConfiguration();
        $configuration->disable();
        $configuration->enable();

        $this->assertTrue($configuration->enabled, 'Auditor is enabled.');
    }

    public function testDefaultTimezoneIsUTC(): void
    {
        $configuration = $this->createAuditorConfiguration();

        $this->assertSame('UTC', $configuration->timezone, 'Default timezone is UTC.');
    }

    public function testCustomTimezone(): void
    {
        $configuration = $this->createAuditorConfiguration([
            'timezone' => 'Europe/Paris',
        ]);

        $this->assertSame('Europe/Paris', $configuration->timezone, 'Custom timezone is "Europe/Paris".');
    }

    public function testExtraDataProviderIsNullByDefault(): void
    {
        $configuration = $this->createAuditorConfiguration();

        $this->assertNull($configuration->getExtraDataProvider(), 'ExtraDataProvider is null by default.');
    }

    public function testSetExtraDataProvider(): void
    {
        $configuration = $this->createAuditorConfiguration();
        $provider = static fn (): array => ['route' => 'app_home'];
        $configuration->setExtraDataProvider($provider);

        $this->assertSame($provider, $configuration->getExtraDataProvider(), 'ExtraDataProvider is set correctly.');
    }
}
