<?php

declare(strict_types=1);

namespace DH\Auditor\Event;

use DH\Auditor\Tests\Event\LifecycleEventTest;

/**
 * @see LifecycleEventTest
 */
final class LifecycleEvent extends AuditEvent
{
    public function __construct(array $payload, public readonly ?object $entity = null)
    {
        parent::__construct($payload);
    }
}
