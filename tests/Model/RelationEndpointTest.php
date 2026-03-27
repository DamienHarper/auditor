<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Model;

use DH\Auditor\Model\RelationEndpoint;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class RelationEndpointTest extends TestCase
{
    public function testFromRawBuildsEndpointWithStandardId(): void
    {
        $raw = [
            'class' => 'App\Entity\Author',
            'field' => 'posts',
            'id' => 1,
            'label' => 'John Doe',
            'table' => 'author',
        ];
        $endpoint = RelationEndpoint::fromRaw($raw);

        $this->assertSame('App\Entity\Author', $endpoint->class);
        $this->assertSame('posts', $endpoint->field);
        $this->assertSame(1, $endpoint->id);
        $this->assertSame('John Doe', $endpoint->label);
        $this->assertSame('author', $endpoint->table);
        $this->assertNull($endpoint->pkName);
    }

    public function testFromRawWithPkNameResolvesCustomPrimaryKey(): void
    {
        $raw = [
            'class' => 'App\Entity\Post',
            'field' => 'tags',
            'uuid' => 'abc-123',
            'label' => 'First post',
            'table' => 'post',
            'pkName' => 'uuid',
        ];
        $endpoint = RelationEndpoint::fromRaw($raw);

        $this->assertSame('abc-123', $endpoint->id);
        $this->assertSame('uuid', $endpoint->pkName);
    }

    public function testFromRawThrowsWhenPkNameKeyIsAbsent(): void
    {
        $raw = [
            'class' => 'App\Entity\Post',
            'field' => 'tags',
            'id' => 42,
            'label' => 'Post',
            'table' => 'post',
            'pkName' => 'nonexistent_key',
        ];

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('nonexistent_key');
        RelationEndpoint::fromRaw($raw);
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $endpoint = new RelationEndpoint('App\Entity\Author', 'posts', 1, 'John', 'author');

        $this->assertNull($endpoint->pkName);
        $this->assertSame(1, $endpoint->id);
    }
}
