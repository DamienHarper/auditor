<?php

namespace DH\Auditor\Tests\Event;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Tests\Traits\AuditorTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class LifecycleEventTest extends TestCase
{
    use AuditorTrait;

    public function testLifecycleEvent(): void
    {
        self::expectException(InvalidArgumentException::class);
        $event = new LifecycleEvent(['invalid payload']);
    }
}
