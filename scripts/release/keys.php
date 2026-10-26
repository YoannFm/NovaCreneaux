<?php
/**
 * Genere la paire de cles de signature des mises a jour (a lancer une seule fois).
 *   php scripts/release/keys.php
 * - la cle PUBLIQUE se colle dans config/nova.php ('update_public_key') ;
 * - la cle SECRETE se colle dans GitHub : Settings > Secrets and variables > Actions > UPDATE_SIGNING_KEY.
 * Ne la partagez avec personne, ne la commitez jamais.
 */
$pair = sodium_crypto_sign_keypair();

echo "Cle publique (config/nova.php) :\n" . base64_encode(sodium_crypto_sign_publickey($pair)) . "\n\n";
echo "Cle secrete (secret GitHub UPDATE_SIGNING_KEY, a garder confidentielle) :\n" . base64_encode(sodium_crypto_sign_secretkey($pair)) . "\n";
