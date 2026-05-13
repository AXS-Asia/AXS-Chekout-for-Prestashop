<?php

// Load composer deps bundled inside module (vendor folder)
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Encryption\Algorithm\KeyEncryption\PBES2HS512A256KW;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM;
use Jose\Component\Encryption\JWEBuilder;
use Jose\Component\Encryption\JWEDecrypter;
use Jose\Component\Encryption\JWELoader;
use Jose\Component\Encryption\Serializer\CompactSerializer;
use Jose\Component\Encryption\Serializer\JWESerializerManager;
use Jose\Component\Core\JWK;
use Jose\Component\Encryption\Compression\CompressionMethodManager;
use Jose\Component\Encryption\Compression\Deflate;

class PaymentLinkGenerator
{
    public function generatePaymentLink(string $paymentLinkId, string $clientId, string $secretKey, array $params): string
    {
        $iterationCount = 1000;
        $payload = json_encode($params);
        $encryptedPayload = $this->encryptSensitiveData($payload, $iterationCount, $secretKey, $clientId);

        return $paymentLinkId . '?data=' . $encryptedPayload;
    }

    public function encryptSensitiveData(string $payload, int $iterationCount, string $secretKey, string $keyId): string
    {
        $b64Key = rtrim(strtr(base64_encode($secretKey), '+/', '-_'), '=');

        $jwk = new JWK([
            'kty' => 'oct',
            'k' => $b64Key,
        ]);

        $keyEncryptionAlgorithmManager = new AlgorithmManager([
            new PBES2HS512A256KW(16, 1000),
        ]);

        $contentEncryptionAlgorithmManager = new AlgorithmManager([
            new A256GCM(),
        ]);

        $compression = new CompressionMethodManager([
            new Deflate(),
        ]);

        $jweBuilder = new JWEBuilder(
            $keyEncryptionAlgorithmManager,
            $contentEncryptionAlgorithmManager,
            $compression
        );

        $protectedHeader = [
            'alg' => 'PBES2-HS512+A256KW',
            'enc' => 'A256GCM',
            'kid' => $keyId,
            'p2c' => $iterationCount,
        ];

        $jwe = $jweBuilder
            ->create()
            ->withPayload($payload)
            ->withSharedProtectedHeader($protectedHeader)
            ->addRecipient($jwk)
            ->build();

        $serializer = new CompactSerializer();
        return $serializer->serialize($jwe, 0);
    }

    public function decryptJWE(string $jweToken, string $secretKey): array
    {
        $b64Key = rtrim(strtr(base64_encode($secretKey), '+/', '-_'), '=');

        $jwk = new JWK([
            'kty' => 'oct',
            'k' => $b64Key,
        ]);

        $keyEncryptionAlgorithmManager = new AlgorithmManager([
            new PBES2HS512A256KW(16, 1000),
        ]);

        $contentEncryptionAlgorithmManager = new AlgorithmManager([
            new A256GCM(),
        ]);

        $compression = new CompressionMethodManager([
            new Deflate(),
        ]);

        $jweDecrypter = new JWEDecrypter(
            $keyEncryptionAlgorithmManager,
            $contentEncryptionAlgorithmManager,
            $compression
        );

        $serializerManager = new JWESerializerManager([
            new CompactSerializer(),
        ]);

        $jweLoader = new JWELoader(
            $serializerManager,
            $jweDecrypter,
            null
        );

        try {
            $recipient = 0;
            $jwe = $jweLoader->loadAndDecryptWithKey($jweToken, $jwk, $recipient);

            $payload = $jwe->getPayload();
            $decodedPayload = json_decode($payload, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to decode JSON payload: ' . json_last_error_msg());
            }

            return [
                'success' => true,
                'payload' => $decodedPayload,
                'header' => $jwe->getSharedProtectedHeader(),
                'raw_payload' => $payload,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function extractJWEFromPaymentLink(string $paymentLink): ?string
    {
        $parsedUrl = parse_url($paymentLink);
        if (!isset($parsedUrl['query'])) {
            return null;
        }

        parse_str($parsedUrl['query'], $queryParams);
        return $queryParams['data'] ?? null;
    }
}