<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Attribute;

use DH\Auditor\Attribute\Mask;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Tests\Fixtures\Attribute\ClassLevelFieldsModel;
use DH\Auditor\Tests\Fixtures\Attribute\PropertyLevelFieldsEntity;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class MaskTest extends TestCase
{
    private const string SECRET = 'a-secret-of-at-least-thirty-two-bytes';

    public function testPropertyLevelMaskHasNoField(): void
    {
        $attributes = new \ReflectionProperty(PropertyLevelFieldsEntity::class, 'email')->getAttributes(Mask::class);

        $this->assertCount(1, $attributes);
        $mask = $attributes[0]->newInstance();
        $this->assertNull($mask->field);
        $this->assertSame(Mask::HIDDEN, $mask->strategy);
    }

    public function testClassLevelMaskIsRepeatableAndNamesTheField(): void
    {
        $attributes = new \ReflectionClass(ClassLevelFieldsModel::class)->getAttributes(Mask::class);

        $masks = array_map(static fn (\ReflectionAttribute $attribute): array => [$attribute->newInstance()->field, $attribute->newInstance()->strategy], $attributes);
        $this->assertSame([['email', Mask::KEYED_HASH], ['phone', Mask::HIDDEN]], $masks);
    }

    public function testEmptyFieldIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Mask(field: ' ');
    }

    public function testUnknownStrategyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"hash" given');

        new Mask(strategy: 'hash');
    }

    public function testHiddenReplacesAnyValueButNull(): void
    {
        $mask = new Mask();

        $this->assertSame(Mask::HIDDEN_VALUE, $mask->apply('secret', null));
        $this->assertSame(Mask::HIDDEN_VALUE, $mask->apply(42, null));
        $this->assertSame(Mask::HIDDEN_VALUE, $mask->apply(['a' => 1], null));
        $this->assertNull($mask->apply(null, null));
    }

    public function testKeyedHashIsAnHmacOfTheValue(): void
    {
        $mask = new Mask(Mask::KEYED_HASH);

        $this->assertSame(hash_hmac('sha256', 'john@example.com', self::SECRET), $mask->apply('john@example.com', self::SECRET));
        $this->assertSame(hash_hmac('sha256', '42', self::SECRET), $mask->apply(42, self::SECRET));
        $this->assertSame(hash_hmac('sha256', '{"a":1}', self::SECRET), $mask->apply(['a' => 1], self::SECRET));
        $this->assertNull($mask->apply(null, self::SECRET));
    }

    public function testKeyedHashDependsOnTheSecret(): void
    {
        $mask = new Mask(Mask::KEYED_HASH);

        $this->assertNotSame($mask->apply('john@example.com', self::SECRET), $mask->apply('john@example.com', 'another-secret-of-thirty-two-bytes'));
    }

    public function testKeyedHashNeedsASecret(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Mask(Mask::KEYED_HASH)->apply('john@example.com', null);
    }
}
