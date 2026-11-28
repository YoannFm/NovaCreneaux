<?php
/**
 * PLUGIN_SIGNING_KEY=<cle secrete> php scripts/plugins/catalog.php dossier-des-archives [dossier-de-sortie]
 * Ecrit index.json et index.json.sig : a publier a la racine de la branche main du depot du catalogue,
 * avec les archives (.zip et .zip.sig) en pieces jointes de la release « {id}-{version} ».
 */
require __DIR__ . '/lib.php';

$key = getenv('PLUGIN_SIGNING_KEY');
if (! isset($argv[1]) || ! is_dir($argv[1]) || ! $key) {
    fwrite(STDERR, "Usage : PLUGIN_SIGNING_KEY=... php scripts/plugins/catalog.php dossier-des-archives [sortie]\n");
    exit(1);
}

$result = plugin_catalog($argv[1], $argv[2] ?? 'dist-catalog', $key);
echo "{$result['path']} : {$result['plugins']} extension(s), signature ecrite.\n";
