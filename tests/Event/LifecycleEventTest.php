<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Event;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\EventSubscriber\AuditEventSubscriber;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Tests\Traits\AuditorTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class LifecycleEventTest extends TestCase
{
    use AuditorTrait;

    /**
     * @var array<string, class-string<AuditEventSubscriber>|string>
     */
    private const array PAYLOAD = [
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
        'extra_data' => '',
        'created_at' => '',
    ];

    public function testLifecycleEvent(): void
    {
        $event = new LifecycleEvent(self::PAYLOAD);
        $this->assertSame(self::PAYLOAD, $event->getPayload());
    }

    public function testLifecycleEventWithEntity(): void
    {
        $entity = new \stdClass();
        $event = new LifecycleEvent(self::PAYLOAD, $entity);
        $this->assertSame($entity, $event->entity);
    }

    public function testLifecycleEventWithoutEntity(): void
    {
        $event = new LifecycleEvent(self::PAYLOAD);
        $this->assertNull($event->entity);
    }

    public function testLifecycleEventWithInvalidPayload(): void
    {
        $this->expectException(InvalidArgumentException::class);
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
            'extra_data' => '',
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
            'extra_data' => '',
            'blame_id' => '',
            'blame_user' => '',
            'blame_user_fqdn' => '',
            'blame_user_firewall' => '',
            'ip' => '',
            'created_at' => '',
        ];

        $event = new LifecycleEvent($payload);

        $this->expectException(InvalidArgumentException::class);
        $payload = ['invalid payload'];
        $event->setPayload($payload);
    }

    public function testPayloadWithoutExtraDataIsNormalized(): void
    {
        // Payload without extra_data key
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

        // Should not throw, extra_data should be auto-added as null
        $event = new LifecycleEvent($payload);
        $resultPayload = $event->getPayload();

        $this->assertArrayHasKey('extra_data', $resultPayload);
        $this->assertNull($resultPayload['extra_data']);
    }

    public function testSetPayloadWithoutExtraDataIsNormalized(): void
    {
        $event = new LifecycleEvent(self::PAYLOAD);

        // Payload without extra_data key
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

        // Should not throw, extra_data should be auto-added as null
        $event->setPayload($payload);
        $resultPayload = $event->getPayload();

        $this->assertArrayHasKey('extra_data', $resultPayload);
        $this->assertNull($resultPayload['extra_data']);
    }
}
