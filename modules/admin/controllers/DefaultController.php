<?php

declare(strict_types=1);

namespace app\modules\admin\controllers;

use yii\web\Controller;

/**
 * Admin home page.
 */
class DefaultController extends Controller
{
    /**
     * Shows the admin home page.
     *
     * @return string
     */
    public function actionIndex(): string
    {
        return $this->render('index');
    }
}
