<?php

declare(strict_types=1);

// Connection settings come from .env (passed into the container by docker compose)
return [
    'class' => \yii\db\Connection::class,
    'dsn' => sprintf(
        'mysql:host=%s;dbname=%s',
        getenv('DB_HOST') ?: 'mysql',
        getenv('DB_NAME') ?: 'app',
    ),
    'username' => getenv('DB_USER') ?: 'app',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
    'enableSchemaCache' => !YII_DEBUG,
    'schemaCacheDuration' => 3600,
    'schemaCache' => 'cache',
];
