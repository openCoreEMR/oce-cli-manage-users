#!/usr/bin/env php
<?php

/**
 * Build script for creating oce-manage-users.phar
 *
 * @package   OpenCoreEMR\CLI\ManageUsers
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc
 * @license   GPL-3.0-or-later
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

if (ini_get('phar.readonly')) {
    fwrite(STDERR, "Error: phar.readonly is enabled. Run with: php -d phar.readonly=0 build.php\n");
    exit(1);
}

$buildDir = __DIR__ . '/build';
$pharFile = $buildDir . '/oce-manage-users.phar';

$version = getenv('BUILD_VERSION');
if ($version === false || $version === '') {
    $version = trim((string) shell_exec('git describe --tags 2>/dev/null'));
    if ($version === '') {
        $version = 'dev';
    }
}

echo "Building oce-manage-users.phar (version: {$version})...\n";

if (!is_dir($buildDir)) {
    mkdir($buildDir, 0755, true);
}

if (file_exists($pharFile)) {
    unlink($pharFile);
}

try {
    $phar = new Phar($pharFile);

    $phar->setMetadata([
        'name' => 'oce-manage-users',
        'version' => $version,
        'created' => date('Y-m-d H:i:s'),
    ]);

    $phar->startBuffering();

    echo "Adding source files...\n";
    $excludedNames = ['build', 'tests', 'tools', '.git', '.github', '.claude', '.phpunit.cache', 'build.php'];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS),
            static fn (SplFileInfo $file): bool => !in_array($file->getFilename(), $excludedNames, true)
        )
    );
    $phar->buildFromIterator($iterator, __DIR__);

    $stub = "#!/usr/bin/env php\n" . $phar->createDefaultStub('bin/oce-manage-users');
    $phar->setStub($stub);

    $phar->stopBuffering();

    echo "Compressing...\n";
    $phar->compressFiles(Phar::GZ);

    chmod($pharFile, 0755);

    echo "PHAR built: {$pharFile}\n";
    echo "  Size: " . number_format((int) filesize($pharFile)) . " bytes\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error building PHAR: " . $e->getMessage() . "\n");
    exit(1);
}
