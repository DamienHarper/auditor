<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Symfony\Set\SymfonySetList;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/src', __DIR__.'/tests'])
//    ->withPaths([__DIR__.'/src'])
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
        phpunitCodeQuality: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
        phpunit: true,
    )
    ->withSets([
        SymfonySetList::SYMFONY_54,
        PHPUnitSetList::PHPUNIT_110,
    ])
    ->withAttributesSets(
        symfony: true,
        doctrine: true,
        phpunit: true,
    )
;
