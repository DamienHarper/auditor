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

        $entry = new Entry();
        $reflectionClass = new \ReflectionClass(Entry::class);
        foreach ($attributes as $name => $value) {
            $attribute = $reflectionClass->getProperty($name);
            $attribute->setAccessible(true);
            $attribute->setValue($entry, $value);
            $attribute->setAccessible(false);
        }

        $this->assertSame(1, $entry->getId(), 'Entry::getId() is ok.');
        $this->assertSame('type', $entry->getType(), 'Entry::getType() is ok.');
        $this->assertSame('1', $entry->getObjectId(), 'Entry::getObjectId() is ok.');
        $this->assertSame([], $entry->getDiffs(), 'Entry::getDiffs() is ok.');
        $this->assertSame(1, $entry->getUserId(), 'Entry::getUserId() is ok.');
        $this->assertSame('John Doe', $entry->getUsername(), 'Entry::getUsername() is ok.');
        $this->assertSame('Acme\User', $entry->getUserFqdn(), 'Entry::getUserFqdn() is ok.');
        $this->assertSame('main', $entry->getUserFirewall(), 'Entry::getUserFirewall() is ok.');
        $this->assertSame('1.2.3.4', $entry->getIp(), 'Entry::getIp() is ok.');
        $this->assertSame($attributes['created_at'], $entry->getCreatedAt(), 'Entry::getCreatedAt() is ok.');
    }

    public function testUndefinedUser(): void
    {
        $entry = new Entry();

        $this->assertNull($entry->getUserId(), 'Entry::getUserId() is ok with undefined user.');
        $this->assertNull($entry->getUsername(), 'Entry::getUsername() is ok with undefined user.');
    }

    public function testGetDiffsReturnsAnArray(): void
    {
        $entry = new Entry();
        $reflectionClass = new \ReflectionClass(Entry::class);

        $attribute = $reflectionClass->getProperty('diffs');
        $attribute->setAccessible(true);
        $attribute->setValue($entry, '{}');
        $attribute->setAccessible(false);

        $this->assertIsArray($entry->getDiffs(), 'Entry::getDiffs() returns an array.');
    }
}
