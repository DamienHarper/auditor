<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Attribute;

use DH\Auditor\Attribute\DiffLabel;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Tests\Fixtures\Attribute\ClassLevelFieldsModel;
use DH\Auditor\Tests\Fixtures\Attribute\PropertyLevelFieldsEntity;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class DiffLabelTest extends TestCase
{
    public function testPropertyLevelDiffLabelHasNoField(): void
    {
        $attributes = new \ReflectionProperty(PropertyLevelFieldsEntity::class, 'categoryId')->getAttributes(DiffLabel::class);

        $this->assertCount(1, $attributes);
        $diffLabel = $attributes[0]->newInstance();
        $this->assertSame('App\Resolver\CategoryLabelResolver', $diffLabel->resolver);
        $this->assertNull($diffLabel->field);
    }

    public function testClassLevelDiffLabelIsRepeatableAndNamesTheField(): void
    {
        $attributes = new \ReflectionClass(ClassLevelFieldsModel::class)->getAttributes(DiffLabel::class);

        $resolvers = [];
        foreach ($attributes as $attribute) {
            $diffLabel = $attribute->newInstance();
            $resolvers[$diffLabel->field] = $diffLabel->resolver;
        }

        $this->assertSame([
            'category_id' => 'App\Resolver\CategoryLabelResolver',
            'status' => 'App\Resolver\StatusLabelResolver',
        ], $resolvers);
    }

    public function testEmptyFieldIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DiffLabel(resolver: 'App\Resolver\CategoryLabelResolver', field: '');
    }
}
