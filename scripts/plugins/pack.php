<?php
/** PLUGIN_SIGNING_KEY=<cle secrete> php scripts/plugins/pack.php dossier-de-l-extension [dossier-de-sortie] */
require __DIR__ . '/lib.php';

$key = getenv('PLUGIN_SIGNING_KEY');
if (! isset($argv[1]) || ! is_dir($argv[1]) || ! $key) {
    fwrite(STDERR, "Usage : PLUGIN_SIGNING_KEY=... php scripts/plugins/pack.php dossier-de-l-extension [sortie]\n");
    exit(1);
}

$result = plugin_pack($argv[1], $argv[2] ?? 'dist-plugins', $key);
echo "{$result['path']} : {$result['files']} fichiers, signature ecrite ({$result['path']}.sig)\n";
