<?php

declare(strict_types=1);

namespace app\services;

use Yii;
use app\models\Comment;

/**
 * Publishes real-time events through the Centrifugo HTTP API.
 */
class RealtimePublisher implements RealtimePublisherInterface
{
    // Short timeout: the guest waits for this request while posting a comment
    private const TIMEOUT_SECONDS = 2;

    /**
     * @param string $apiUrl Centrifugo HTTP API base URL, e.g. http://centrifugo:8000/api
     * @param string $apiKey Centrifugo API key
     */
    public function __construct(
        private readonly string $apiUrl,
        private readonly string $apiKey,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function publishNewComment(Comment $comment): void
    {
        $this->publish(self::ADMIN_COMMENTS_CHANNEL, [
            'id' => $comment->id,
            'author' => $comment->author,
            'text' => $comment->text,
            'ip' => $comment->ip,
            'createdAt' => Yii::$app->formatter->asDatetime($comment->created_at),
        ]);
    }

    /**
     * Sends one publication to Centrifugo.
     *
     * @param string $channel Channel name
     * @param array<string, mixed> $data Event payload
     * @return void
     * @throws RealtimePublishException If Centrifugo is unreachable or returns an error
     */
    private function publish(string $channel, array $data): void
    {
        $body = json_encode(
            ['channel' => $channel, 'data' => $data],
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE,
        );

        $curl = curl_init(rtrim($this->apiUrl, '/') . '/publish');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-Key: ' . $this->apiKey,
            ],
        ]);

        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if (!is_string($response)) {
            throw new RealtimePublishException('Centrifugo is unreachable: ' . curl_error($curl));
        }

        if ($status !== 200) {
            throw new RealtimePublishException("Centrifugo API returned HTTP {$status}: {$response}");
        }

        // Centrifugo reports API errors with HTTP 200 and an "error" object
        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded['error'])) {
            throw new RealtimePublishException('Centrifugo API error: ' . json_encode($decoded['error']));
        }
    }
}
