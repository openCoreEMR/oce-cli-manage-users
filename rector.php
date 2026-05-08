<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/bin',
    ])
    ->withCache(
        cacheClass: FileCacheStorage::class,
        cacheDirectory: '/tmp/rector-oce-cli-manage-users'
    )
    ->withConfiguredRule(ClassPropertyAssignToConstructorPromotionRector::class, [
        'allow_model_based_classes' => true,
        'inline_public' => false,
        'rename_property' => true,
    ])
    ->withParallel(
        timeoutSeconds: 120,
        maxNumberOfProcess: 12,
        jobSize: 12
    )
    ->withPhpSets()
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true
    )
    ->withSkip([
        __DIR__ . '/vendor',
        \Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector::class,
    ]);
