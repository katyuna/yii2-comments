<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/test_db.php';

/**
 * Application configuration shared by all test types
 */
return [
    'id' => 'yii2-comments-tests',
    'name' => 'Yii2 Comments',
    'timeZone' => 'UTC',
    'basePath' => dirname(__DIR__),
    'bootstrap' => [
        \app\tests\Support\MailerBootstrap::class,
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
    'language' => 'en-US',
    'container' => [
        'singletons' => [
            \app\services\CommentService::class => [
                '__construct()' => [$params['commentRateLimitPerMinute']],
            ],
            // Functional tests must not send events to a real Centrifugo; the fake records them instead
            \app\services\RealtimePublisherInterface::class => \app\tests\Support\RecordingPublisher::class,
            \app\services\CentrifugoTokenIssuer::class => [
                '__construct()' => [$params['centrifugo']['tokenSecret'], $params['centrifugo']['tokenTtl']],
            ],
        ],
    ],
    'components' => [
        'db' => $db,
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'messageClass' => \yii\symfonymailer\Message::class,
            'useFileTransport' => true,
            'viewPath' => '@app/mail',
        ],
        'assetManager' => [
            'basePath' => __DIR__ . '/../web/assets',
        ],
        'urlManager' => [
            'showScriptName' => true,
        ],
        'user' => [
            'identityClass' => \app\models\User::class,
            'loginUrl' => ['/admin/auth/login'],
        ],
        'request' => [
            'cookieValidationKey' => 'test',
            // CSRF validation stays on (Yii default), so tests check the real form protection
        ],
    ],
    'modules' => [
        'admin' => [
            'class' => \app\modules\admin\Module::class,
        ],
    ],
    'params' => $params,
];
