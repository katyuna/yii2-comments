<?php

declare(strict_types=1);

namespace app\tests\Unit\services;

use app\models\Comment;
use app\models\CommentForm;
use app\services\CommentService;
use app\services\RealtimePublisherInterface;
use app\services\RealtimePublishException;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Yii;
use yii\log\Logger;

/**
 * CommentService: saving a guest comment, notifying admins, rate limit and feed order
 * (checklist U-08 … U-12, U-16).
 *
 * The Centrifugo publisher is replaced by a PHPUnit mock, so no WebSocket server is needed.
 * The DB is real (app_test); the Codeception Yii2 module wraps every test in a transaction
 * and rolls it back, so each test starts with the data it creates itself.
 */
final class CommentServiceTest extends Unit
{
    /** Rate limit used by these tests, independent of .env */
    private const LIMIT = 5;

    private const IP = '10.0.0.1';

    /** @var RealtimePublisherInterface&MockObject */
    private RealtimePublisherInterface $publisher;

    private CommentService $service;

    /**
     * Creates the service with a mocked publisher and empties the comment table.
     *
     * @return void
     */
    protected function _before(): void
    {
        $this->publisher = $this->createMock(RealtimePublisherInterface::class);
        $this->service = new CommentService(self::LIMIT, $this->publisher);
        // Inside the test transaction: the rollback brings the rows back afterwards
        Comment::deleteAll();
    }

    /**
     * U-08: a valid form is saved, and admins are notified once with the saved comment.
     *
     * @flowStep 3
     * @flowStep 5
     * @return void
     */
    public function testValidCommentIsSavedAndPublishedOnce(): void
    {
        // Assert (expectation set before the action, as PHPUnit mocks require)
        $this->publisher->expects($this->once())
            ->method('publishNewComment')
            ->with($this->callback(static function (Comment $comment): bool {
                // The event needs everything the admin row shows
                return $comment->id > 0
                    && $comment->author === 'Anna'
                    && $comment->text === 'Hello'
                    && $comment->created_at > 0;
            }));

        // Act
        $comment = $this->service->create($this->form('Anna', 'Hello'), self::IP);

        // Assert
        $this->assertNotNull($comment);
        $stored = Comment::findOne($comment->id);
        $this->assertNotNull($stored, 'The comment must be in the DB');
        $this->assertSame('Anna', $stored->author);
        $this->assertSame('Hello', $stored->text);
        $this->assertSame(self::IP, $stored->ip);
    }

    /**
     * U-09: an invalid form is neither saved nor published.
     *
     * @flowStep 2
     * @flowStep 5
     * @return void
     */
    public function testInvalidCommentIsNotSavedAndNotPublished(): void
    {
        $this->publisher->expects($this->never())->method('publishNewComment');
        $form = $this->form('', '');

        $comment = $this->service->create($form, self::IP);

        $this->assertNull($comment);
        $this->assertTrue($form->hasErrors());
        $this->assertSame(0, (int) Comment::find()->count());
    }

    /**
     * U-10: if Centrifugo is down, the comment is still saved, the error is logged,
     * and no exception reaches the guest.
     *
     * @flowStep 3
     * @flowStep 5
     * @return void
     */
    public function testCommentIsSavedAndErrorLoggedWhenPublisherFails(): void
    {
        $this->publisher->method('publishNewComment')
            ->willThrowException(new RealtimePublishException('Centrifugo is unreachable'));
        // Codeception's own logger does not keep messages, so a plain Yii logger records them here
        $codeceptionLogger = Yii::getLogger();
        $logger = new Logger(['flushInterval' => 0]);
        Yii::setLogger($logger);

        try {
            // Act: must not throw
            $comment = $this->service->create($this->form('Anna', 'Hello'), self::IP);
        } finally {
            Yii::setLogger($codeceptionLogger);
        }

        $this->assertNotNull($comment, 'The comment must be saved although publishing failed');
        $this->assertNotNull(Comment::findOne($comment->id));
        $errors = array_filter($logger->messages, static fn (array $m): bool => $m[1] === Logger::LEVEL_ERROR);
        $this->assertContains(
            'Centrifugo is unreachable',
            array_column($errors, 0),
            'The publishing error must be logged',
        );
    }

    /**
     * U-11: one IP may post LIMIT comments per minute; the next one is refused.
     * Comments older than a minute no longer count.
     *
     * The service reads the time with time(), so "a minute later" is simulated by
     * comments whose created_at is 61 seconds in the past.
     *
     * @flowStep 2
     * @return void
     */
    public function testRateLimitRefusesCommentsOverTheLimitPerMinute(): void
    {
        for ($i = 1; $i <= self::LIMIT; $i++) {
            $this->assertNotNull(
                $this->service->create($this->form('Anna', "Comment {$i}"), self::IP),
                "Comment {$i} of " . self::LIMIT . ' must be allowed',
            );
        }

        $form = $this->form('Anna', 'One too many');
        $refused = $this->service->create($form, self::IP);

        $this->assertNull($refused, 'Comment number ' . (self::LIMIT + 1) . ' within a minute must be refused');
        $this->assertStringContainsString('too often', (string) $form->getFirstError('text'));
        $this->assertSame(self::LIMIT, (int) Comment::find()->count());
    }

    /**
     * U-11 (second half): after a minute the same IP may post again.
     *
     * @flowStep 2
     * @return void
     */
    public function testRateLimitAllowsPostingAgainAfterAMinute(): void
    {
        $this->insertComments(self::LIMIT, self::IP, time() - 61);

        $comment = $this->service->create($this->form('Anna', 'A minute later'), self::IP);

        $this->assertNotNull($comment, 'Comments older than 60 s must not count against the limit');
    }

    /**
     * U-12: the limit is counted per IP, so one spammer does not block other guests.
     *
     * @flowStep 2
     * @return void
     */
    public function testRateLimitIsCountedPerIp(): void
    {
        $this->insertComments(self::LIMIT, self::IP, time());

        $comment = $this->service->create($this->form('Bob', 'Hello'), '10.0.0.2');

        $this->assertNotNull($comment, 'Another IP must not be limited');
    }

    /**
     * U-16: the feed and the admin list show the newest comments first;
     * comments posted in the same second are ordered by id.
     *
     * @flowStep 4
     * @return void
     */
    public function testFeedAndAdminListShowNewestFirst(): void
    {
        $now = time();
        $old = $this->insertComment('old', $now - 100);
        $sameSecondFirst = $this->insertComment('same second, first', $now);
        $sameSecondSecond = $this->insertComment('same second, second', $now);
        $middle = $this->insertComment('middle', $now - 50);
        $expected = [$sameSecondSecond, $sameSecondFirst, $middle, $old];

        $feedIds = array_map(static fn (Comment $c): int => $c->id, $this->service->feedDataProvider()->getModels());
        $adminIds = array_map(static fn (Comment $c): int => $c->id, $this->service->adminDataProvider()->getModels());

        $this->assertSame($expected, $feedIds);
        $this->assertSame($expected, $adminIds);
    }

    /**
     * Builds a guest form from request-like data.
     *
     * @param string $author Name
     * @param string $text Comment text
     * @return CommentForm
     */
    private function form(string $author, string $text): CommentForm
    {
        $form = new CommentForm();
        $form->load(['author' => $author, 'text' => $text], '');

        return $form;
    }

    /**
     * Inserts several comments from one IP with a given time, bypassing the service.
     *
     * @param int $count How many
     * @param string $ip Client IP
     * @param int $createdAt Unix time
     * @return void
     */
    private function insertComments(int $count, string $ip, int $createdAt): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->insertComment("Comment {$i}", $createdAt, $ip);
        }
    }

    /**
     * Inserts one comment with a fixed time (TimestampBehavior would set "now").
     *
     * @param string $text Comment text
     * @param int $createdAt Unix time
     * @param string $ip Client IP
     * @return int Id of the new row
     */
    private function insertComment(string $text, int $createdAt, string $ip = '127.0.0.1'): int
    {
        Yii::$app->db->createCommand()->insert(Comment::tableName(), [
            'author' => 'Test',
            'text' => $text,
            'ip' => $ip,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->execute();

        return (int) Yii::$app->db->getLastInsertID();
    }
}
