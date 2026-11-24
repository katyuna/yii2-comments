<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\models\CommentForm;
use app\services\CommentService;
use yii\base\Module;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ErrorAction;
use yii\web\Response;

/**
 * Public pages: the comment feed with the guest comment form.
 */
class SiteController extends Controller
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
     * HTTP verb rules.
     *
     * @return array<string, mixed>
     */
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['get', 'post'],
                ],
            ],
        ];
    }

    /**
     * Standalone actions.
     *
     * @return array<string, mixed>
     */
    public function actions(): array
    {
        return [
            'error' => [
                'class' => ErrorAction::class,
            ],
        ];
    }

    /**
     * Shows the comment feed; on POST saves the guest comment.
     *
     * @return Response|string
     */
    public function actionIndex(): Response|string
    {
        $form = new CommentForm();

        if ($form->load($this->request->post())) {
            $comment = $this->commentService->create($form, (string) $this->request->userIP);

            if ($comment !== null) {
                Yii::$app->session->setFlash('success', 'Thank you! Your comment has been published.');

                // Redirect after POST, so a page refresh does not post the comment twice
                return $this->redirect(['index']);
            }
        }

        return $this->render('index', [
            'commentForm' => $form,
            'dataProvider' => $this->commentService->feedDataProvider(),
        ]);
    }
}
