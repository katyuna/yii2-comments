<?php

declare(strict_types=1);

namespace app\modules\admin\controllers;

use Yii;
use app\models\LoginForm;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * Admin login and logout.
 */
class AuthController extends Controller
{
    /**
     * Logout changes state, so it is POST only.
     *
     * @return array<string, mixed>
     */
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Shows the login form; on POST logs the admin in.
     *
     * @return Response|string
     */
    public function actionLogin(): Response|string
    {
        if (!Yii::$app->user->isGuest) {
            return $this->redirect(['/admin']);
        }

        $model = new LoginForm();

        if ($model->load($this->request->post()) && $model->login()) {
            return $this->goBack(['/admin']);
        }

        $model->password = '';

        return $this->render('login', ['model' => $model]);
    }

    /**
     * Logs the admin out and returns to the public feed.
     *
     * @return Response
     */
    public function actionLogout(): Response
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }
}
