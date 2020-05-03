<?php

namespace DH\Auditor\Tests\Model;

use DH\Auditor\Model\Entry;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class EntryTest extends TestCase
{
    public function testAccessors(): void
    {
        $entry = new Entry();
        $entry->id = 1;
        $entry->type = 'type';
        $entry->object_id = '1';
        $entry->diffs = '{}';
        $entry->blame_id = 1;
        $entry->blame_user = 'John Doe';
        $entry->blame_user_fqdn = 'Acme\User';
        $entry->blame_user_firewall = 'main';
        $entry->ip = '1.2.3.4';
        $entry->created_at = 'now';

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

        self::assertSame(1, $entry->id, 'id accessor is ok.');
        self::assertSame('type', $entry->type, 'type accessor is ok.');
        self::assertSame('1', $entry->object_id, 'object_id accessor is ok.');
        self::assertSame('{}', $entry->diffs, 'diffs accessor is ok.');
        self::assertSame(1, $entry->blame_id, 'blame_id accessor is ok.');
        self::assertSame('John Doe', $entry->blame_user, 'blame_user accessor is ok.');
        self::assertSame('Acme\User', $entry->blame_user_fqdn, 'blame_user_fqdn accessor is ok.');
        self::assertSame('main', $entry->blame_user_firewall, 'blame_user_firewall accessor is ok.');
        self::assertSame('1.2.3.4', $entry->ip, 'ip accessor is ok.');
        self::assertSame('now', $entry->created_at, 'created_at accessor is ok.');
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
        $entry->diffs = '{}';

        self::assertIsArray($entry->getDiffs(), 'Entry::getDiffs() returns an array.');
    }
}
