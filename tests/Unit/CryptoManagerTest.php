<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassClasses\CryptoManager\CryptoManager;

/**
 * Unit tests for CryptoManager.
 *
 * Covers RSA and AES operations (phpseclib v3).
 * RSA key pairs are generated once per class (1024-bit) for speed.
 *
 * DB-dependent operations (sharekey migration, per-item encryption flow)
 * belong in integration tests and are not covered here.
 */
class CryptoManagerTest extends TestCase
{
    /** @var array{privatekey:string,publickey:string} */
    private static array $keyPair;

    /**
     * Generate one RSA key pair for the whole test class.
     * 1024-bit keys are fast enough for unit tests.
     */
    public static function setUpBeforeClass(): void
    {
        self::$keyPair = CryptoManager::generateRSAKeyPair(1024);
    }

    // =========================================================================
    // generateRSAKeyPair
    // =========================================================================

    public function testGenerateRSAKeyPairReturnsPrivateKey(): void
    {
        $this->assertNotEmpty(self::$keyPair['privatekey']);
        $this->assertStringContainsString('-----BEGIN', self::$keyPair['privatekey']);
    }

    public function testGenerateRSAKeyPairReturnsPublicKey(): void
    {
        $this->assertNotEmpty(self::$keyPair['publickey']);
        $this->assertStringContainsString('-----BEGIN', self::$keyPair['publickey']);
    }

    public function testGenerateRSAKeyPairKeysAreDifferent(): void
    {
        $this->assertNotEquals(self::$keyPair['privatekey'], self::$keyPair['publickey']);
    }

    public function testGenerateRSAKeyPairProducesDifferentPairsEachCall(): void
    {
        $pair1 = CryptoManager::generateRSAKeyPair(1024);
        $pair2 = CryptoManager::generateRSAKeyPair(1024);

        $this->assertNotEquals($pair1['privatekey'], $pair2['privatekey']);
        $this->assertNotEquals($pair1['publickey'], $pair2['publickey']);
    }

    // =========================================================================
    // rsaEncrypt / rsaDecrypt — round-trip
    // =========================================================================

    public function testRsaEncryptDecryptRoundTrip(): void
    {
        $plain     = 'my-secret-object-key';
        $encrypted = CryptoManager::rsaEncrypt($plain, self::$keyPair['publickey']);
        $decrypted = CryptoManager::rsaDecrypt($encrypted, self::$keyPair['privatekey']);

        $this->assertSame($plain, $decrypted);
    }

    public function testRsaEncryptedOutputDiffersFromPlainText(): void
    {
        $plain     = 'plaintext-data';
        $encrypted = CryptoManager::rsaEncrypt($plain, self::$keyPair['publickey']);

        $this->assertNotEquals($plain, $encrypted);
    }

    public function testRsaEncryptSameInputProducesDifferentCiphertexts(): void
    {
        // OAEP padding embeds random bytes → two encryptions must differ
        $plain = 'same-plaintext';
        $c1    = CryptoManager::rsaEncrypt($plain, self::$keyPair['publickey']);
        $c2    = CryptoManager::rsaEncrypt($plain, self::$keyPair['publickey']);

        $this->assertNotEquals($c1, $c2);
    }

    public function testRsaEncryptDecryptRoundTripWithBase64EncodedPublicKey(): void
    {
        // Production code stores keys base64-encoded in the DB
        $plain         = 'base64-key-test';
        $base64PubKey  = base64_encode(self::$keyPair['publickey']);
        $base64PrivKey = base64_encode(self::$keyPair['privatekey']);

        $encrypted = CryptoManager::rsaEncrypt($plain, $base64PubKey);
        $decrypted = CryptoManager::rsaDecrypt($encrypted, $base64PrivKey);

        $this->assertSame($plain, $decrypted);
    }

    public function testRsaEncryptDecryptRoundTripWithSpecialCharacters(): void
    {
        $plain     = "P@\$\$w0rd!#%^&*()_+-=[]{}|\nLine2\téàü中文";
        $encrypted = CryptoManager::rsaEncrypt($plain, self::$keyPair['publickey']);
        $decrypted = CryptoManager::rsaDecrypt($encrypted, self::$keyPair['privatekey']);

        $this->assertSame($plain, $decrypted);
    }

    public function testRsaDecryptWithWrongKeyThrowsException(): void
    {
        $this->expectException(Exception::class);

        $wrongPair = CryptoManager::generateRSAKeyPair(1024);
        $encrypted = CryptoManager::rsaEncrypt('secret', self::$keyPair['publickey']);

        // Decrypt with mismatched private key must throw
        CryptoManager::rsaDecrypt($encrypted, $wrongPair['privatekey'], false);
    }

    public function testRsaDecryptWithInvalidDataThrowsException(): void
    {
        $this->expectException(Exception::class);

        CryptoManager::rsaDecrypt('not-valid-ciphertext', self::$keyPair['privatekey'], false);
    }

    public function testRsaEncryptWithInvalidKeyThrowsException(): void
    {
        $this->expectException(Exception::class);

        CryptoManager::rsaEncrypt('data', 'this-is-not-a-valid-key');
    }

    // =========================================================================
    // rsaDecryptWithVersionDetection
    // =========================================================================

    public function testRsaDecryptWithVersionDetectionReturnsVersionThreeForCurrentData(): void
    {
        $plain     = 'version-detection-test';
        $encrypted = CryptoManager::rsaEncrypt($plain, self::$keyPair['publickey']);

        $result = CryptoManager::rsaDecryptWithVersionDetection($encrypted, self::$keyPair['privatekey']);

        $this->assertSame($plain, $result['data']);
        $this->assertSame(3, $result['version_used']);
    }

    public function testRsaDecryptWithVersionDetectionResultHasRequiredKeys(): void
    {
        $encrypted = CryptoManager::rsaEncrypt('test', self::$keyPair['publickey']);
        $result    = CryptoManager::rsaDecryptWithVersionDetection($encrypted, self::$keyPair['privatekey']);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('version_used', $result);
    }

    public function testRsaDecryptWithVersionDetectionVersionIsIntegerOneOrThree(): void
    {
        $encrypted = CryptoManager::rsaEncrypt('test', self::$keyPair['publickey']);
        $result    = CryptoManager::rsaDecryptWithVersionDetection($encrypted, self::$keyPair['privatekey']);

        $this->assertContains($result['version_used'], [1, 3]);
    }

    // =========================================================================
    // rsaDecryptWithVersion
    // =========================================================================

    public function testRsaDecryptWithVersionThreeMatchesStandardDecrypt(): void
    {
        $plain     = 'explicit-version-3';
        $encrypted = CryptoManager::rsaEncrypt($plain, self::$keyPair['publickey']);

        $decrypted = CryptoManager::rsaDecryptWithVersion($encrypted, self::$keyPair['privatekey'], 3);

        $this->assertSame($plain, $decrypted);
    }

    // =========================================================================
    // getCurrentVersion
    // =========================================================================

    public function testGetCurrentVersionReturnsThree(): void
    {
        $this->assertSame(3, CryptoManager::getCurrentVersion());
    }

    // =========================================================================
    // aesEncrypt / aesDecrypt — round-trip (sha1)
    // =========================================================================

    public function testAesEncryptDecryptRoundTripWithSha1(): void
    {
        $plain    = 'aes-secret-with-sha1';
        $password = 'my-password';

        $encrypted = CryptoManager::aesEncrypt($plain, $password, 'cbc', 'sha1');
        $decrypted = CryptoManager::aesDecrypt($encrypted, $password, 'cbc', 'sha1');

        $this->assertSame($plain, $decrypted);
    }

    public function testAesEncryptDecryptRoundTripWithSha256(): void
    {
        $plain    = 'aes-secret-with-sha256';
        $password = 'my-password';

        $encrypted = CryptoManager::aesEncrypt($plain, $password, 'cbc', 'sha256');
        $decrypted = CryptoManager::aesDecrypt($encrypted, $password, 'cbc', 'sha256');

        $this->assertSame($plain, $decrypted);
    }

    public function testAesEncryptedOutputDiffersFromPlainText(): void
    {
        $plain     = 'visible-data';
        $encrypted = CryptoManager::aesEncrypt($plain, 'password', 'cbc', 'sha256');

        $this->assertNotEquals($plain, $encrypted);
    }

    public function testAesEncryptDecryptRoundTripWithSpecialCharacters(): void
    {
        $plain    = "P@\$\$w0rd!#%^&*()\nLine2\téàü中文日本語";
        $password = 'complex-password';

        $encrypted = CryptoManager::aesEncrypt($plain, $password, 'cbc', 'sha256');
        $decrypted = CryptoManager::aesDecrypt($encrypted, $password, 'cbc', 'sha256');

        $this->assertSame($plain, $decrypted);
    }

    public function testAesEncryptDecryptRoundTripWithLongData(): void
    {
        $plain    = str_repeat('abcdefghij', 500); // 5000 chars
        $password = 'password';

        $encrypted = CryptoManager::aesEncrypt($plain, $password, 'cbc', 'sha256');
        $decrypted = CryptoManager::aesDecrypt($encrypted, $password, 'cbc', 'sha256');

        $this->assertSame($plain, $decrypted);
    }

    // =========================================================================
    // Cross-version AES: data encrypted with sha1, decrypted with sha256 must fail
    // =========================================================================

    public function testAesDecryptWithWrongHashAlgorithmDoesNotReturnOriginal(): void
    {
        // AES-CBC has no authentication; wrong key derivation (sha1 vs sha256)
        // produces binary garbage. CryptoManager detects this and throws.
        $plain    = 'cross-version-test';
        $password = 'password';

        $encryptedWithSha1 = CryptoManager::aesEncrypt($plain, $password, 'cbc', 'sha1');

        $this->expectException(Exception::class);
        // Attempt to decrypt sha1-encrypted data using sha256 key derivation
        CryptoManager::aesDecrypt($encryptedWithSha1, $password, 'cbc', 'sha256');
    }

    // =========================================================================
    // aesDecryptWithVersionDetection
    // =========================================================================

    public function testAesDecryptWithVersionDetectionRecognisesVersionThree(): void
    {
        $plain    = 'v3-detection';
        $password = 'password';

        $encrypted = CryptoManager::aesEncrypt($plain, $password, 'cbc', 'sha256');
        $result    = CryptoManager::aesDecryptWithVersionDetection($encrypted, $password);

        $this->assertSame($plain, $result['data']);
        $this->assertSame(3, $result['version_used']);
    }

    public function testAesDecryptWithVersionDetectionRecognisesVersionOne(): void
    {
        $plain    = 'v1-detection';
        $password = 'password';

        // Encrypt with sha1 (v1 style) and check that detection falls back to v1
        $encrypted = CryptoManager::aesEncrypt($plain, $password, 'cbc', 'sha1');
        $result    = CryptoManager::aesDecryptWithVersionDetection($encrypted, $password);

        $this->assertSame($plain, $result['data']);
        $this->assertSame(1, $result['version_used']);
    }

    public function testAesDecryptWithVersionDetectionResultHasRequiredKeys(): void
    {
        $encrypted = CryptoManager::aesEncrypt('test', 'pwd', 'cbc', 'sha256');
        $result    = CryptoManager::aesDecryptWithVersionDetection($encrypted, 'pwd');

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('version_used', $result);
    }

    // =========================================================================
    // createAESCipher / loadRSAKey — smoke tests
    // =========================================================================

    public function testCreateAESCipherReturnsCipherObject(): void
    {
        $cipher = CryptoManager::createAESCipher('cbc');

        $this->assertIsObject($cipher);
    }

    public function testLoadRSAKeyWithPublicKeyReturnsObject(): void
    {
        $key = CryptoManager::loadRSAKey(self::$keyPair['publickey']);

        $this->assertIsObject($key);
    }

    public function testLoadRSAKeyWithPrivateKeyReturnsObject(): void
    {
        $key = CryptoManager::loadRSAKey(self::$keyPair['privatekey']);

        $this->assertIsObject($key);
    }

    public function testLoadRSAKeyWithBase64EncodedKeyReturnsObject(): void
    {
        $key = CryptoManager::loadRSAKey(base64_encode(self::$keyPair['publickey']));

        $this->assertIsObject($key);
    }

    public function testLoadRSAKeyWithInvalidDataThrowsException(): void
    {
        $this->expectException(Exception::class);

        CryptoManager::loadRSAKey('this-is-not-a-key');
    }
}
