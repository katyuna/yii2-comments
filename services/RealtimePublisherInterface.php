<?php

declare(strict_types=1);

namespace app\services;

use app\models\Comment;

/**
 * Sends real-time events to the browsers of logged-in admins.
 *
 * An interface, so unit tests can replace the real WebSocket server with a mock.
 */
interface RealtimePublisherInterface
{
    /** Channel the admin comment list listens to ("admin:" namespace requires a JWT) */
    public const ADMIN_COMMENTS_CHANNEL = 'admin:comments';

    /**
     * Tells the admins that a new comment has been posted.
     *
     * @param Comment $comment The saved comment
     * @return void
     * @throws RealtimePublishException If the event could not be sent
     */
    public function publishNewComment(Comment $comment): void;
}
