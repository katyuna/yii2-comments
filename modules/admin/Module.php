<?php

declare(strict_types=1);

namespace app\modules\admin;

use yii\filters\AccessControl;

/**
 * Admin area (/admin). Every page except the login form requires a logged-in admin.
 */
class Module extends \yii\base\Module
{
    /** @var string */
    public $controllerNamespace = 'app\modules\admin\controllers';

    /** @var string */
    public $defaultRoute = 'default';

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
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }
}
