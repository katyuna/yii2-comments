<?php

declare(strict_types=1);

namespace app\tests\Unit\services;

use app\services\CentrifugoTokenIssuer;
use Codeception\Test\Unit;
use yii\base\InvalidConfigException;

/**
 * JWT for the Centrifugo connection (checklist U-15).
 *
 * Centrifugo checks the HS256 signature with the shared secret. The test decodes the token
 * by hand (it is just three base64url parts), so it does not need a JWT library.
 *
 * @flowStep 7
 */
final class CentrifugoTokenIssuerTest extends Unit
{
    private const SECRET = 'unit-test-secret';

    /**
     * U-15: the token has the admin id in "sub" and an expiry in the future.
     *
     * @return void
     */
    public function testTokenContainsAdminIdAndExpiry(): void
    {
        $before = time();

        $token = (new CentrifugoTokenIssuer(self::SECRET))->connectionToken('42');

        [$header, $claims] = $this->decode($token);
        $this->assertSame(['alg' => 'HS256', 'typ' => 'JWT'], $header);
        $this->assertSame('42', $claims['sub']);
        $this->assertGreaterThanOrEqual($before, $claims['iat']);
        $this->assertGreaterThan($claims['iat'], $claims['exp'], 'The token must expire after it is issued');
    }

    /**
     * U-15: the signature is valid with our secret and invalid with any other,
     * so nobody without the secret can forge a token for the admin channel.
     *
     * @return void
     */
    public function testSignatureMatchesOnlyTheConfiguredSecret(): void
    {
        $token = (new CentrifugoTokenIssuer(self::SECRET))->connectionToken('42');

        $this->assertTrue($this->hasValidSignature($token, self::SECRET));
        $this->assertFalse($this->hasValidSignature($token, 'another-secret'));
    }

    /**
     * U-15: without a secret the issuer refuses to make unsigned-looking tokens.
     *
     * @return void
     */
    public function testEmptySecretIsAConfigurationError(): void
    {
        $this->expectException(InvalidConfigException::class);

        (new CentrifugoTokenIssuer(''))->connectionToken('42');
    }

    /**
     * Decodes header and claims of a JWT.
     *
     * @param string $token JWT
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function decode(string $token): array
    {
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'A JWT has three parts');

        return [
            json_decode($this->base64UrlDecode($parts[0]), true, flags: JSON_THROW_ON_ERROR),
            json_decode($this->base64UrlDecode($parts[1]), true, flags: JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * Checks the HS256 signature the way Centrifugo does.
     *
     * @param string $token JWT
     * @param string $secret Secret to check with
     * @return bool
     */
    private function hasValidSignature(string $token, string $secret): bool
    {
        [$header, $claims, $signature] = explode('.', $token);
        $expected = hash_hmac('sha256', "{$header}.{$claims}", $secret, true);

        return hash_equals($expected, $this->base64UrlDecode($signature));
    }

    /**
     * Base64url without padding → raw bytes.
     *
     * @param string $data Encoded part
     * @return string
     */
    private function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}
