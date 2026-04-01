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
    public function testGetField(): void
    {
        $diff = new AuditDiff('email', 'old@example.com', 'new@example.com');

        $this->assertSame('email', $diff->getField());
    }

    public function testGetOldValue(): void
    {
        $diff = new AuditDiff('email', 'old@example.com', 'new@example.com');

        $this->assertSame('old@example.com', $diff->getOldValue());
    }

    public function testGetNewValue(): void
    {
        $diff = new AuditDiff('email', 'old@example.com', 'new@example.com');

        $this->assertSame('new@example.com', $diff->getNewValue());
    }

    public function testWasAdded(): void
    {
        $diff = new AuditDiff('email', null, 'new@example.com');

        $this->assertTrue($diff->wasAdded());
        $this->assertFalse($diff->wasRemoved());
    }

    public function testWasAddedIsFalseWhenBothValuesSet(): void
    {
        $diff = new AuditDiff('email', 'old@example.com', 'new@example.com');

        $this->assertFalse($diff->wasAdded());
    }

    public function testWasRemoved(): void
    {
        $diff = new AuditDiff('email', 'old@example.com', null);

        $this->assertTrue($diff->wasRemoved());
        $this->assertFalse($diff->wasAdded());
    }

    public function testWasRemovedIsFalseWhenBothValuesSet(): void
    {
        $diff = new AuditDiff('email', 'old@example.com', 'new@example.com');

        $this->assertFalse($diff->wasRemoved());
    }

    public function testIsRelationDetectsRelationInOldValue(): void
    {
        $relation = ['id' => '42', 'class' => 'App\Entity\Author', 'label' => 'Dark Vador', 'table' => 'author'];
        $diff = new AuditDiff('author', $relation, null);

        $this->assertTrue($diff->isRelation());
    }

    public function testIsRelationDetectsRelationInNewValue(): void
    {
        $relation = ['id' => '42', 'class' => 'App\Entity\Author', 'label' => 'Dark Vador', 'table' => 'author'];
        $diff = new AuditDiff('author', null, $relation);

        $this->assertTrue($diff->isRelation());
    }

    public function testIsRelationIsFalseForScalarValues(): void
    {
        $diff = new AuditDiff('email', 'old@example.com', 'new@example.com');

        $this->assertFalse($diff->isRelation());
    }

    public function testIsRelationIsFalseForNullValues(): void
    {
        $diff = new AuditDiff('field', null, null);

        $this->assertFalse($diff->isRelation());
        $this->assertFalse($diff->wasAdded());
        $this->assertFalse($diff->wasRemoved());
    }

    public function testIsRelationWhenBothSidesAreRelationDescriptors(): void
    {
        $old = ['id' => '1', 'class' => 'App\Entity\Author', 'label' => 'Alice', 'table' => 'author'];
        $new = ['id' => '2', 'class' => 'App\Entity\Author', 'label' => 'Bob', 'table' => 'author'];
        $diff = new AuditDiff('author', $old, $new);

        $this->assertTrue($diff->isRelation());
        $this->assertFalse($diff->wasAdded());
        $this->assertFalse($diff->wasRemoved());
    }

    public function testIsRelationIsFalseForIncompleteArray(): void
    {
        // Missing 'table' key — not a relation descriptor
        $diff = new AuditDiff('field', ['id' => '1', 'class' => 'Foo', 'label' => 'bar'], null);

        $this->assertFalse($diff->isRelation());
    }
}
