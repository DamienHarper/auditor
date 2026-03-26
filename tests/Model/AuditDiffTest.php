<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Model;

use DH\Auditor\Model\AuditDiff;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class AuditDiffTest extends TestCase
{
    public function testInsertFieldHasNoOldValue(): void
    {
        $diff = AuditDiff::fromRaw('email', ['new' => 'john@example.com']);

        $this->assertSame('email', $diff->field);
        $this->assertSame('john@example.com', $diff->new);
        $this->assertFalse($diff->hasOld);
        $this->assertNull($diff->old);
    }

    public function testUpdateFieldHasOldAndNewValues(): void
    {
        $diff = AuditDiff::fromRaw('email', ['old' => 'old@example.com', 'new' => 'new@example.com']);

        $this->assertSame('email', $diff->field);
        $this->assertSame('old@example.com', $diff->old);
        $this->assertSame('new@example.com', $diff->new);
        $this->assertTrue($diff->hasOld);
    }

    public function testOldValueCanBeNull(): void
    {
        $diff = AuditDiff::fromRaw('nickname', ['old' => null, 'new' => 'Johnny']);

        $this->assertTrue($diff->hasOld, 'hasOld must be true even when old value is null.');
        $this->assertNull($diff->old);
        $this->assertSame('Johnny', $diff->new);
    }

    public function testNewValueCanBeNull(): void
    {
        $diff = AuditDiff::fromRaw('nickname', ['old' => 'Johnny', 'new' => null]);

        $this->assertTrue($diff->hasOld);
        $this->assertSame('Johnny', $diff->old);
        $this->assertNull($diff->new);
    }

    public function testIsNotEnrichedWithScalarValues(): void
    {
        $diff = AuditDiff::fromRaw('email', ['old' => 'a@b.com', 'new' => 'c@d.com']);

        $this->assertFalse($diff->isEnriched);
        $this->assertSame('a@b.com', $diff->oldRawValue);
        $this->assertSame('c@d.com', $diff->newRawValue);
        $this->assertNull($diff->oldLabel);
        $this->assertNull($diff->newLabel);
    }

    public function testIsEnrichedWithDiffLabelValues(): void
    {
        $diff = AuditDiff::fromRaw('category', [
            'old' => ['label' => 'Books', 'value' => 1],
            'new' => ['label' => 'Electronics', 'value' => 2],
        ]);

        $this->assertTrue($diff->isEnriched);
        $this->assertSame(1, $diff->oldRawValue);
        $this->assertSame(2, $diff->newRawValue);
        $this->assertSame('Books', $diff->oldLabel);
        $this->assertSame('Electronics', $diff->newLabel);
    }

    public function testIsEnrichedWithOnlyNewEnrichedValue(): void
    {
        $diff = AuditDiff::fromRaw('category', [
            'new' => ['label' => 'Books', 'value' => 1],
        ]);

        $this->assertTrue($diff->isEnriched);
        $this->assertSame(1, $diff->newRawValue);
        $this->assertSame('Books', $diff->newLabel);
        $this->assertNull($diff->oldLabel);
        $this->assertNull($diff->oldRawValue);
    }

    public function testRelationDescriptorValueIsNotEnriched(): void
    {
        // ManyToOne relation update — value is a descriptor array, not a DiffLabel
        $descriptor = ['id' => 42, 'class' => 'App\Entity\Author', 'label' => 'Jane', 'table' => 'author'];
        $diff = AuditDiff::fromRaw('author', ['old' => $descriptor, 'new' => ['id' => 99, 'class' => 'App\Entity\Author', 'label' => 'John', 'table' => 'author']]);

        // Relation descriptors have 'id' and 'class' but NOT 'label'+'value' — not enriched
        $this->assertFalse($diff->isEnriched);
        $this->assertSame($descriptor, $diff->oldRawValue);
    }
}
