<?php

declare(strict_types=1);

namespace app\modules\admin\controllers;

use Yii;
use app\models\Comment;
use app\services\CommentService;
use yii\base\Module;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Admin list of comments with edit and delete.
 */
class CommentController extends Controller
{
    /**
     * @param string $id Controller ID
     * @param Module $module Owning module
     * @param CommentService $commentService Injected by the Yii DI container
     * @param array<string, mixed> $config Object config
     */
    public function __construct(
        $id,
        $module,
        private readonly CommentService $commentService,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Delete changes data, so it is POST only (a link or a crawler cannot delete).
     *
     * @return array<string, mixed>
     */
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Lists all comments, newest first.
     *
     * @return string
     */
    public function actionIndex(): string
    {
        return $this->render('index', [
            'dataProvider' => $this->commentService->adminDataProvider(),
        ]);
    }

    /**
     * Shows the edit form; on POST saves the comment.
     *
     * @param int $id Comment ID
     * @return Response|string
     * @throws NotFoundHttpException If the comment does not exist
     */
    public function actionUpdate(int $id): Response|string
    {
        $comment = $this->findComment($id);

        if ($this->request->isPost && $this->commentService->update($comment, $this->request->post())) {
            Yii::$app->session->setFlash('success', "Comment #{$comment->id} has been updated.");

            return $this->redirect(['index']);
        }

        return $this->render('update', ['comment' => $comment]);
    }

    /**
     * Deletes the comment.
     *
     * @param int $id Comment ID
     * @return Response
     * @throws NotFoundHttpException If the comment does not exist
     */
    public function actionDelete(int $id): Response
    {
        $this->commentService->delete($this->findComment($id));
        Yii::$app->session->setFlash('success', "Comment #{$id} has been deleted.");

        return $this->redirect(['index']);
    }

    /**
     * Finds the comment or shows a 404 page.
     *
     * @param int $id Comment ID
     * @return Comment
     * @throws NotFoundHttpException If the comment does not exist
     */
    private function findComment(int $id): Comment
    {
        return $this->commentService->findById($id)
            ?? throw new NotFoundHttpException('The comment does not exist.');
    }
}
