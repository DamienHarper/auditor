<?php

namespace DH\Auditor\Tests;

use DH\Auditor\Configuration;
use DH\Auditor\Provider\ProviderInterface;
use DH\Auditor\Tests\Fixtures\DummyProvider;
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

    public function testGetProvider(): void
    {
        $auditor = $this->createAuditor();

        self::assertInstanceOf(ProviderInterface::class, $auditor->getProvider());
        self::assertInstanceOf(DummyProvider::class, $auditor->getProvider());
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
