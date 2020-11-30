<?php

namespace DH\Auditor\Tests\Model;

use DH\Auditor\Model\Entry;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
final class EntryTest extends TestCase
{
    public function testAccessors(): void
    {
        $entry = new Entry();
        $reflectionClass = new ReflectionClass(Entry::class);

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
            'created_at' => 'now',
        ];
        foreach ($attributes as $name => $value) {
            $attribute = $reflectionClass->getProperty($name);
            $attribute->setAccessible(true);
            $attribute->setValue($entry, $value);
            $attribute->setAccessible(false);
        }

        self::assertSame(1, $entry->getId(), 'Entry::getId() is ok.');
        self::assertSame('type', $entry->getType(), 'Entry::getType() is ok.');
        self::assertSame('1', $entry->getObjectId(), 'Entry::getObjectId() is ok.');
        self::assertSame([], $entry->getDiffs(), 'Entry::getDiffs() is ok.');
        self::assertSame(1, $entry->getUserId(), 'Entry::getUserId() is ok.');
        self::assertSame('John Doe', $entry->getUsername(), 'Entry::getUsername() is ok.');
        self::assertSame('Acme\User', $entry->getUserFqdn(), 'Entry::getUserFqdn() is ok.');
        self::assertSame('main', $entry->getUserFirewall(), 'Entry::getUserFirewall() is ok.');
        self::assertSame('1.2.3.4', $entry->getIp(), 'Entry::getIp() is ok.');
        self::assertSame('now', $entry->getCreatedAt(), 'Entry::getCreatedAt() is ok.');
    }

    public function testUndefinedUser(): void
    {
        $entry = new Entry();

        self::assertNull($entry->getUserId(), 'Entry::getUserId() is ok with undefined user.');
        self::assertNull($entry->getUsername(), 'Entry::getUsername() is ok with undefined user.');
    }

    public function testGetDiffsReturnsAnArray(): void
    {
        $entry = new Entry();
        $reflectionClass = new ReflectionClass(Entry::class);

        $attribute = $reflectionClass->getProperty('diffs');
        $attribute->setAccessible(true);
        $attribute->setValue($entry, '{}');
        $attribute->setAccessible(false);

        self::assertIsArray($entry->getDiffs(), 'Entry::getDiffs() returns an array.');
    }
}
