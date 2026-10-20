<?php

declare(strict_types=1);

/**
 * Lint all project PHP files (excludes vendor/).
 *
 * Usage: php bin/lint.php
 * Exit code 0 on success, 1 if any file fails php -l.
 */

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$failed = 0;
$checked = 0;

/** @var SplFileInfo $file */
foreach ($iterator as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
        continue;
    }

    $checked++;
    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    exec($cmd, $output, $code);
    $line = $output[0] ?? '';
    $output = [];

    if ($code !== 0) {
        echo $line . PHP_EOL;
        $failed++;
        continue;
    }

    echo $line . PHP_EOL;
}

echo PHP_EOL . "Checked {$checked} file(s); {$failed} failed." . PHP_EOL;

exit($failed > 0 ? 1 : 0);
