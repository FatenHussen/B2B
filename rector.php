<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPromotedPropertyRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\DeadCode\Rector\FunctionLike\NarrowWideUnionReturnTypeRector;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/app-modules',
        __DIR__.'/bootstrap',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/app-modules/*/vendor',
        __DIR__.'/bootstrap/cache',
        __DIR__.'/vendor',

        // Public shapes are a published contract in this repository: events, DTOs and
        // resources are consumed across module boundaries and by the generated TS client.
        // Rector may not rewrite a signature or a class declaration on its own.
        ReadOnlyClassRector::class,
        RemoveUnusedPromotedPropertyRector::class,
        RemoveUnusedPublicMethodParameterRector::class,
        NarrowWideUnionReturnTypeRector::class,
        InlineConstructorDefaultToPropertyRector::class,
    ])
    ->withPhpSets(php83: true)
    ->withPreparedSets(deadCode: true);
