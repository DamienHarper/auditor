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

        $this->assertTrue($configuration->isEnabled(), 'Auditor is enabled by default.');
    }

    public function testDisable(): void
    {
        $configuration = $this->createAuditorConfiguration();
        $configuration->disable();

        $this->assertFalse($configuration->isEnabled(), 'Auditor is disabled.');
    }

    public function testEnable(): void
    {
        $configuration = $this->createAuditorConfiguration();
        $configuration->disable();
        $configuration->enable();

        $this->assertTrue($configuration->isEnabled(), 'Auditor is enabled.');
    }

    public function testDefaultTimezoneIsUTC(): void
    {
        $configuration = $this->createAuditorConfiguration();

        $this->assertSame('UTC', $configuration->getTimezone(), 'Default timezone is UTC.');
    }

    public function testCustomTimezone(): void
    {
        $configuration = $this->createAuditorConfiguration([
            'timezone' => 'Europe/Paris',
        ]);

        $this->assertSame('Europe/Paris', $configuration->getTimezone(), 'Custom timezone is "Europe/Paris".');
    }
}
