<?php

declare(strict_types=1);

$db = require __DIR__ . '/db.php';
// Tests always use the separate app_test database, never the app data
$db['dsn'] = sprintf(
    'mysql:host=%s;dbname=%s',
    getenv('DB_HOST') ?: 'mysql',
    getenv('DB_TEST_NAME') ?: 'app_test',
);
$db['enableSchemaCache'] = false;

return $db;
