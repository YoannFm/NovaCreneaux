<?php
/** php scripts/release/build.php --version=1.0.0 [--output=dist] [--deleted-file=deleted.txt] */
require __DIR__ . '/lib.php';

$options = getopt('', ['version:', 'output::', 'deleted-file::']);
$root = realpath(__DIR__ . '/../..');
$deleted = isset($options['deleted-file']) && is_file($options['deleted-file'])
    ? file($options['deleted-file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    : [];

$result = release_build($root, $options['output'] ?? 'dist', $options['version'] ?? '', $deleted);
echo "Archive {$result['path']} : {$result['files']} fichiers, " . round($result['bytes'] / 1048576, 1) . " Mo\n";
