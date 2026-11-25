<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\LoginForm $model */

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

$this->title = 'Admin login';
?>
<div class="admin-auth-login row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card">
            <div class="card-body">
                <h1 class="h4 card-title mb-3"><?= Html::encode($this->title) ?></h1>

                <?php $form = ActiveForm::begin([
                    'id' => 'login-form',
                    'options' => ['data-testid' => 'login-form'],
                ]) ?>

                <?= $form->field($model, 'username', [
                    'errorOptions' => ['class' => 'invalid-feedback', 'data-testid' => 'login-username-error'],
                ])->textInput([
                    'autofocus' => true,
                    'autocomplete' => 'username',
                    'data-testid' => 'login-username',
                ]) ?>

                <?= $form->field($model, 'password', [
                    'errorOptions' => ['class' => 'invalid-feedback', 'data-testid' => 'login-password-error'],
                ])->passwordInput([
                    'autocomplete' => 'current-password',
                    'data-testid' => 'login-password',
                ]) ?>

                <?= $form->field($model, 'rememberMe')->checkbox(['data-testid' => 'login-remember']) ?>

                <?= Html::submitButton('Log in', [
                    'class' => 'btn btn-primary w-100',
                    'data-testid' => 'login-submit',
                ]) ?>

                <?php ActiveForm::end() ?>
            </div>
        </div>
    </div>
</div>
