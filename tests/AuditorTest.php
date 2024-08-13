<?php

declare(strict_types=1);

namespace DH\Auditor\Tests;

use DH\Auditor\Configuration;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Exception\ProviderException;
use DH\Auditor\Tests\Fixtures\Provider\AuditNoStorageProvider;
use DH\Auditor\Tests\Fixtures\Provider\NoStorageNoAuditProvider;
use DH\Auditor\Tests\Fixtures\Provider\StorageAndAuditProvider;
use DH\Auditor\Tests\Fixtures\Provider\StorageNoAuditProvider;
use DH\Auditor\Tests\Traits\AuditorTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Small]
#[CoversNothing]
final class AuditorTest extends TestCase
{
    use AuditorTrait;

    public function testGetConfiguration(): void
    {
        $auditor = $this->createAuditor();

        $this->assertInstanceOf(Configuration::class, $auditor->getConfiguration());
    }

    public function testGetProviders(): void
    {
        $auditor = $this->createAuditor();

        $this->assertIsArray($auditor->getProviders(), 'Auditor::$providers is an array.');
        $this->assertCount(0, $auditor->getProviders(), 'Auditor::$providers is an empty array by default.');
    }

    #[Depends('testGetProviders')]
    public function testRegisterProvider(): void
    {
        $auditor = $this->createAuditor();
        $provider = new StorageAndAuditProvider();

        $auditor->registerProvider($provider);

        $expected = [
            StorageAndAuditProvider::class => $provider,
        ];

        $this->assertSame($expected, $auditor->getProviders(), 'Provider is registered.');
        $this->assertSame($auditor, $provider->getAuditor(), 'Provider is properly registered.');
    }

    #[Depends('testRegisterProvider')]
    public function testHasProvider(): void
    {
        $auditor = $this->createAuditor();
        $provider = new StorageAndAuditProvider();

        $auditor->registerProvider($provider);

        $this->assertTrue($auditor->hasProvider(StorageAndAuditProvider::class));
        $this->assertFalse($auditor->hasProvider('UNKNOWN_PROVIDER'));
    }

    #[Depends('testRegisterProvider')]
    public function testRegisterNoStorageNoAuditProvider(): void
    {
        $auditor = $this->createAuditor();
        $provider = new NoStorageNoAuditProvider();

        $this->expectException(ProviderException::class);
        $auditor->registerProvider($provider);
    }

    #[Depends('testRegisterProvider')]
    public function testGetProvider(): void
    {
        $auditor = $this->createAuditor();
        $provider = new StorageAndAuditProvider();

        $auditor->registerProvider($provider);

        $this->assertSame($provider, $auditor->getProvider(StorageAndAuditProvider::class), 'Provider is found.');

        $this->expectException(InvalidArgumentException::class);
        $auditor->getProvider('UNKNOWN_PROVIDER');
    }

    #[Depends('testRegisterProvider')]
    public function testIsStorageEnabled(): void
    {
        $auditor = $this->createAuditor();
        $provider1 = new StorageAndAuditProvider();
        $provider2 = new AuditNoStorageProvider();
        $auditor->registerProvider($provider1);
        $auditor->registerProvider($provider2);

        $this->assertTrue($auditor->isStorageEnabled($provider1), 'Storage provider 1 is enabled.');
        $this->assertFalse($auditor->isStorageEnabled($provider2), 'Storage provider 2 is disabled.');

        $this->expectException(InvalidArgumentException::class);
        $provider3 = new NoStorageNoAuditProvider();
        $auditor->isStorageEnabled($provider3);
    }

    #[Depends('testIsStorageEnabled')]
    public function testDisableStorage(): void
    {
        $auditor = $this->createAuditor();
        $provider1 = new StorageAndAuditProvider();
        $provider2 = new StorageNoAuditProvider();
        $provider3 = new AuditNoStorageProvider();

        $auditor->registerProvider($provider1);
        $auditor->registerProvider($provider2);
        $auditor->registerProvider($provider3);

        $auditor->disableStorage($provider2);

        $this->assertFalse($auditor->isStorageEnabled($provider2), 'Storage provider 2 is disabled.');

        $this->expectException(ProviderException::class);
        $auditor->disableStorage($provider3);
    }

    #[Depends('testIsStorageEnabled')]
    public function testDisableStorageWhenOnlyOneIsEnabled(): void
    {
        $auditor = $this->createAuditor();
        $provider = new StorageAndAuditProvider();

        $auditor->registerProvider($provider);

        $this->expectException(ProviderException::class);
        $auditor->disableStorage($provider);
    }

    #[Depends('testDisableStorage')]
    public function testEnableStorage(): void
    {
        $auditor = $this->createAuditor();
        $provider1 = new StorageAndAuditProvider();
        $provider2 = new StorageNoAuditProvider();
        $provider3 = new AuditNoStorageProvider();

        $auditor->registerProvider($provider1);
        $auditor->registerProvider($provider2);
        $auditor->registerProvider($provider3);
        $auditor->disableStorage($provider2);
        $auditor->enableStorage($provider2);

        $this->assertTrue($auditor->isStorageEnabled($provider2), 'Storage provider 2 is enabled.');

        $this->expectException(ProviderException::class);
        $auditor->enableStorage($provider3);
    }

    #[Depends('testRegisterProvider')]
    public function testIsAuditingEnabled(): void
    {
        $auditor = $this->createAuditor();
        $provider1 = new StorageAndAuditProvider();
        $provider2 = new StorageNoAuditProvider();
        $auditor->registerProvider($provider1);
        $auditor->registerProvider($provider2);

        $this->assertTrue($auditor->isAuditingEnabled($provider1), 'Auditing provider 1 is enabled.');
        $this->assertFalse($auditor->isAuditingEnabled($provider2), 'Auditing provider 2 is disabled.');

        $this->expectException(InvalidArgumentException::class);
        $provider3 = new NoStorageNoAuditProvider();
        $auditor->isAuditingEnabled($provider3);
    }

    #[Depends('testIsStorageEnabled')]
    public function testDisableAuditing(): void
    {
        $auditor = $this->createAuditor();
        $provider1 = new StorageAndAuditProvider();
        $provider2 = new AuditNoStorageProvider();
        $provider3 = new StorageNoAuditProvider();

        $auditor->registerProvider($provider1);
        $auditor->registerProvider($provider2);
        $auditor->registerProvider($provider3);

        $auditor->disableAuditing($provider2);

        $this->assertFalse($auditor->isAuditingEnabled($provider2), 'Auditing provider 2 is disabled.');

        $this->expectException(ProviderException::class);
        $auditor->disableAuditing($provider3);
    }

    #[Depends('testIsStorageEnabled')]
    public function testDisableAuditingWhenOnlyOneIsEnabled(): void
    {
        $auditor = $this->createAuditor();
        $provider = new StorageAndAuditProvider();

        $auditor->registerProvider($provider);

        $this->expectException(ProviderException::class);
        $auditor->disableAuditing($provider);
    }

    #[Depends('testDisableStorage')]
    public function testEnableAuditing(): void
    {
        $auditor = $this->createAuditor();
        $provider1 = new StorageAndAuditProvider();
        $provider2 = new AuditNoStorageProvider();
        $provider3 = new StorageNoAuditProvider();

        $auditor->registerProvider($provider1);
        $auditor->registerProvider($provider2);
        $auditor->registerProvider($provider3);
        $auditor->disableAuditing($provider2);
        $auditor->enableAuditing($provider2);

        $this->assertTrue($auditor->isAuditingEnabled($provider2), 'Auditing provider 2 is enabled.');

        $this->expectException(ProviderException::class);
        $auditor->enableAuditing($provider3);
    }

    public function testGetEventDispatcher(): void
    {
        $auditor = $this->createAuditor();

        $this->assertInstanceOf(EventDispatcher::class, $auditor->getEventDispatcher(), 'Auditor::getEventDispatcher() is OK.');
    }
}
