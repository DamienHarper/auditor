<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Event;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\EventSubscriber\AuditEventSubscriber;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\SchemaHelper;
use DH\Auditor\Tests\Traits\AuditorTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
#[CoversClass(LifecycleEvent::class)]
#[CoversClass(DoctrineHelper::class)]
#[CoversClass(SchemaHelper::class)]
final class LifecycleEventTest extends TestCase
{
    use AuditorTrait;

    /**
     * @var array<string, class-string<\DH\Auditor\EventSubscriber\AuditEventSubscriber>|string>
     */
    private const PAYLOAD = [
        'entity' => AuditEventSubscriber::class,
        'table' => '',
        'type' => '',
        'object_id' => '',
        'discriminator' => '',
        'transaction_hash' => '',
        'diffs' => '',
        'blame_id' => '',
        'blame_user' => '',
        'blame_user_fqdn' => '',
        'blame_user_firewall' => '',
        'ip' => '',
        'created_at' => '',
    ];

    public function testLifecycleEvent(): void
    {
        $event = new LifecycleEvent(self::PAYLOAD);
        $this->assertSame(self::PAYLOAD, $event->getPayload());
    }

    public function testLifecycleEventWithInvalidPayload(): void
    {
        self::expectException(InvalidArgumentException::class);
        new LifecycleEvent(['invalid payload']);
    }

    public function testSetValidPayload(): void
    {
        $payload = [
            'entity' => AuditEventSubscriber::class,
            'table' => '',
            'type' => '',
            'object_id' => '',
            'discriminator' => '',
            'transaction_hash' => '',
            'diffs' => '',
            'blame_id' => '',
            'blame_user' => '',
            'blame_user_fqdn' => '',
            'blame_user_firewall' => '',
            'ip' => '',
            'created_at' => '',
        ];

        $event = new LifecycleEvent($payload);

        $payload['entity'] = 'new entity';
        $event->setPayload($payload);
        $this->assertSame($payload, $event->getPayload());
    }

    public function testSetInvalidPayload(): void
    {
        $payload = [
            'entity' => AuditEventSubscriber::class,
            'table' => '',
            'type' => '',
            'object_id' => '',
            'discriminator' => '',
            'transaction_hash' => '',
            'diffs' => '',
            'blame_id' => '',
            'blame_user' => '',
            'blame_user_fqdn' => '',
            'blame_user_firewall' => '',
            'ip' => '',
            'created_at' => '',
        ];

        $event = new LifecycleEvent($payload);

        self::expectException(InvalidArgumentException::class);
        $payload = ['invalid payload'];
        $event->setPayload($payload);
    }
}
