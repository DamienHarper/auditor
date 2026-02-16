<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Model;

use DH\Auditor\Model\Entry;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class EntryTest extends TestCase
{
    public function testAccessors(): void
    {
        $attributes = [
            'id' => 1,
            'type' => 'type',
            'object_id' => '1',
            'diffs' => '{}',
            'blame_id' => 1,
            'blame_user' => 'John Doe',
            'blame_user_fqdn' => 'Acme\User',
            'blame_user_firewall' => 'main',
            'ip' => '1.2.3.4',
            'created_at' => new \DateTimeImmutable(),
        ];

        $entry = Entry::fromArray($attributes);

        $this->assertSame(1, $entry->id, 'Entry::id is ok.');
        $this->assertSame('type', $entry->type, 'Entry::type is ok.');
        $this->assertSame('1', $entry->objectId, 'Entry::objectId is ok.');
        $this->assertSame([], $entry->getDiffs(), 'Entry::getDiffs() is ok.');
        $this->assertSame(1, $entry->userId, 'Entry::userId is ok.');
        $this->assertSame('John Doe', $entry->username, 'Entry::username is ok.');
        $this->assertSame('Acme\User', $entry->userFqdn, 'Entry::userFqdn is ok.');
        $this->assertSame('main', $entry->userFirewall, 'Entry::userFirewall is ok.');
        $this->assertSame('1.2.3.4', $entry->ip, 'Entry::ip is ok.');
        $this->assertSame($attributes['created_at'], $entry->createdAt, 'Entry::createdAt is ok.');
    }

    public function testUndefinedUser(): void
    {
        $entry = new Entry();

        $this->assertNull($entry->userId, 'Entry::userId is ok with undefined user.');
        $this->assertNull($entry->username, 'Entry::username is ok with undefined user.');
    }

    public function testGetDiffsReturnsAnArray(): void
    {
        $entry = Entry::fromArray(['diffs' => '{}']);

        $this->assertIsArray($entry->getDiffs(), 'Entry::getDiffs() returns an array.');
    }

    public function testGetExtraDataReturnsNullWhenEmpty(): void
    {
        $entry = Entry::fromArray(['extra_data' => null]);

        $this->assertNull($entry->getExtraData(), 'Entry::getExtraData() returns null when empty.');
        $this->assertNull($entry->extraData, 'Entry::extraData virtual property returns null when empty.');
    }

    public function testGetExtraDataReturnsArray(): void
    {
        $entry = Entry::fromArray(['extra_data' => '{"key":"value"}']);

        $this->assertSame(['key' => 'value'], $entry->getExtraData(), 'Entry::getExtraData() returns an array.');
        $this->assertSame(['key' => 'value'], $entry->extraData, 'Entry::extraData virtual property returns an array.');
    }

    public function testGetExtraDataWithNestedData(): void
    {
        $data = ['department' => 'IT', 'metadata' => ['level' => 3, 'tags' => ['admin', 'user']]];
        $entry = Entry::fromArray(['extra_data' => json_encode($data)]);

        $this->assertSame($data, $entry->getExtraData(), 'Entry::getExtraData() handles nested data.');
    }
}
