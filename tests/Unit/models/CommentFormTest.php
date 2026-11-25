<?php

declare(strict_types=1);

namespace app\tests\Unit\models;

use app\models\Comment;
use app\models\CommentForm;
use Codeception\Test\Unit;

/**
 * Validation rules of the guest comment form (checklist U-01 … U-07).
 *
 * Pure model tests: no DB, no HTTP. Each test builds a form, calls validate()
 * and checks which attribute got an error.
 *
 * @flowStep 2
 */
final class CommentFormTest extends Unit
{
    /**
     * U-01: a normal name and text pass validation.
     *
     * @return void
     */
    public function testValidNameAndTextPassValidation(): void
    {
        // Arrange
        $form = $this->form('Anna', 'Nice article, thank you!');

        // Act
        $isValid = $form->validate();

        // Assert
        $this->assertTrue($isValid);
        $this->assertSame([], $form->getErrors());
    }

    /**
     * U-02: an empty name, or a name of spaces only, is rejected.
     *
     * Spaces must be trimmed before the "required" check, otherwise "   " would count as a name.
     *
     * @dataProvider blankValues
     * @param string $author Blank name
     * @return void
     */
    public function testBlankNameIsRejected(string $author): void
    {
        $form = $this->form($author, 'Some text');

        $this->assertFalse($form->validate());
        $this->assertTrue($form->hasErrors('author'));
        $this->assertFalse($form->hasErrors('text'));
    }

    /**
     * U-03: an empty text, or a text of spaces and line breaks only, is rejected.
     *
     * @dataProvider blankValues
     * @param string $text Blank text
     * @return void
     */
    public function testBlankTextIsRejected(string $text): void
    {
        $form = $this->form('Anna', $text);

        $this->assertFalse($form->validate());
        $this->assertTrue($form->hasErrors('text'));
        $this->assertFalse($form->hasErrors('author'));
    }

    /**
     * Values that look empty after trimming.
     *
     * @return array<string, array{string}>
     */
    public static function blankValues(): array
    {
        return [
            'empty string' => [''],
            'spaces' => ['   '],
            'tabs and line breaks' => ["\t\n \r\n"],
        ];
    }

    /**
     * U-04: the name may be exactly 64 characters, 65 is too long.
     *
     * The browser's maxlength can be bypassed, so the server must check the limit itself.
     *
     * @return void
     */
    public function testNameLengthLimitIsExactly64Characters(): void
    {
        $atLimit = $this->form(str_repeat('a', Comment::AUTHOR_MAX_LENGTH), 'Text');
        $overLimit = $this->form(str_repeat('a', Comment::AUTHOR_MAX_LENGTH + 1), 'Text');

        $this->assertTrue($atLimit->validate(), 'A 64-character name must be accepted');
        $this->assertFalse($overLimit->validate(), 'A 65-character name must be rejected');
        $this->assertTrue($overLimit->hasErrors('author'));
    }

    /**
     * U-05: the text may be exactly 1000 characters, 1001 is too long.
     *
     * @return void
     */
    public function testTextLengthLimitIsExactly1000Characters(): void
    {
        $atLimit = $this->form('Anna', str_repeat('a', Comment::TEXT_MAX_LENGTH));
        $overLimit = $this->form('Anna', str_repeat('a', Comment::TEXT_MAX_LENGTH + 1));

        $this->assertTrue($atLimit->validate(), 'A 1000-character text must be accepted');
        $this->assertFalse($overLimit->validate(), 'A 1001-character text must be rejected');
        $this->assertTrue($overLimit->hasErrors('text'));
    }

    /**
     * U-06: limits count characters, not bytes.
     *
     * "Я" takes 2 bytes in UTF-8 and "😀" takes 4. If the limit counted bytes,
     * a Russian name would be cut at 32 characters and an emoji one at 16.
     *
     * @dataProvider multibyteCharacters
     * @param string $char One multibyte character
     * @return void
     */
    public function testLimitsCountCharactersNotBytes(string $char): void
    {
        $form = $this->form(
            str_repeat($char, Comment::AUTHOR_MAX_LENGTH),
            str_repeat($char, Comment::TEXT_MAX_LENGTH),
        );

        $this->assertTrue($form->validate(), 'Multibyte text exactly at the limits must be accepted');
    }

    /**
     * Characters that take more than one byte in UTF-8.
     *
     * @return array<string, array{string}>
     */
    public static function multibyteCharacters(): array
    {
        return [
            'cyrillic (2 bytes)' => ['Я'],
            'emoji (4 bytes)' => ['😀'],
        ];
    }

    /**
     * U-07: a filled honeypot field ("website") rejects the form.
     *
     * The field is hidden from people, so only a bot fills it in.
     *
     * @return void
     */
    public function testFilledHoneypotRejectsTheForm(): void
    {
        $form = $this->form('Anna', 'Buy cheap pills');
        $form->website = 'https://spam.example';

        $this->assertFalse($form->validate());
        $this->assertTrue($form->hasErrors('website'));
    }

    /**
     * Builds a form the way the controller does: from request data.
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
}
