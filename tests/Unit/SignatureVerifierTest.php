<?php

namespace Tests\Unit;

use App\Services\Update\SignatureVerifier;
use App\Services\Update\UpdateException;
use PHPUnit\Framework\TestCase;

class SignatureVerifierTest extends TestCase
{
    private string $file;
    private string $publicKey;
    private string $signature;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'sig');
        file_put_contents($this->file, 'contenu de l\'archive');
        $pair = sodium_crypto_sign_keypair();
        $this->publicKey = base64_encode(sodium_crypto_sign_publickey($pair));
        $this->signature = base64_encode(sodium_crypto_sign_detached('contenu de l\'archive', sodium_crypto_sign_secretkey($pair)));
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function test_a_valid_signature_is_accepted(): void
    {
        (new SignatureVerifier)->verify($this->file, $this->signature . "\n", $this->publicKey);

        $this->addToAssertionCount(1);
    }

    public function test_a_modified_archive_is_refused(): void
    {
        file_put_contents($this->file, 'contenu modifie');

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('signature de l\'archive est invalide');
        (new SignatureVerifier)->verify($this->file, $this->signature, $this->publicKey);
    }

    public function test_a_signature_from_another_key_is_refused(): void
    {
        $other = base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()));

        $this->expectException(UpdateException::class);
        (new SignatureVerifier)->verify($this->file, $this->signature, $other);
    }

    public function test_garbage_signatures_are_refused(): void
    {
        foreach (['', 'pas du base64 !!', base64_encode('trop court')] as $bad) {
            try {
                (new SignatureVerifier)->verify($this->file, $bad, $this->publicKey);
                $this->fail("Signature acceptee : {$bad}");
            } catch (UpdateException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_without_a_public_key_nothing_is_accepted(): void
    {
        $verifier = new SignatureVerifier;

        $this->assertFalse($verifier->isConfigured(''));
        $this->assertFalse($verifier->isConfigured('abc'));
        $this->assertTrue($verifier->isConfigured($this->publicKey));
        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('n\'est pas configurée');
        $verifier->verify($this->file, $this->signature, '');
    }
}
