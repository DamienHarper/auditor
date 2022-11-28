<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__.'/src', __DIR__.'/tests']);

    // Do not try to change simple property init and assign to constructor promotion
    // to make code easier to read (no more class with properties declared both
    // at the start of the class and in the constructor)
    $rectorConfig->skip([
        ClassPropertyAssignToConstructorPromotionRector::class,
        RemoveUnusedPrivatePropertyRector::class,
    ]);

    // PHP rules
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_80,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::CODING_STYLE,
//        SetList::TYPE_DECLARATION,
//        SetList::TYPE_DECLARATION_STRICT,
    ]);

    // Doctrine rules
    $rectorConfig->sets([
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
    ]);
};
