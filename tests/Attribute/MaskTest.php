<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Attribute;

use DH\Auditor\Attribute\Mask;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Masking\MaskStrategy;
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
    public function testPropertyLevelMaskHasNoField(): void
    {
        $attributes = new \ReflectionProperty(PropertyLevelFieldsEntity::class, 'email')->getAttributes(Mask::class);

        $this->assertCount(1, $attributes);
        $mask = $attributes[0]->newInstance();
        $this->assertNull($mask->field);
        $this->assertSame(MaskStrategy::Hidden, $mask->strategy);
    }

    public function testClassLevelMaskIsRepeatableAndNamesTheField(): void
    {
        $attributes = new \ReflectionClass(ClassLevelFieldsModel::class)->getAttributes(Mask::class);

        $masks = array_map(static fn (\ReflectionAttribute $attribute): array => [$attribute->newInstance()->field, $attribute->newInstance()->strategy], $attributes);
        $this->assertSame([['email', MaskStrategy::KeyedHash], ['phone', MaskStrategy::Hidden]], $masks);
    }

    public function testEmptyFieldIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Mask(field: ' ');
    }
}
