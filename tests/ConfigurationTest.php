<?php

namespace DH\Auditor\Tests;

use DH\Auditor\Tests\Traits\AuditorConfigurationTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ConfigurationTest extends TestCase
{
    use AuditorConfigurationTrait;

    public function testIsEnabledByDefault(): void
    {
        $configuration = $this->createAuditorConfiguration();

        self::assertTrue($configuration->isEnabled(), 'Auditor is enabled by default.');
    }

    public function testDisable(): void
    {
        $configuration = $this->createAuditorConfiguration();
        $configuration->disable();

        self::assertFalse($configuration->isEnabled(), 'Auditor is disabled.');
    }

    public function testEnable(): void
    {
        $configuration = $this->createAuditorConfiguration();
        $configuration->disable();
        $configuration->enable();

        self::assertTrue($configuration->isEnabled(), 'Auditor is enabled.');
    }

    public function testDefaultTimezoneIsUTC(): void
    {
        $configuration = $this->createAuditorConfiguration();

        self::assertSame('UTC', $configuration->getTimezone(), 'Default timezone is UTC.');
    }

    public function testCustomTimezone(): void
    {
        $configuration = $this->createAuditorConfiguration([
            'timezone' => 'Europe/Paris',
        ]);

        self::assertSame('Europe/Paris', $configuration->getTimezone(), 'Custom timezone is "Europe/Paris".');
    }
}
