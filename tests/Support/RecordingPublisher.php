<?php

declare(strict_types=1);

namespace app\tests\Support;

use app\models\Comment;
use app\services\RealtimePublisherInterface;
use app\services\RealtimePublishException;

/**
 * Test double for the Centrifugo publisher, used by functional tests (config/test.php).
 *
 * It records published comments instead of sending them, and can simulate a Centrifugo
 * outage. State is static because the Yii2 Codeception module creates a new application
 * (and a new publisher) for every request.
 */
final class RecordingPublisher implements RealtimePublisherInterface
{
    /** @var list<array{id: int, author: string, text: string}> Comments "sent" since the last reset */
    public static array $published = [];

    /** When true, every publish fails as if Centrifugo were down */
    public static bool $failing = false;

    /**
     * Forgets recorded events and turns the simulated outage off. Call it in `_before()`.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$published = [];
        self::$failing = false;
    }

    /**
     * {@inheritdoc}
     */
    public function publishNewComment(Comment $comment): void
    {
        if (self::$failing) {
            throw new RealtimePublishException('Centrifugo is unreachable (simulated by RecordingPublisher)');
        }

        self::$published[] = [
            'id' => $comment->id,
            'author' => $comment->author,
            'text' => $comment->text,
        ];
    }
}
