<?php

namespace DH\Auditor\Tests;

use DH\Auditor\Configuration;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Exception\ProviderException;
use DH\Auditor\Tests\Fixtures\Provider\AuditNoStorageProvider;
use DH\Auditor\Tests\Fixtures\Provider\NoStorageNoAuditProvider;
use DH\Auditor\Tests\Fixtures\Provider\StorageAndAuditProvider;
use DH\Auditor\Tests\Fixtures\Provider\StorageNoAuditProvider;
use DH\Auditor\Tests\Traits\AuditorTrait;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
final class AuditorTest extends TestCase
{
    use AuditorTrait;

    public function testGetConfiguration(): void
    {
        $auditor = $this->createAuditor();

        self::assertInstanceOf(Configuration::class, $auditor->getConfiguration());
    }

    public function testGetProviders(): void
    {
        $auditor = $this->createAuditor();

        self::assertIsArray($auditor->getProviders(), 'Auditor::$providers is an array.');
        self::assertCount(0, $auditor->getProviders(), 'Auditor::$providers is an empty array by default.');
    }

    /**
     * @depends testGetProviders
     */
    public function testRegisterProvider(): void
    {
        $auditor = $this->createAuditor();
        $provider = new StorageAndAuditProvider();

        $auditor->registerProvider($provider);

        $expected = [
            StorageAndAuditProvider::class => $provider,
        ];

        self::assertSame($expected, $auditor->getProviders(), 'Provider is registered.');
        self::assertSame($auditor, $provider->getAuditor(), 'Provider is properly registered.');
    }

    /**
     * @depends testRegisterProvider
     */
    public function testHasProvider(): void
    {
        $auditor = $this->createAuditor();
        $provider = new StorageAndAuditProvider();

        $auditor->registerProvider($provider);

        self::assertTrue($auditor->hasProvider(StorageAndAuditProvider::class));
        self::assertFalse($auditor->hasProvider('UNKNOWN_PROVIDER'));
    }

    /**
     * @depends testRegisterProvider
     */
    public function testRegisterNoStorageNoAuditProvider(): void
    {
        $auditor = $this->createAuditor();
        $provider = new NoStorageNoAuditProvider();

        $this->expectException(ProviderException::class);
        $auditor->registerProvider($provider);
    }

    /**
     * @depends testRegisterProvider
     */
    public function testGetProvider(): void
    {
        $auditor = $this->createAuditor();
        $provider = new StorageAndAuditProvider();

        $auditor->registerProvider($provider);

        self::assertSame($provider, $auditor->getProvider(StorageAndAuditProvider::class), 'Provider is found.');

        $this->expectException(InvalidArgumentException::class);
        $auditor->getProvider('UNKNOWN_PROVIDER');
    }

    /**
     * @depends testRegisterProvider
     */
    public function testIsStorageEnabled(): void
    {
        $auditor = $this->createAuditor();
        $provider1 = new StorageAndAuditProvider();
        $provider2 = new AuditNoStorageProvider();
        $auditor->registerProvider($provider1);
        $auditor->registerProvider($provider2);

        self::assertTrue($auditor->isStorageEnabled($provider1), 'Storage provider 1 is enabled.');
        self::assertFalse($auditor->isStorageEnabled($provider2), 'Storage provider 2 is disabled.');

        $this->expectException(InvalidArgumentException::class);
        $provider3 = new NoStorageNoAuditProvider();
        $auditor->isStorageEnabled($provider3);
    }

    /**
     * @depends testIsStorageEnabled
     */
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

        self::assertFalse($auditor->isStorageEnabled($provider2), 'Storage provider 2 is disabled.');

        $this->expectException(ProviderException::class);
        $auditor->disableStorage($provider3);
    }

    /**
     * @depends testIsStorageEnabled
     */
    public function testDisableStorageWhenOnlyOneIsEnabled(): void
    {
        $auditor = $this->createAuditor();
        $provider = new StorageAndAuditProvider();

        $auditor->registerProvider($provider);

        $this->expectException(ProviderException::class);
        $auditor->disableStorage($provider);
    }

    /**
     * @depends testDisableStorage
     */
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

        self::assertTrue($auditor->isStorageEnabled($provider2), 'Storage provider 2 is enabled.');

        $this->expectException(ProviderException::class);
        $auditor->enableStorage($provider3);
    }

    /**
     * @depends testRegisterProvider
     */
    public function testIsAuditingEnabled(): void
    {
        $auditor = $this->createAuditor();
        $provider1 = new StorageAndAuditProvider();
        $provider2 = new StorageNoAuditProvider();
        $auditor->registerProvider($provider1);
        $auditor->registerProvider($provider2);

        self::assertTrue($auditor->isAuditingEnabled($provider1), 'Auditing provider 1 is enabled.');
        self::assertFalse($auditor->isAuditingEnabled($provider2), 'Auditing provider 2 is disabled.');

        $this->expectException(InvalidArgumentException::class);
        $provider3 = new NoStorageNoAuditProvider();
        $auditor->isAuditingEnabled($provider3);
    }

    /**
     * @depends testIsStorageEnabled
     */
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

        self::assertFalse($auditor->isAuditingEnabled($provider2), 'Auditing provider 2 is disabled.');

        $this->expectException(ProviderException::class);
        $auditor->disableAuditing($provider3);
    }

    /**
     * @depends testIsStorageEnabled
     */
    public function testDisableAuditingWhenOnlyOneIsEnabled(): void
    {
        $auditor = $this->createAuditor();
        $provider = new StorageAndAuditProvider();

        $auditor->registerProvider($provider);

        $this->expectException(ProviderException::class);
        $auditor->disableAuditing($provider);
    }

    /**
     * @depends testDisableStorage
     */
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

        self::assertTrue($auditor->isAuditingEnabled($provider2), 'Auditing provider 2 is enabled.');

        $this->expectException(ProviderException::class);
        $auditor->enableAuditing($provider3);
    }

    public function testGetEventDispatcher(): void
    {
        $auditor = $this->createAuditor();

        self::assertInstanceOf(EventDispatcher::class, $auditor->getEventDispatcher(), 'Auditor::getEventDispatcher() is OK.');
    }

    public function testIsPre43Dispatcher(): void
    {
        $auditor = $this->createAuditor();

        $r = new ReflectionMethod($auditor->getEventDispatcher(), 'dispatch');
        $p = $r->getParameters();
        $isPre43Dispatcher = 2 === \count($p) && 'event' !== $p[0]->name;

        self::assertSame($isPre43Dispatcher, $auditor->isPre43Dispatcher(), 'Auditor::isPre43Dispatcher() is OK.');
    }
}
