<?php

declare(strict_types=1);

namespace app\modules\admin;

use yii\filters\AccessControl;
use yii\web\ForbiddenHttpException;

/**
 * Admin area (/admin). Every page except the login form requires a logged-in admin.
 */
class Module extends \yii\base\Module
{
    /** @var string */
    public $controllerNamespace = 'app\modules\admin\controllers';

    /** @var string */
    public $defaultRoute = 'comment';

    /**
     * Access rules for the whole module: guests may only open the login form.
     *
     * @return array<string, mixed>
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'controllers' => ['admin/auth'],
                        'actions' => ['login'],
                    ],
                    [
                        // Called by JavaScript: a 403 tells the WebSocket client to stop, a redirect would not
                        'allow' => false,
                        'controllers' => ['admin/realtime'],
                        'roles' => ['?'],
                        'denyCallback' => static function (): void {
                            throw new ForbiddenHttpException('Log in as admin to receive real-time updates.');
                        },
                    ],
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }
}
