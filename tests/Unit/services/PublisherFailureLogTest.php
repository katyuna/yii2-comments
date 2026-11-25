<?php

declare(strict_types=1);

namespace app\tests\Unit\services;

use app\models\Comment;
use app\models\CommentForm;
use app\services\CommentService;
use app\services\RealtimePublisherInterface;
use app\services\RealtimePublishException;
use Codeception\Test\Unit;
use Yii;
use yii\log\Dispatcher;
use yii\log\FileTarget;
use yii\log\Logger;

/**
 * Regression test (checklist R-04): when Centrifugo is down the comment is saved and the
 * error lands in the log file, as was checked by hand in phase 1.
 *
 * The web app writes errors with a FileTarget to runtime/logs/app.log. Here the same kind
 * of target writes to a temporary file, so the test does not depend on the shared app.log.
 *
 * @group regression
 * @flowStep 3
 * @flowStep 5
 */
final class PublisherFailureLogTest extends Unit
{
    private string $logFile;

    /**
     * Chooses a fresh temporary log file.
     *
     * @return void
     */
    protected function _before(): void
    {
        $this->logFile = sys_get_temp_dir() . '/r04-' . bin2hex(random_bytes(4)) . '.log';
    }

    /**
     * Removes the temporary log file.
     *
     * @return void
     */
    protected function _after(): void
    {
        if (is_file($this->logFile)) {
            unlink($this->logFile);
        }
    }

    /**
     * R-04: Centrifugo unreachable → the comment is in the DB and the error is in the log file.
     *
     * @return void
     */
    public function testCommentIsSavedAndErrorIsWrittenToLogFile(): void
    {
        // Arrange: a publisher that fails like an unreachable Centrifugo, and a file log
        $publisher = $this->createMock(RealtimePublisherInterface::class);
        $publisher->method('publishNewComment')
            ->willThrowException(new RealtimePublishException('Centrifugo is unreachable: Connection refused'));
        $codeceptionLogger = Yii::getLogger();
        $logger = new Logger();
        $logger->dispatcher = new Dispatcher([
            'logger' => $logger,
            'targets' => [new FileTarget(['logFile' => $this->logFile, 'levels' => ['error'], 'logVars' => []])],
        ]);
        Yii::setLogger($logger);
        $form = new CommentForm();
        $form->load(['author' => 'Anna', 'text' => 'Saved although Centrifugo is down'], '');

        try {
            // Act
            $comment = (new CommentService(5, $publisher))->create($form, '10.0.0.4');
            $logger->flush(true);
        } finally {
            Yii::setLogger($codeceptionLogger);
        }

        // Assert
        $this->assertNotNull($comment);
        $this->assertNotNull(Comment::findOne($comment->id), 'The comment must be in the DB');
        $this->assertFileExists($this->logFile);
        $log = (string) file_get_contents($this->logFile);
        $this->assertStringContainsString('[error]', $log);
        $this->assertStringContainsString('Centrifugo is unreachable', $log);
    }

    /**
     * R-04: the web app really has such a file target for errors (config/web.php).
     *
     * @return void
     */
    public function testWebConfigWritesErrorsToAFile(): void
    {
        $config = require codecept_root_dir('config/web.php');

        $fileTargets = array_filter(
            $config['components']['log']['targets'],
            static fn (array $target): bool => $target['class'] === FileTarget::class,
        );

        $this->assertNotEmpty($fileTargets, 'Errors must be written to a log file');
        $this->assertContains('error', array_values($fileTargets)[0]['levels']);
    }
}
