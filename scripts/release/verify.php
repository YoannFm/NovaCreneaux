<?php
/** Verifie la signature avec la cle publique commitee dans config/nova.php : empeche de publier avec une mauvaise cle secrete. */
require __DIR__ . '/lib.php';

$zip = $argv[1] ?? '';
$publicKey = release_config_string(realpath(__DIR__ . '/../..'), 'update_public_key');
if (!is_file($zip) || !release_verify($zip, $publicKey)) {
    fwrite(STDERR, "Signature invalide : la cle secrete ne correspond pas a la cle publique de config/nova.php.\n");
    exit(1);
}
echo "Signature valide.\n";
