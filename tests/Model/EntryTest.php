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
        $blame = json_encode([
            'username' => 'John Doe',
            'user_fqdn' => 'Acme\User',
            'user_firewall' => 'main',
            'ip' => '1.2.3.4',
        ]);

        $attributes = [
            'id' => 1,
            'schema_version' => 2,
            'type' => 'type',
            'object_id' => '1',
            'diffs' => '{}',
            'blame_id' => 1,
            'blame' => $blame,
            'created_at' => new \DateTimeImmutable(),
        ];

        $entry = Entry::fromArray($attributes);

        $this->assertSame(1, $entry->id, 'Entry::id is ok.');
        $this->assertSame(2, $entry->schemaVersion, 'Entry::schemaVersion is ok.');
        $this->assertSame('type', $entry->type, 'Entry::type is ok.');
        $this->assertSame('1', $entry->objectId, 'Entry::objectId is ok.');
        $this->assertSame([], $entry->getDiffs(), 'Entry::getDiffs() returns empty array for empty diffs.');
        $this->assertSame(1, $entry->userId, 'Entry::userId is ok.');
        $this->assertSame('John Doe', $entry->blame['username'], 'Entry::blame[username] is ok.');
        $this->assertSame('Acme\User', $entry->blame['user_fqdn'], 'Entry::blame[user_fqdn] is ok.');
        $this->assertSame('main', $entry->blame['user_firewall'], 'Entry::blame[user_firewall] is ok.');
        $this->assertSame('1.2.3.4', $entry->blame['ip'], 'Entry::blame[ip] is ok.');
        $this->assertSame($attributes['created_at'], $entry->createdAt, 'Entry::createdAt is ok.');
    }

    public function testUndefinedUser(): void
    {
        $entry = new Entry();

        $this->assertNull($entry->userId, 'Entry::userId is ok with undefined user.');
        $this->assertNull($entry->blame, 'Entry::blame is null with undefined user.');
    }

    public function testSchemaVersionDefaultsToOne(): void
    {
        $entry = new Entry();

        $this->assertSame(1, $entry->schemaVersion, 'Entry::schemaVersion defaults to 1.');
    }

    public function testGetDiffsLegacyFormat(): void
    {
        // schema_version = 1: old format, returns raw decoded array (minus @source)
        $entry = Entry::fromArray([
            'schema_version' => 1,
            'diffs' => json_encode([
                '@source' => ['id' => 1, 'class' => 'App\Entity\Foo', 'label' => 'Foo', 'table' => 'foo'],
                'name' => ['new' => 'Alice'],
            ]),
        ]);

        $diffs = $entry->getDiffs();
        $this->assertArrayNotHasKey('@source', $diffs, 'getDiffs() strips @source in legacy format.');
        $this->assertArrayHasKey('name', $diffs, 'getDiffs() returns field diffs in legacy format.');
    }

    public function testGetDiffsNewFormat(): void
    {
        // schema_version = 2: unified envelope, returns 'changes' sub-array
        $entry = Entry::fromArray([
            'schema_version' => 2,
            'diffs' => json_encode([
                'source' => ['id' => '1', 'class' => 'App\Entity\Foo', 'label' => 'Foo', 'table' => 'foo'],
                'changes' => [
                    'name' => ['old' => 'Alice', 'new' => 'Bob'],
                ],
            ]),
        ]);

        $diffs = $entry->getDiffs();
        $this->assertSame(['name' => ['old' => 'Alice', 'new' => 'Bob']], $diffs);
    }

    public function testGetDiffSource(): void
    {
        $source = ['id' => '1', 'class' => 'App\Entity\Foo', 'label' => 'Foo', 'table' => 'foo'];

        $entry = Entry::fromArray([
            'schema_version' => 2,
            'diffs' => json_encode(['source' => $source, 'changes' => []]),
        ]);

        $this->assertSame($source, $entry->getDiffSource());
    }

    public function testGetDiffSourceReturnsNullForLegacyEntry(): void
    {
        $entry = Entry::fromArray([
            'schema_version' => 1,
            'diffs' => '{"name": {"new": "Alice"}}',
        ]);

        $this->assertNull($entry->getDiffSource());
    }

    public function testGetDiffTarget(): void
    {
        $target = ['id' => '5', 'class' => 'App\Entity\Tag', 'label' => 'php', 'table' => 'tag', 'field' => 'posts'];

        $entry = Entry::fromArray([
            'schema_version' => 2,
            'diffs' => json_encode([
                'source' => ['id' => '1', 'class' => 'App\Entity\Post', 'label' => 'First post', 'table' => 'post', 'field' => 'tags'],
                'target' => $target,
                'is_owning_side' => true,
            ]),
        ]);

        $this->assertSame($target, $entry->getDiffTarget());
    }

    public function testGetDiffTargetReturnsNullForLegacyEntry(): void
    {
        $entry = Entry::fromArray([
            'schema_version' => 1,
            'diffs' => '{"source": {}, "target": {}}',
        ]);

        $this->assertNull($entry->getDiffTarget());
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

    public function testTransactionId(): void
    {
        $entry = Entry::fromArray(['transaction_id' => '01HXYZ1234567890ABCDEFGHIJ']);

        $this->assertSame('01HXYZ1234567890ABCDEFGHIJ', $entry->transactionId);
    }

    public function testTransactionIdIsNullByDefault(): void
    {
        $entry = new Entry();

        $this->assertNull($entry->transactionId);
    }
}
