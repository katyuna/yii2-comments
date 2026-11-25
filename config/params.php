<?php

declare(strict_types=1);

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Yii2 Comments',
    // Anti-spam: how many comments one IP may post per minute
    'commentRateLimitPerMinute' => (int) (getenv('COMMENT_RATE_LIMIT_PER_MINUTE') ?: 5),
];
