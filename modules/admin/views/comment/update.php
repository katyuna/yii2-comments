<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\Comment $comment */

use app\models\Comment;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

$this->title = "Edit comment #{$comment->id}";
$this->params['breadcrumbs'][] = ['label' => 'Comments', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="admin-comment-update row">
    <div class="col-lg-8">
        <h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>

        <?php $form = ActiveForm::begin([
            'id' => 'admin-comment-form',
            'options' => ['data-testid' => 'admin-comment-form'],
        ]) ?>

        <?= $form->field($comment, 'author', [
            'errorOptions' => ['class' => 'invalid-feedback', 'data-testid' => 'admin-comment-form-author-error'],
        ])->textInput([
            'maxlength' => Comment::AUTHOR_MAX_LENGTH,
            'data-testid' => 'admin-comment-form-author',
        ]) ?>

        <?= $form->field($comment, 'text', [
            'errorOptions' => ['class' => 'invalid-feedback', 'data-testid' => 'admin-comment-form-text-error'],
        ])->textarea([
            'rows' => 6,
            'maxlength' => Comment::TEXT_MAX_LENGTH,
            'data-testid' => 'admin-comment-form-text',
        ]) ?>

        <?= Html::submitButton('Save', [
            'class' => 'btn btn-primary',
            'data-testid' => 'admin-comment-form-submit',
        ]) ?>
        <?= Html::a('Cancel', ['index'], [
            'class' => 'btn btn-link',
            'data-testid' => 'admin-comment-form-cancel',
        ]) ?>

        <?php ActiveForm::end() ?>
    </div>
</div>
