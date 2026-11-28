<?php
/** UPDATE_SIGNING_KEY=<cle secrete> php scripts/release/sign.php dist/novacreneaux-1.0.0.zip */
require __DIR__ . '/lib.php';

$zip = $argv[1] ?? '';
$key = getenv('UPDATE_SIGNING_KEY');
if (!is_file($zip) || !$key) {
    fwrite(STDERR, "Usage : UPDATE_SIGNING_KEY=... php scripts/release/sign.php archive.zip (secret UPDATE_SIGNING_KEY absent ?)\n");
    exit(1);
}
release_sign($zip, $key);
echo "Signature ecrite : {$zip}.sig\n";
