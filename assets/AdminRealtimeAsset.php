<?php

declare(strict_types=1);

namespace app\assets;

use yii\web\AssetBundle;
use yii\web\YiiAsset;

/**
 * Scripts for the live admin comment list (WebSocket via Centrifugo).
 */
class AdminRealtimeAsset extends AssetBundle
{
    /** @var string */
    public $basePath = '@webroot';

    /** @var string */
    public $baseUrl = '@web';

    /** @var array<int, string> */
    public $js = [
        'js/vendor/centrifuge.js',
        'js/admin-realtime.js',
    ];

    /** @var array<int, class-string> YiiAsset gives yii.getCsrfToken() and the data-confirm dialogs */
    public $depends = [
        YiiAsset::class,
    ];
}
