<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Model;

use DH\Auditor\Model\RelationDescriptor;
use DH\Auditor\Model\RelationEndpoint;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class RelationDescriptorTest extends TestCase
{
    public function testFromRawBuildsOneToManyDescriptor(): void
    {
        $raw = [
            'is_owning_side' => false,
            'source' => ['class' => 'App\Entity\Author', 'field' => 'posts', 'id' => 1, 'label' => 'John Doe', 'table' => 'author'],
            'target' => ['class' => 'App\Entity\Post', 'field' => 'author', 'id' => 5, 'label' => 'First post', 'table' => 'post'],
        ];
        $descriptor = RelationDescriptor::fromRaw($raw);

        $this->assertFalse($descriptor->isOwningSide);
        $this->assertNull($descriptor->pivotTable);
        $this->assertInstanceOf(RelationEndpoint::class, $descriptor->source);
        $this->assertInstanceOf(RelationEndpoint::class, $descriptor->target);
        $this->assertSame('App\Entity\Author', $descriptor->source->class);
        $this->assertSame('App\Entity\Post', $descriptor->target->class);
    }

    public function testFromRawBuildsManyToManyDescriptorWithPivotTable(): void
    {
        $raw = [
            'is_owning_side' => true,
            'source' => ['class' => 'App\Entity\Post', 'field' => 'tags', 'id' => 1, 'label' => 'Post', 'table' => 'post'],
            'target' => ['class' => 'App\Entity\Tag', 'field' => 'posts', 'id' => 2, 'label' => 'Tag', 'table' => 'tag'],
            'table' => 'post__tag',
        ];
        $descriptor = RelationDescriptor::fromRaw($raw);

        $this->assertTrue($descriptor->isOwningSide);
        $this->assertSame('post__tag', $descriptor->pivotTable);
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $source = new RelationEndpoint('App\Entity\Post', 'tags', 1, 'Post', 'post');
        $target = new RelationEndpoint('App\Entity\Tag', 'posts', 2, 'Tag', 'tag');
        $descriptor = new RelationDescriptor($source, $target, true, 'post__tag');

        $this->assertSame($source, $descriptor->source);
        $this->assertSame($target, $descriptor->target);
        $this->assertTrue($descriptor->isOwningSide);
        $this->assertSame('post__tag', $descriptor->pivotTable);
    }
}
