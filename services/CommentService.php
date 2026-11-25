<?php

declare(strict_types=1);

namespace app\services;

use app\models\Comment;
use app\models\CommentForm;
use yii\data\ActiveDataProvider;

/**
 * Business logic for comments: creating them from the guest form, listing, editing and deleting.
 */
class CommentService
{
    private const RATE_LIMIT_WINDOW_SECONDS = 60;
    private const FEED_PAGE_SIZE = 20;
    private const ADMIN_PAGE_SIZE = 50;

    /**
     * @param int $rateLimitPerMinute Max comments one IP may post per minute
     */
    public function __construct(private readonly int $rateLimitPerMinute)
    {
    }

    /**
     * Validates the guest form and saves a new comment.
     *
     * Validation and rate limit errors are added to the form.
     *
     * @param CommentForm $form Loaded guest form
     * @param string $ip Client IP, used for the rate limit
     * @return Comment|null The saved comment, or null if it was not saved
     */
    public function create(CommentForm $form, string $ip): ?Comment
    {
        if (!$form->validate()) {
            return null;
        }

        if ($this->isRateLimited($ip)) {
            $form->addError('text', 'You are posting too often. Please wait a minute and try again.');

            return null;
        }

        $comment = new Comment();
        $comment->author = (string) $form->author;
        $comment->text = (string) $form->text;
        $comment->ip = $ip;

        if (!$comment->save()) {
            $form->addErrors($comment->getErrors());

            return null;
        }

        return $comment;
    }

    /**
     * Data provider for the public feed: newest comments first, paginated.
     *
     * @return ActiveDataProvider
     */
    public function feedDataProvider(): ActiveDataProvider
    {
        return $this->newestFirst(self::FEED_PAGE_SIZE);
    }

    /**
     * Data provider for the admin list: newest comments first, paginated.
     *
     * @return ActiveDataProvider
     */
    public function adminDataProvider(): ActiveDataProvider
    {
        return $this->newestFirst(self::ADMIN_PAGE_SIZE);
    }

    /**
     * Finds a comment by ID.
     *
     * @param int $id Comment ID
     * @return Comment|null
     */
    public function findById(int $id): ?Comment
    {
        return Comment::findOne(['id' => $id]);
    }

    /**
     * Updates a comment from the admin edit form.
     *
     * Validation errors stay in the model.
     *
     * @param Comment $comment Comment to update
     * @param array<string, mixed> $data POST data of the edit form
     * @return bool Whether the comment was loaded and saved
     */
    public function update(Comment $comment, array $data): bool
    {
        return $comment->load($data) && $comment->save();
    }

    /**
     * Deletes a comment.
     *
     * @param Comment $comment Comment to delete
     * @return void
     */
    public function delete(Comment $comment): void
    {
        $comment->delete();
    }

    /**
     * Builds a paginated query of comments, newest first.
     *
     * @param int $pageSize Comments per page
     * @return ActiveDataProvider
     */
    private function newestFirst(int $pageSize): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => Comment::find()->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC]),
            'pagination' => ['pageSize' => $pageSize],
            'sort' => false,
        ]);
    }

    /**
     * Checks whether this IP has already posted the allowed number of comments in the last minute.
     *
     * @param string $ip Client IP
     * @return bool
     */
    private function isRateLimited(string $ip): bool
    {
        $recentCount = (int) Comment::find()
            ->where(['ip' => $ip])
            ->andWhere(['>=', 'created_at', time() - self::RATE_LIMIT_WINDOW_SECONDS])
            ->count();

        return $recentCount >= $this->rateLimitPerMinute;
    }
}
