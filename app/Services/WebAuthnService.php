<?php

namespace App\Services;

use App\Models\User;

/**
 * WebAuthn (FIDO2) implementation in pure PHP.
 * Supports "none" attestation, ES256 (EC P-256), and RS256 (RSA).
 * RFC: https://www.w3.org/TR/webauthn-2/
 */
class WebAuthnService
{
    private function rpId(): string
    {
        return parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
    }

    private function origin(): string
    {
        return rtrim(config('app.url'), '/');
    }

    // ──────────────────────────────────────────────────────────────────
    // Registration
    // ──────────────────────────────────────────────────────────────────

    public function generateRegistrationOptions(User $user): array
    {
        $challenge = random_bytes(32);

        return [
            'challenge'  => $this->b64u($challenge),
            'rp'         => ['name' => config('app.name'), 'id' => $this->rpId()],
            'user'       => [
                'id'          => $this->b64u(pack('N', $user->id)),
                'name'        => $user->email,
                'displayName' => $user->name,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256
                ['type' => 'public-key', 'alg' => -257],  // RS256
            ],
            'authenticatorSelection' => ['userVerification' => 'preferred'],
            'timeout'     => 60000,
            'attestation' => 'none',
            '_challenge'  => base64_encode($challenge), // stored server-side in session
        ];
    }

    public function verifyRegistration(array $attestation, string $sessionChallenge, ?string $keyName = null): ?array
    {
        try {
            $clientData = json_decode($this->b64uDecode($attestation['clientDataJSON']), true);
            if (($clientData['type'] ?? '') !== 'webauthn.create') return null;

            if ($clientData['challenge'] !== $this->b64u(base64_decode($sessionChallenge))) return null;
            if ($clientData['origin'] !== $this->origin()) return null;

            $attestObj = CborDecoder::decode($this->b64uDecode($attestation['attestationObject']));
            if (($attestObj['fmt'] ?? '') !== 'none') return null; // only "none" supported

            $authData = $attestObj['authData'];

            // rpIdHash check
            if (substr($authData, 0, 32) !== hash('sha256', $this->rpId(), true)) return null;

            $flags = ord($authData[32]);
            if (!($flags & 0x01)) return null; // UP required
            if (!($flags & 0x40)) return null; // AT (attested credential) required

            $offset = 37 + 16; // skip signCount(4) + aaguid(16)
            $credIdLen = unpack('n', substr($authData, $offset, 2))[1];
            $offset += 2;
            $credentialId = substr($authData, $offset, $credIdLen);
            $offset += $credIdLen;

            $coseKey   = CborDecoder::decode(substr($authData, $offset));
            $publicKey = $this->coseKeyToPem($coseKey);
            if (!$publicKey) return null;

            return [
                'id'             => base64_encode($credentialId),
                'public_key_pem' => $publicKey,
                'name'           => $keyName ?: ('Clé ' . date('d/m/Y')),
                'created_at'     => now()->toISOString(),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Authentication
    // ──────────────────────────────────────────────────────────────────

    public function generateAssertionOptions(User $user): array
    {
        $challenge   = random_bytes(32);
        $credentials = $user->webAuthnCredentials();

        return [
            'challenge'        => $this->b64u($challenge),
            'rpId'             => $this->rpId(),
            'userVerification' => 'preferred',
            'timeout'          => 60000,
            'allowCredentials' => array_map(fn($c) => [
                'type' => 'public-key',
                'id'   => rtrim(strtr(base64_encode(base64_decode($c['id'])), '+/', '-_'), '='),
            ], $credentials),
            '_challenge' => base64_encode($challenge),
        ];
    }

    public function verifyAssertion(User $user, array $assertion, string $sessionChallenge): bool
    {
        try {
            $credentialId = $this->b64uDecode($assertion['id'] ?? $assertion['rawId'] ?? '');
            $credentials  = $user->webAuthnCredentials();
            $credential   = null;
            foreach ($credentials as $c) {
                if (base64_decode($c['id']) === $credentialId) {
                    $credential = $c;
                    break;
                }
            }
            if (!$credential) return false;

            $clientData = json_decode($this->b64uDecode($assertion['clientDataJSON']), true);
            if (($clientData['type'] ?? '') !== 'webauthn.get') return false;
            if ($clientData['challenge'] !== $this->b64u(base64_decode($sessionChallenge))) return false;
            if ($clientData['origin'] !== $this->origin()) return false;

            $authData = $this->b64uDecode($assertion['authenticatorData']);
            if (substr($authData, 0, 32) !== hash('sha256', $this->rpId(), true)) return false;
            if (!(ord($authData[32]) & 0x01)) return false;

            $clientDataHash = hash('sha256', $this->b64uDecode($assertion['clientDataJSON']), true);
            $signedData     = $authData . $clientDataHash;
            $signature      = $this->b64uDecode($assertion['signature']);

            $publicKey = openssl_pkey_get_public($credential['public_key_pem']);
            if (!$publicKey) return false;

            return openssl_verify($signedData, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // COSE public key → PEM
    // ──────────────────────────────────────────────────────────────────

    private function coseKeyToPem(array $coseKey): ?string
    {
        $kty = $coseKey[1] ?? null;
        $alg = $coseKey[3] ?? null;

        // EC2 P-256 (ES256)
        if ($kty === 2 && $alg === -7) {
            $x   = $coseKey[-2];
            $y   = $coseKey[-3];
            $der = $this->buildEcPublicKeyDer("\x04" . $x . $y);
            return $this->pemWrap($der, 'PUBLIC KEY');
        }

        // RSA RS256
        if ($kty === 3 && $alg === -257) {
            $n   = $coseKey[-1];
            $e   = $coseKey[-2];
            $der = $this->buildRsaPublicKeyDer($n, $e);
            return $this->pemWrap($der, 'PUBLIC KEY');
        }

        return null;
    }

    private function buildEcPublicKeyDer(string $point): string
    {
        // OID 1.2.840.10045.2.1 (ecPublicKey) + OID 1.2.840.10045.3.1.7 (P-256)
        $algoSeq = $this->derSeq(
            $this->derOid("\x2a\x86\x48\xce\x3d\x02\x01") .
            $this->derOid("\x2a\x86\x48\xce\x3d\x03\x01\x07")
        );
        return $this->derSeq($algoSeq . $this->derBitStr($point));
    }

    private function buildRsaPublicKeyDer(string $n, string $e): string
    {
        // OID 1.2.840.113549.1.1.1 (rsaEncryption) + NULL
        $algoSeq = $this->derSeq(
            $this->derOid("\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01") . "\x05\x00"
        );
        $rsaKey = $this->derSeq($this->derInt($n) . $this->derInt($e));
        return $this->derSeq($algoSeq . $this->derBitStr($rsaKey));
    }

    // ──────────────────────────────────────────────────────────────────
    // DER helpers
    // ──────────────────────────────────────────────────────────────────

    private function derLen(string $data): string
    {
        $len = strlen($data);
        if ($len < 128)   return chr($len);
        if ($len < 256)   return "\x81" . chr($len);
        return "\x82" . chr($len >> 8) . chr($len & 0xFF);
    }

    private function derSeq(string $data): string
    {
        return "\x30" . $this->derLen($data) . $data;
    }

    private function derOid(string $bytes): string
    {
        return "\x06" . $this->derLen($bytes) . $bytes;
    }

    private function derBitStr(string $data): string
    {
        $inner = "\x00" . $data;
        return "\x03" . $this->derLen($inner) . $inner;
    }

    private function derInt(string $bytes): string
    {
        if (strlen($bytes) > 0 && (ord($bytes[0]) & 0x80)) {
            $bytes = "\x00" . $bytes;
        }
        return "\x02" . $this->derLen($bytes) . $bytes;
    }

    private function pemWrap(string $der, string $type): string
    {
        return "-----BEGIN {$type}-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END {$type}-----\n";
    }

    // ──────────────────────────────────────────────────────────────────
    // Base64url helpers
    // ──────────────────────────────────────────────────────────────────

    public function b64u(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public function b64uDecode(string $b64u): string
    {
        $pad = strlen($b64u) % 4;
        if ($pad) $b64u .= str_repeat('=', 4 - $pad);
        return base64_decode(strtr($b64u, '-_', '+/'));
    }
}

// ──────────────────────────────────────────────────────────────────
// Minimal CBOR decoder (RFC 7049) — WebAuthn use only
// ──────────────────────────────────────────────────────────────────
final class CborDecoder
{
    private int $pos = 0;

    private function __construct(private readonly string $data) {}

    public static function decode(string $bytes): mixed
    {
        return (new self($bytes))->read();
    }

    private function read(): mixed
    {
        $byte = ord($this->data[$this->pos++]);
        $mt   = ($byte >> 5) & 0x07;
        $ai   = $byte & 0x1F;
        $val  = $this->additionalValue($ai);

        return match ($mt) {
            0 => $val,
            1 => -1 - $val,
            2 => $this->readRaw($val),
            3 => $this->readRaw($val),
            4 => array_map(fn() => $this->read(), array_fill(0, $val, null)),
            5 => $this->readMap($val),
            7 => match ($ai) { 20 => false, 21 => true, 22 => null, default => $val },
            default => throw new \RuntimeException("CBOR: unknown major type $mt"),
        };
    }

    private function additionalValue(int $ai): int
    {
        return match (true) {
            $ai <= 23 => $ai,
            $ai === 24 => ord($this->data[$this->pos++]),
            $ai === 25 => unpack('n', substr($this->data, ($this->pos += 2) - 2, 2))[1],
            $ai === 26 => unpack('N', substr($this->data, ($this->pos += 4) - 4, 4))[1],
            default    => throw new \RuntimeException("CBOR: unsupported additional info $ai"),
        };
    }

    private function readRaw(int $len): string
    {
        $slice       = substr($this->data, $this->pos, $len);
        $this->pos  += $len;
        return $slice;
    }

    private function readMap(int $count): array
    {
        $map = [];
        for ($i = 0; $i < $count; $i++) {
            $key       = $this->read();
            $map[$key] = $this->read();
        }
        return $map;
    }
}
