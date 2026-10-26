<?php
namespace App\Services\Update;

/** Verifie la signature ed25519 (detachee) d'une archive : seul le detenteur de la cle secrete peut publier une mise a jour. */
class SignatureVerifier {
    public function isConfigured(?string $publicKey): bool {
        return function_exists('sodium_crypto_sign_verify_detached')
            && strlen((string) base64_decode((string) $publicKey, true)) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES;
    }

    public function verify(string $file, string $signatureBase64, ?string $publicKey): void {
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new UpdateException("L'extension PHP sodium est absente : impossible de vérifier la signature.");
        }
        if (!$this->isConfigured($publicKey)) {
            throw new UpdateException('La clé publique de signature n\'est pas configurée (config/nova.php) : mises à jour refusées.');
        }

        $signature = base64_decode(trim($signatureBase64), true);
        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            throw new UpdateException('Le fichier de signature est invalide.');
        }

        $content = file_get_contents($file);
        if ($content === false || !sodium_crypto_sign_verify_detached($signature, $content, base64_decode($publicKey, true))) {
            throw new UpdateException("La signature de l'archive est invalide : mise à jour refusée.");
        }
    }
}
