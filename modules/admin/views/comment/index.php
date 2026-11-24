<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string $wsToken Centrifugo connection token */
/** @var string $wsChannel Channel with new comments */

use app\assets\AdminRealtimeAsset;
use app\models\Comment;
use yii\bootstrap5\LinkPager;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Comments';
AdminRealtimeAsset::register($this);

/** @var Comment[] $comments */
$comments = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
// New comments are inserted at the top, so only the first page shows them live
$isFirstPage = $pagination === false || $pagination->getPage() === 0;
?>
<div class="admin-comment-index">
    <h1 class="h3 mb-3 d-flex align-items-center gap-2">
        <?= Html::encode($this->title) ?>
        <span class="badge text-bg-secondary" data-testid="admin-comment-count"><?= $dataProvider->getTotalCount() ?></span>
        <span class="badge text-bg-secondary fs-6 ms-auto" data-testid="realtime-status" data-state="connecting"
              title="Real-time updates over WebSocket">Connecting…</span>
    </h1>

    <div class="table-responsive">
        <?= Html::beginTag('table', [
            'class' => 'table table-striped align-middle',
            'data' => [
                'testid' => 'admin-comment-list',
                'ws-token' => $wsToken,
                'ws-channel' => $wsChannel,
                'token-url' => Url::to(['realtime/token']),
                'update-url' => Url::to(['update', 'id' => '__ID__']),
                'delete-url' => Url::to(['delete', 'id' => '__ID__']),
                'live-insert' => $isFirstPage ? '1' : '0',
            ],
        ]) ?>
            <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">Name</th>
                <th scope="col">Comment</th>
                <th scope="col">Posted</th>
                <th scope="col">IP</th>
                <th scope="col" class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($comments === []): ?>
                <tr data-testid="admin-comment-list-empty">
                    <td colspan="6" class="text-center text-body-secondary">No comments yet.</td>
                </tr>
            <?php endif ?>

            <?php foreach ($comments as $comment): ?>
                <tr data-testid="admin-comment-row" data-comment-id="<?= $comment->id ?>">
                    <td data-testid="admin-comment-id"><?= $comment->id ?></td>
                    <td data-testid="admin-comment-author"><?= Html::encode($comment->author) ?></td>
                    <td class="comment-text" data-testid="admin-comment-text"><?= Html::encode($comment->text) ?></td>
                    <td class="text-nowrap" data-testid="admin-comment-date"><?= Html::encode(Yii::$app->formatter->asDatetime($comment->created_at)) ?></td>
                    <td class="text-nowrap small text-body-secondary"><?= Html::encode($comment->ip) ?></td>
                    <td class="text-end text-nowrap">
                        <?= Html::a('Edit', ['update', 'id' => $comment->id], [
                            'class' => 'btn btn-sm btn-outline-primary',
                            'data-testid' => 'admin-comment-edit',
                        ]) ?>
                        <?= Html::beginForm(['delete', 'id' => $comment->id], 'post', ['class' => 'd-inline']) ?>
                        <?= Html::submitButton('Delete', [
                            'class' => 'btn btn-sm btn-outline-danger',
                            'data-confirm' => 'Delete this comment?',
                            'data-testid' => 'admin-comment-delete',
                        ]) ?>
                        <?= Html::endForm() ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?= LinkPager::widget([
        'pagination' => $dataProvider->getPagination(),
        'options' => ['data-testid' => 'admin-comment-pager'],
    ]) ?>
</div>
