<?php

declare(strict_types=1);

namespace app\services;

use yii\base\InvalidConfigException;

/**
 * Issues connection JWTs for Centrifugo.
 *
 * Centrifugo accepts WebSocket connections only with a valid token, and the app gives
 * tokens only to logged-in admins, so guests cannot listen to the admin channel.
 */
class CentrifugoTokenIssuer
{
    /** Default token lifetime: one hour */
    public const DEFAULT_TTL_SECONDS = 3600;

    /**
     * @param string $secret HMAC secret shared with Centrifugo (CENTRIFUGO_TOKEN_SECRET)
     * @param int $ttlSeconds Token lifetime (CENTRIFUGO_TOKEN_TTL). After it the browser must ask
     *                        for a new token, so a logged-out admin stops receiving events within it.
     */
    public function __construct(
        private readonly string $secret,
        private readonly int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ) {
    }

    /**
     * Creates a connection token for the user.
     *
     * @param string $userId ID of the logged-in admin
     * @return string JWT signed with HS256
     * @throws InvalidConfigException If the secret is not configured
     */
    public function connectionToken(string $userId): string
    {
        if ($this->secret === '') {
            throw new InvalidConfigException('Set CENTRIFUGO_TOKEN_SECRET in .env.');
        }

        $now = time();

        return $this->sign([
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
        ]);
    }

    /**
     * Builds a JWT: base64url(header).base64url(claims).base64url(signature).
     *
     * @param array<string, int|string> $claims Token claims
     * @return string
     */
    private function sign(array $claims): string
    {
        $segments = [
            self::base64Url((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT'])),
            self::base64Url((string) json_encode($claims)),
        ];
        $segments[] = self::base64Url(hash_hmac('sha256', implode('.', $segments), $this->secret, true));

        return implode('.', $segments);
    }

    /**
     * URL-safe base64 without padding, as JWT requires.
     *
     * @param string $data Raw bytes
     * @return string
     */
    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
