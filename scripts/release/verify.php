<?php
/** Verifie la signature avec la cle publique commitee dans config/nova.php : empeche de publier avec une mauvaise cle secrete. */
require __DIR__ . '/lib.php';

$zip = $argv[1] ?? '';
$config = include __DIR__ . '/../../config/nova.php';
if (!is_file($zip) || !release_verify($zip, $config['update_public_key'])) {
    fwrite(STDERR, "Signature invalide : la cle secrete ne correspond pas a la cle publique de config/nova.php.\n");
    exit(1);
}
echo "Signature valide.\n";
