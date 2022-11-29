<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Rector\Class_\MakeCommandLazyRector;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Transform\Rector\Attribute\AttributeKeyToClassConstFetchRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__.'/src', __DIR__.'/tests']);

    // Do not try to change simple property init and assign to constructor promotion
    // to make code easier to read (no more class with properties declared both
    // at the start of the class and in the constructor)
    $rectorConfig->skip([
        ClassPropertyAssignToConstructorPromotionRector::class,
        RemoveUnusedPrivatePropertyRector::class,
        AttributeKeyToClassConstFetchRector::class,
        MakeCommandLazyRector::class,
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

    // Symfony rules
//    $rectorConfig->symfonyContainerXml(__DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml');
    $rectorConfig->sets([
        SymfonySetList::SYMFONY_54,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
        SymfonySetList::SYMFONY_STRICT,
    ]);

    // Doctrine rules
    $rectorConfig->sets([
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        DoctrineSetList::DOCTRINE_DBAL_30,
        DoctrineSetList::DOCTRINE_ORM_29,
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
    ]);
};
