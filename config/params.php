<?php

declare(strict_types=1);

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Yii2 Comments',
    // Anti-spam: how many comments one IP may post per minute
    'commentRateLimitPerMinute' => (int) (getenv('COMMENT_RATE_LIMIT_PER_MINUTE') ?: 5),
    // WebSocket server: PHP publishes events over its HTTP API, browsers connect with a JWT
    'centrifugo' => [
        'apiUrl' => getenv('CENTRIFUGO_API_URL') ?: 'http://centrifugo:8000/api',
        'apiKey' => (string) getenv('CENTRIFUGO_API_KEY'),
        'tokenSecret' => (string) getenv('CENTRIFUGO_TOKEN_SECRET'),
        // Seconds a WebSocket token is valid; a logged-out admin's other tabs stop within this time
        'tokenTtl' => (int) (getenv('CENTRIFUGO_TOKEN_TTL') ?: 3600),
    ],
];
