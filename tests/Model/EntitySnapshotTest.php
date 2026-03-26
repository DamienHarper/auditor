<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Model;

use DH\Auditor\Model\EntitySnapshot;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class EntitySnapshotTest extends TestCase
{
    public function testFromRawBuildsSnapshot(): void
    {
        $raw = ['class' => 'App\Entity\Author', 'id' => 1, 'label' => 'John Doe', 'table' => 'author'];
        $snapshot = EntitySnapshot::fromRaw($raw);

        $this->assertSame('App\Entity\Author', $snapshot->class);
        $this->assertSame(1, $snapshot->id);
        $this->assertSame('John Doe', $snapshot->label);
        $this->assertSame('author', $snapshot->table);
    }

    public function testFromRawWithStringId(): void
    {
        $raw = ['class' => 'App\Entity\Post', 'id' => 'uuid-abc-123', 'label' => 'Post title', 'table' => 'post'];
        $snapshot = EntitySnapshot::fromRaw($raw);

        $this->assertSame('uuid-abc-123', $snapshot->id);
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $snapshot = new EntitySnapshot('App\Entity\Author', 1, 'John', 'author');

        $this->assertSame('App\Entity\Author', $snapshot->class);
        $this->assertSame(1, $snapshot->id);
        $this->assertSame('John', $snapshot->label);
        $this->assertSame('author', $snapshot->table);
    }
}
