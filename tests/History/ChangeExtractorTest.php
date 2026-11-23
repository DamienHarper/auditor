<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\History;

use DH\Auditor\History\Change;
use DH\Auditor\History\ChangeExtractor;
use DH\Auditor\History\ChangeKind;
use DH\Auditor\Model\Entry;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class ChangeExtractorTest extends TestCase
{
    public function testCreation(): void
    {
        $entry = EntryFactory::entry('insert', 'App\Post', '1', ['changes' => ['title' => ['new' => 'Hello'], 'status' => ['new' => 'draft']]], label: 'Hello');

        $changes = new ChangeExtractor()->extract($entry);

        $this->assertCount(1, $changes);
        $this->assertSame(ChangeKind::Created, $changes[0]->kind);
        $this->assertSame('App\Post', $changes[0]->entity);
        $this->assertSame('1', $changes[0]->objectId);
        $this->assertSame('Hello', $changes[0]->objectLabel);
        $this->assertNull($changes[0]->field);
        $this->assertSame(['status' => 'draft', 'title' => 'Hello'], $changes[0]->new);
        $this->assertSame($entry, $changes[0]->entry);
    }

    public function testSnapshot(): void
    {
        $entry = EntryFactory::entry('insert', 'App\Post', '1', ['snapshot' => true, 'changes' => ['title' => ['new' => 'Hello']]]);

        $this->assertSame(ChangeKind::Snapshot, new ChangeExtractor()->extract($entry)[0]->kind);
    }

    public function testDeletion(): void
    {
        $withSnapshot = EntryFactory::entry('remove', 'App\Post', '1', ['changes' => ['title' => ['old' => 'Hello']]]);
        $withoutSnapshot = EntryFactory::entry('remove', 'App\Post', '2', []);

        $change = new ChangeExtractor()->extract($withSnapshot)[0];
        $this->assertSame(ChangeKind::Deleted, $change->kind);
        $this->assertSame(['title' => 'Hello'], $change->old);

        $change = new ChangeExtractor()->extract($withoutSnapshot)[0];
        $this->assertSame(ChangeKind::Deleted, $change->kind);
        $this->assertSame([], $change->old);
        $this->assertSame('App\Post#2', $change->objectLabel);
    }

    public function testFieldChanges(): void
    {
        $entry = EntryFactory::entry('update', 'App\Post', '1', ['changes' => [
            'title' => ['old' => 'Hello', 'new' => 'Hi'],
            'summary' => ['old' => null, 'new' => 'Short'],
            'body' => ['old' => 'Long', 'new' => null],
            'status' => ['old' => ['value' => 1, 'label' => 'Draft'], 'new' => ['value' => 2, 'label' => 'Published']],
            'author' => ['old' => null, 'new' => ['id' => 3, 'class' => 'App\User', 'label' => 'alice', 'table' => 'user']],
        ]]);

        $changes = new ChangeExtractor()->extract($entry);
        $byField = array_column(array_map(static fn (Change $c): array => ['field' => $c->field, 'change' => $c], $changes), 'change', 'field');

        $this->assertSame(['author', 'body', 'status', 'summary', 'title'], array_keys($byField));
        $this->assertSame(ChangeKind::Changed, $byField['title']->kind);
        $this->assertSame('Hello', $byField['title']->old);
        $this->assertSame('Hi', $byField['title']->new);
        $this->assertSame(ChangeKind::Set, $byField['summary']->kind);
        $this->assertSame(ChangeKind::Cleared, $byField['body']->kind);
        $this->assertSame(ChangeKind::Changed, $byField['status']->kind);
        $this->assertSame(1, $byField['status']->old);
        $this->assertSame('Draft', $byField['status']->oldLabel);
        $this->assertSame(2, $byField['status']->new);
        $this->assertSame('Published', $byField['status']->newLabel);
        $this->assertSame(ChangeKind::Set, $byField['author']->kind);
        $this->assertSame('alice', $byField['author']->newLabel);
        $this->assertSame(3, $byField['author']->new['id']);
    }

    public function testJsonDeepDiff(): void
    {
        $entry = EntryFactory::entry('update', 'App\Post', '1', [
            'changes' => ['settings' => ['theme' => ['old' => 'dark', 'new' => 'light']]],
            'deep_diffs' => ['settings'],
        ]);

        $change = new ChangeExtractor()->extract($entry)[0];

        $this->assertSame(ChangeKind::Json, $change->kind);
        $this->assertSame('settings', $change->field);
        $this->assertSame(['theme' => ['old' => 'dark', 'new' => 'light']], $change->new);
    }

    public function testCollectionChanges(): void
    {
        $target = ['id' => 7, 'class' => 'App\Tag', 'label' => 'symfony', 'table' => 'tag', 'field' => 'posts'];
        $added = EntryFactory::association('associate', 'App\Post', '1', 'tags', $target);
        $removed = EntryFactory::association('dissociate', 'App\Post', '1', 'tags', $target);

        $change = new ChangeExtractor()->extract($added)[0];
        $this->assertSame(ChangeKind::Added, $change->kind);
        $this->assertSame('tags', $change->field);
        $this->assertSame('symfony', $change->newLabel);
        $this->assertSame('App\Tag', $change->getTargetClass());
        $this->assertSame('7', $change->getTargetId());

        $change = new ChangeExtractor()->extract($removed)[0];
        $this->assertSame(ChangeKind::Removed, $change->kind);
        $this->assertSame('symfony', $change->oldLabel);
        $this->assertSame('7', $change->getTargetId());
    }

    public function testMirrorEntriesAreKeptOnce(): void
    {
        $owning = EntryFactory::association('associate', 'App\Post', '1', 'tags', ['id' => 7, 'class' => 'App\Tag', 'label' => 'symfony', 'table' => 'tag', 'field' => 'posts']);
        $inverse = EntryFactory::association('associate', 'App\Tag', '7', 'posts', ['id' => 1, 'class' => 'App\Post', 'label' => 'Hello', 'table' => 'post', 'field' => 'tags'], owning: false);
        $other = EntryFactory::association('associate', 'App\Post', '1', 'tags', ['id' => 8, 'class' => 'App\Tag', 'label' => 'audit', 'table' => 'tag', 'field' => 'posts']);

        $changes = new ChangeExtractor()->extractAll([$owning, $inverse, $other]);

        $this->assertCount(2, $changes);
        $this->assertSame($owning, $changes[0]->entry);
        $this->assertSame($other, $changes[1]->entry);
    }

    public function testFieldList(): void
    {
        $update = EntryFactory::entry('update', 'App\Post', '1', ['changes' => [
            'title' => ['old' => 'a', 'new' => 'b'],
            'updatedAt' => ['old' => 'x', 'new' => 'y'],
            'status' => ['old' => 'draft', 'new' => 'published'],
        ]]);
        $insert = EntryFactory::entry('insert', 'App\Post', '1', ['changes' => ['title' => ['new' => 'a'], 'updatedAt' => ['new' => 'x']]]);
        $tag = EntryFactory::association('associate', 'App\Post', '1', 'tags', ['id' => 7, 'class' => 'App\Tag', 'label' => 'symfony', 'table' => 'tag']);
        $comment = EntryFactory::entry('update', 'App\Comment', '1', ['changes' => ['body' => ['old' => 'a', 'new' => 'b']]]);

        $extractor = new ChangeExtractor(['App\Post' => ['status', 'title']]);

        $this->assertSame(['status', 'title'], array_map(static fn (Change $c): ?string => $c->field, $extractor->extract($update)));
        $this->assertSame(['title' => 'a'], $extractor->extract($insert)[0]->new);
        $this->assertSame([], $extractor->extract($tag));
        $this->assertCount(1, $extractor->extract($comment), 'Entities without a field list keep every field.');
    }

    public function testLegacyEntry(): void
    {
        $entry = Entry::fromArray([
            'schema_version' => 1,
            'type' => 'update',
            'object_id' => '1',
            'diffs' => json_encode(['@source' => ['class' => 'App\Post', 'label' => 'Hello'], 'title' => ['old' => 'a', 'new' => 'b']], JSON_THROW_ON_ERROR),
            'entity' => 'App\Post',
        ]);

        $changes = new ChangeExtractor()->extract($entry);

        $this->assertCount(1, $changes);
        $this->assertSame(ChangeKind::Changed, $changes[0]->kind);
        $this->assertSame('App\Post', $changes[0]->entity);
        $this->assertSame('title', $changes[0]->field);
    }

    public function testEntityOfFallsBackToTheDiffSource(): void
    {
        $entry = Entry::fromArray([
            'schema_version' => 2,
            'type' => 'update',
            'object_id' => '1',
            'diffs' => json_encode(['source' => ['id' => 1, 'class' => 'App\Post', 'label' => 'x'], 'changes' => []], JSON_THROW_ON_ERROR),
        ]);

        $this->assertSame('App\Post', ChangeExtractor::entityOf($entry));
    }

    public function testCreationValuesAreRawAndKeepTheirLabels(): void
    {
        $entry = EntryFactory::entry('insert', 'App\Post', '1', [
            'changes' => [
                'status' => ['new' => ['value' => 'draft', 'label' => 'Draft']],
                'author' => ['new' => ['id' => 7, 'class' => 'App\Author', 'label' => 'John', 'table' => 'author']],
                'settings' => ['theme' => ['new' => 'dark'], 'layout' => ['columns' => ['new' => 2]]],
                'title' => ['new' => 'Hello'],
            ],
            'deep_diffs' => ['settings'],
        ]);

        $change = new ChangeExtractor()->extract($entry)[0];

        $this->assertSame('draft', $change->new['status'], 'A #[DiffLabel] value is read as its raw value.');
        $this->assertSame(['layout' => ['columns' => 2], 'theme' => 'dark'], $change->new['settings'], 'A JSON field is rebuilt from its deep diff.');
        $this->assertSame('Hello', $change->new['title']);
        $this->assertSame(['author' => 'John', 'status' => 'Draft'], $change->labels, 'Fields are sorted by name.');
        $this->assertSame('draft', $change->getValue('status'));
        $this->assertSame('Draft', $change->getLabel('status'));
        $this->assertNull($change->getLabel('title'));
        $this->assertNull($change->getValue('unknown'));
    }

    public function testDeletionValuesAreRaw(): void
    {
        $entry = EntryFactory::entry('remove', 'App\Post', '1', [
            'changes' => [
                'status' => ['old' => ['value' => 'draft', 'label' => 'Draft']],
                'settings' => ['theme' => ['old' => 'dark']],
            ],
            'deep_diffs' => ['settings'],
        ]);

        $change = new ChangeExtractor()->extract($entry)[0];

        $this->assertSame(['settings' => ['theme' => 'dark'], 'status' => 'draft'], $change->old);
        $this->assertSame('draft', $change->getValue('status'));
        $this->assertSame(['status' => 'Draft'], $change->labels);
    }

    public function testValueOfAFieldChange(): void
    {
        $entry = EntryFactory::entry('update', 'App\Post', '1', ['changes' => ['status' => ['old' => 'draft', 'new' => ['value' => 'published', 'label' => 'Published']]]]);

        $change = new ChangeExtractor()->extract($entry)[0];

        $this->assertSame('published', $change->getValue('status'));
        $this->assertSame('Published', $change->getLabel('status'));
        $this->assertNull($change->getValue('title'));
        $this->assertNull($change->getTargetClass());

        $association = EntryFactory::entry('update', 'App\Post', '1', ['changes' => ['author' => [
            'old' => ['id' => 1, 'class' => 'App\Author', 'label' => 'John', 'table' => 'author'],
            'new' => ['id' => 2, 'class' => 'App\Author', 'label' => 'Jane', 'table' => 'author'],
        ]]]);
        $change = new ChangeExtractor()->extract($association)[0];

        $this->assertSame(ChangeKind::Changed, $change->kind);
        $this->assertNull($change->getTargetClass(), 'Only collection changes have a target, not a changed association.');
        $this->assertSame('Jane', $change->getLabel('author'));
    }

    public function testFieldChangesAreNotMirrors(): void
    {
        $first = EntryFactory::entry('update', 'App\Post', '1', ['changes' => ['title' => ['old' => 'a', 'new' => 'b']]], 'T1');
        $second = EntryFactory::entry('update', 'App\Post', '1', ['changes' => ['title' => ['old' => 'b', 'new' => 'c']]], 'T1');

        $this->assertCount(2, new ChangeExtractor()->extractAll([$first, $second]), 'Two changes of a field are both kept.');
    }
}
