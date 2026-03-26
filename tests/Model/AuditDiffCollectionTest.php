<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Model;

use DH\Auditor\Model\AuditDiff;
use DH\Auditor\Model\AuditDiffCollection;
use DH\Auditor\Model\DiffKind;
use DH\Auditor\Model\TransactionType;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class AuditDiffCollectionTest extends TestCase
{
    // --- FieldChanges (INSERT / UPDATE) ---

    public function testEmptyDiffsProduceEmptyFieldChangesCollection(): void
    {
        $collection = AuditDiffCollection::fromRawDiffs([], TransactionType::Insert);

        $this->assertSame(DiffKind::FieldChanges, $collection->kind);
        $this->assertTrue($collection->kind->hasFieldDiffs());
        $this->assertCount(0, $collection);
        $this->assertTrue($collection->isEmpty);
        $this->assertNull($collection->entitySnapshot);
        $this->assertNull($collection->relationDescriptor);
        $this->assertSame([], $collection->fields());
    }

    public function testInsertDiffsProduceAuditDiffObjects(): void
    {
        $raw = [
            'email' => ['new' => 'john@example.com'],
            'fullname' => ['new' => 'John Doe'],
        ];
        $collection = AuditDiffCollection::fromRawDiffs($raw, TransactionType::Insert);

        $this->assertSame(DiffKind::FieldChanges, $collection->kind);
        $this->assertCount(2, $collection);
        $this->assertFalse($collection->isEmpty);
        $this->assertSame(['email', 'fullname'], $collection->fields());

        $this->assertTrue($collection->has('email'));
        $email = $collection->get('email');
        $this->assertInstanceOf(AuditDiff::class, $email);
        $this->assertSame('email', $email->field);
        $this->assertSame('john@example.com', $email->new);
        $this->assertFalse($email->hasOld);
    }

    public function testUpdateDiffsProduceAuditDiffObjectsWithOldValues(): void
    {
        $raw = [
            'email' => ['old' => 'old@example.com', 'new' => 'new@example.com'],
        ];
        $collection = AuditDiffCollection::fromRawDiffs($raw, TransactionType::Update);

        $this->assertSame(DiffKind::FieldChanges, $collection->kind);
        $this->assertCount(1, $collection);

        $email = $collection->get('email');
        $this->assertNotNull($email);
        $this->assertTrue($email->hasOld);
        $this->assertSame('old@example.com', $email->old);
        $this->assertSame('new@example.com', $email->new);
    }

    public function testIterationYieldsAuditDiffKeyedByFieldName(): void
    {
        $raw = ['email' => ['new' => 'john@example.com'], 'name' => ['new' => 'John']];
        $collection = AuditDiffCollection::fromRawDiffs($raw, TransactionType::Insert);

        $fields = [];
        foreach ($collection as $field => $diff) {
            $fields[] = $field;
            $this->assertInstanceOf(AuditDiff::class, $diff);
            $this->assertSame($field, $diff->field);
        }

        $this->assertSame(['email', 'name'], $fields);
    }

    public function testMetadataKeysAreSkipped(): void
    {
        $raw = [
            '@source' => ['id' => 1, 'class' => 'App\Entity\Author', 'label' => 'John', 'table' => 'author'],
            'email' => ['new' => 'john@example.com'],
        ];
        $collection = AuditDiffCollection::fromRawDiffs($raw, TransactionType::Insert);

        $this->assertCount(1, $collection);
        $this->assertTrue($collection->has('email'));
        $this->assertFalse($collection->has('@source'));
    }

    public function testGetReturnsNullForUnknownField(): void
    {
        $collection = AuditDiffCollection::fromRawDiffs(['email' => ['new' => 'x']], TransactionType::Insert);

        $this->assertNull($collection->get('unknown'));
        $this->assertFalse($collection->has('unknown'));
    }

    // --- EntityRemoval (REMOVE) ---

    public function testRemoveDiffsProduceEntityRemovalCollection(): void
    {
        $raw = [
            'class' => 'App\Entity\Author',
            'id' => 1,
            'label' => 'John Doe',
            'table' => 'author',
        ];
        $collection = AuditDiffCollection::fromRawDiffs($raw, TransactionType::Remove);

        $this->assertSame(DiffKind::EntityRemoval, $collection->kind);
        $this->assertTrue($collection->kind->isRemoval());
        $this->assertCount(0, $collection);
        $this->assertTrue($collection->isEmpty);
        $this->assertNull($collection->relationDescriptor);

        $snapshot = $collection->requireEntitySnapshot();
        $this->assertSame('App\Entity\Author', $snapshot->class);
        $this->assertSame(1, $snapshot->id);
        $this->assertSame('John Doe', $snapshot->label);
        $this->assertSame('author', $snapshot->table);
    }

    public function testRequireEntitySnapshotThrowsForNonRemovalCollection(): void
    {
        $collection = AuditDiffCollection::fromRawDiffs([], TransactionType::Insert);

        $this->expectException(\LogicException::class);
        $collection->requireEntitySnapshot();
    }

    // --- Relation (ASSOCIATE / DISSOCIATE) ---

    public function testAssociateDiffsProduceAssociateCollection(): void
    {
        $raw = [
            'is_owning_side' => false,
            'source' => [
                'class' => 'App\Entity\Author',
                'field' => 'posts',
                'id' => 1,
                'label' => 'John Doe',
                'table' => 'author',
            ],
            'target' => [
                'class' => 'App\Entity\Post',
                'field' => 'author',
                'id' => 1,
                'label' => 'First post',
                'table' => 'post',
            ],
        ];
        $collection = AuditDiffCollection::fromRawDiffs($raw, TransactionType::Associate);

        $this->assertSame(DiffKind::Associate, $collection->kind);
        $this->assertTrue($collection->kind->isRelation());
        $this->assertCount(0, $collection);
        $this->assertTrue($collection->isEmpty);
        $this->assertNull($collection->entitySnapshot);

        $rel = $collection->requireRelationDescriptor();
        $this->assertFalse($rel->isOwningSide);
        $this->assertNull($rel->pivotTable);

        $this->assertSame('App\Entity\Author', $rel->source->class);
        $this->assertSame('posts', $rel->source->field);
        $this->assertSame(1, $rel->source->id);

        $this->assertSame('App\Entity\Post', $rel->target->class);
        $this->assertSame('author', $rel->target->field);
    }

    public function testManyToManyAssociateDiffsIncludePivotTable(): void
    {
        $raw = [
            'is_owning_side' => true,
            'source' => ['class' => 'App\Entity\Post', 'field' => 'tags', 'id' => 1, 'label' => 'First post', 'table' => 'post'],
            'table' => 'post__tag',
            'target' => ['class' => 'App\Entity\Tag', 'field' => 'posts', 'id' => 2, 'label' => 'house', 'table' => 'tag'],
        ];
        $collection = AuditDiffCollection::fromRawDiffs($raw, TransactionType::Associate);

        $rel = $collection->requireRelationDescriptor();
        $this->assertTrue($rel->isOwningSide);
        $this->assertSame('post__tag', $rel->pivotTable);
    }

    public function testDissociateDiffsProduceDissociateCollection(): void
    {
        $raw = [
            'is_owning_side' => false,
            'source' => ['class' => 'App\Entity\Author', 'field' => 'posts', 'id' => 1, 'label' => 'John Doe', 'table' => 'author'],
            'target' => ['class' => 'App\Entity\Post', 'field' => 'author', 'id' => 1, 'label' => 'First post', 'table' => 'post'],
        ];
        $collection = AuditDiffCollection::fromRawDiffs($raw, TransactionType::Dissociate);

        $this->assertSame(DiffKind::Dissociate, $collection->kind);
        $this->assertTrue($collection->kind->isRelation());
        $this->assertNotNull($collection->requireRelationDescriptor());
    }

    public function testRequireRelationDescriptorThrowsForNonRelationCollection(): void
    {
        $collection = AuditDiffCollection::fromRawDiffs([], TransactionType::Insert);

        $this->expectException(\LogicException::class);
        $collection->requireRelationDescriptor();
    }

    public function testRelationEndpointWithNonStandardPkName(): void
    {
        $raw = [
            'is_owning_side' => true,
            'source' => ['class' => 'App\Entity\Post', 'field' => 'tags', 'uuid' => 'abc-123', 'label' => 'First post', 'table' => 'post', 'pkName' => 'uuid'],
            'target' => ['class' => 'App\Entity\Tag', 'field' => 'posts', 'id' => 2, 'label' => 'house', 'table' => 'tag'],
        ];
        $collection = AuditDiffCollection::fromRawDiffs($raw, TransactionType::Associate);

        $rel = $collection->requireRelationDescriptor();
        $this->assertSame('abc-123', $rel->source->id);
        $this->assertSame('uuid', $rel->source->pkName);
        $this->assertNull($rel->target->pkName);
    }
}
