<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->title = 'Admin';
// The module allows only logged-in users here, so the identity is always set
/** @var app\models\User $admin */
$admin = Yii::$app->user->identity;
?>
<div class="admin-default-index">
    <h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
    <p data-testid="admin-welcome">
        Logged in as <strong><?= Html::encode($admin->username) ?></strong>.
    </p>
</div>
