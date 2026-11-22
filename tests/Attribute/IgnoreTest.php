<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Attribute;

use DH\Auditor\Attribute\Ignore;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Tests\Fixtures\Attribute\ClassLevelFieldsModel;
use DH\Auditor\Tests\Fixtures\Attribute\PropertyLevelFieldsEntity;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class IgnoreTest extends TestCase
{
    public function testPropertyLevelIgnoreHasNoField(): void
    {
        $attributes = new \ReflectionProperty(PropertyLevelFieldsEntity::class, 'password')->getAttributes(Ignore::class);

        $this->assertCount(1, $attributes);
        $this->assertNull($attributes[0]->newInstance()->field);
    }

    public function testClassLevelIgnoreIsRepeatableAndNamesTheField(): void
    {
        $attributes = new \ReflectionClass(ClassLevelFieldsModel::class)->getAttributes(Ignore::class);

        $fields = array_map(static fn (\ReflectionAttribute $attribute): ?string => $attribute->newInstance()->field, $attributes);
        $this->assertSame(['password', 'remember_token'], $fields);
    }

    public function testEmptyFieldIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Ignore(field: ' ');
    }
}
