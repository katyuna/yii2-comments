<?php

declare(strict_types=1);

namespace app\modules\admin\controllers;

use Yii;
use app\services\CentrifugoTokenIssuer;
use yii\base\Module;
use yii\web\Controller;
use yii\web\Response;

/**
 * WebSocket support for the admin pages.
 */
class RealtimeController extends Controller
{
    /**
     * @param string $id Controller ID
     * @param Module $module Owning module
     * @param CentrifugoTokenIssuer $tokenIssuer Injected by the Yii DI container
     * @param array<string, mixed> $config Object config
     */
    public function __construct(
        $id,
        $module,
        private readonly CentrifugoTokenIssuer $tokenIssuer,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Returns a fresh Centrifugo token; the browser calls it when the old token expires.
     *
     * @return Response JSON: {"token": "..."}
     */
    public function actionToken(): Response
    {
        return $this->asJson([
            'token' => $this->tokenIssuer->connectionToken((string) Yii::$app->user->id),
        ]);
    }
}
