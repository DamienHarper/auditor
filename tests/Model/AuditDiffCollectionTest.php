<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Model;

use DH\Auditor\Model\AuditDiff;
use DH\Auditor\Model\AuditDiffCollection;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class AuditDiffCollectionTest extends TestCase
{
    public function testIsEmpty(): void
    {
        $collection = new AuditDiffCollection([]);

        $this->assertCount(0, $collection);
    }

    public function testCount(): void
    {
        $collection = new AuditDiffCollection([
            'email' => ['old' => 'a@a.com', 'new' => 'b@b.com'],
            'name' => ['old' => 'Alice', 'new' => 'Bob'],
        ]);

        $this->assertCount(2, $collection);
    }

    public function testIsIterable(): void
    {
        $collection = new AuditDiffCollection([
            'email' => ['old' => 'a@a.com', 'new' => 'b@b.com'],
        ]);

        foreach ($collection as $diff) {
            $this->assertInstanceOf(AuditDiff::class, $diff);
            $this->assertSame('email', $diff->getField());
            $this->assertSame('a@a.com', $diff->getOldValue());
            $this->assertSame('b@b.com', $diff->getNewValue());
        }
    }

    public function testGetField(): void
    {
        $collection = new AuditDiffCollection([
            'email' => ['old' => 'a@a.com', 'new' => 'b@b.com'],
            'name' => ['old' => 'Alice', 'new' => 'Bob'],
        ]);

        $diff = $collection->getField('name');
        $this->assertInstanceOf(AuditDiff::class, $diff);
        $this->assertSame('name', $diff->getField());
        $this->assertSame('Alice', $diff->getOldValue());
        $this->assertSame('Bob', $diff->getNewValue());
    }

    public function testGetFieldReturnsNullWhenNotFound(): void
    {
        $collection = new AuditDiffCollection([
            'email' => ['old' => 'a@a.com', 'new' => 'b@b.com'],
        ]);

        $this->assertNull($collection->getField('nonexistent'));
    }

    public function testAdded(): void
    {
        $collection = new AuditDiffCollection([
            'email' => ['old' => null, 'new' => 'new@example.com'],    // added
            'name' => ['old' => 'Alice', 'new' => 'Bob'],              // changed
            'phone' => ['old' => '555-1234', 'new' => null],           // removed
        ]);

        $added = $collection->added();
        $this->assertCount(1, $added);
        $this->assertNotNull($added->getField('email'));
        $this->assertNull($added->getField('name'));
    }

    public function testRemoved(): void
    {
        $collection = new AuditDiffCollection([
            'email' => ['old' => null, 'new' => 'new@example.com'],    // added
            'name' => ['old' => 'Alice', 'new' => 'Bob'],              // changed
            'phone' => ['old' => '555-1234', 'new' => null],           // removed
        ]);

        $removed = $collection->removed();
        $this->assertCount(1, $removed);
        $this->assertNotNull($removed->getField('phone'));
        $this->assertNull($removed->getField('name'));
    }

    public function testChanged(): void
    {
        $collection = new AuditDiffCollection([
            'email' => ['old' => null, 'new' => 'new@example.com'],    // added
            'name' => ['old' => 'Alice', 'new' => 'Bob'],              // changed
            'phone' => ['old' => '555-1234', 'new' => null],           // removed
        ]);

        $changed = $collection->changed();
        $this->assertCount(1, $changed);
        $this->assertNotNull($changed->getField('name'));
        $this->assertNull($changed->getField('email'));
    }

    public function testLegacyInsertWithoutOldKey(): void
    {
        // schema_version = 1 INSERT: no 'old' key — treated as added (old = null)
        $collection = new AuditDiffCollection([
            'name' => ['new' => 'Alice'],
        ]);

        $diff = $collection->getField('name');
        $this->assertNotNull($diff);
        $this->assertNull($diff->getOldValue());
        $this->assertSame('Alice', $diff->getNewValue());
        $this->assertTrue($diff->wasAdded());
    }

    public function testLegacyRemoveShapeYieldsEmptyCollection(): void
    {
        // schema_version = 1 REMOVE: flat {id, class, label, table} — not field-level diffs
        $collection = new AuditDiffCollection([
            'id' => '1',
            'class' => 'App\Entity\Foo',
            'label' => 'Foo label',
            'table' => 'foo',
        ]);

        $this->assertCount(0, $collection);
    }

    public function testFilteredCollectionIsIterable(): void
    {
        $collection = new AuditDiffCollection([
            'a' => ['old' => null, 'new' => 'x'],
            'b' => ['old' => '1', 'new' => '2'],
        ]);

        $added = $collection->added();
        $fields = [];
        foreach ($added as $diff) {
            $fields[] = $diff->getField();
        }

        $this->assertSame(['a'], $fields);
    }
}
