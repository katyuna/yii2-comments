<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\CommentForm $commentForm */
/** @var yii\data\ActiveDataProvider $dataProvider */

use app\models\Comment;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\LinkPager;
use yii\helpers\Html;

$this->title = 'Comments';

/** @var Comment[] $comments */
$comments = $dataProvider->getModels();
?>
<div class="site-index row justify-content-center">
    <div class="col-lg-8">
        <h1 class="h3 mb-4"><?= Html::encode($this->title) ?></h1>

        <section class="card mb-5" aria-labelledby="comment-form-title">
            <div class="card-body">
                <h2 id="comment-form-title" class="h5 card-title">Leave a comment</h2>

                <?php $form = ActiveForm::begin([
                    'id' => 'comment-form',
                    'options' => ['data-testid' => 'comment-form'],
                ]) ?>

                <?= $form->field($commentForm, 'author', [
                    'errorOptions' => ['class' => 'invalid-feedback', 'data-testid' => 'comment-form-author-error'],
                ])->textInput([
                    'maxlength' => Comment::AUTHOR_MAX_LENGTH,
                    'data-testid' => 'comment-form-author',
                ]) ?>

                <?= $form->field($commentForm, 'text', [
                    'errorOptions' => ['class' => 'invalid-feedback', 'data-testid' => 'comment-form-text-error'],
                ])->textarea([
                    'rows' => 4,
                    'maxlength' => Comment::TEXT_MAX_LENGTH,
                    'data-testid' => 'comment-form-text',
                ]) ?>

                <?php // Honeypot: hidden from people, bots that fill every field get rejected ?>
                <div class="d-none" aria-hidden="true">
                    <?= $form->field($commentForm, 'website', [
                        'errorOptions' => ['class' => 'invalid-feedback', 'data-testid' => 'comment-form-website-error'],
                    ])->textInput([
                        'tabindex' => '-1',
                        'autocomplete' => 'off',
                        'data-testid' => 'comment-form-website',
                    ]) ?>
                </div>

                <?= Html::submitButton('Post comment', [
                    'class' => 'btn btn-primary',
                    'data-testid' => 'comment-form-submit',
                ]) ?>

                <?php ActiveForm::end() ?>
            </div>
        </section>

        <section aria-labelledby="comment-feed-title">
            <h2 id="comment-feed-title" class="h5 mb-3">
                Latest comments
                <span class="badge text-bg-secondary" data-testid="comment-feed-count"><?= $dataProvider->getTotalCount() ?></span>
            </h2>

            <div data-testid="comment-feed">
                <?php if ($comments === []): ?>
                    <p class="text-body-secondary" data-testid="comment-feed-empty">No comments yet. Be the first!</p>
                <?php endif ?>

                <?php foreach ($comments as $comment): ?>
                    <article class="card mb-3" id="comment-<?= $comment->id ?>"
                             data-testid="comment-item" data-comment-id="<?= $comment->id ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-baseline mb-2">
                                <strong data-testid="comment-item-author"><?= Html::encode($comment->author) ?></strong>
                                <time class="small text-body-secondary"
                                      datetime="<?= Html::encode(date(DATE_ATOM, $comment->created_at)) ?>"
                                      data-testid="comment-item-date"><?= Html::encode(Yii::$app->formatter->asDatetime($comment->created_at)) ?></time>
                            </div>
                            <p class="card-text mb-0 comment-text" data-testid="comment-item-text"><?= Html::encode($comment->text) ?></p>
                        </div>
                    </article>
                <?php endforeach ?>
            </div>

            <?= LinkPager::widget([
                'pagination' => $dataProvider->getPagination(),
                'options' => ['data-testid' => 'comment-feed-pager'],
            ]) ?>
        </section>
    </div>
</div>
