<?php

declare(strict_types=1);

namespace app\models;

use yii\base\Model;

/**
 * The comment form a guest fills in on the public page.
 *
 * Besides the visible fields it has a honeypot field (`website`): it is hidden from
 * people, so only spam bots fill it in.
 */
class CommentForm extends Model
{
    // Not typed as string: a crafted request may send an array, which must fail validation, not crash
    /** @var mixed */
    public $author = '';
    /** @var mixed */
    public $text = '';
    /** @var mixed Honeypot, must stay empty */
    public $website = '';

    /**
     * Validation rules for the guest form.
     *
     * @return array<int, array<int|string, mixed>>
     */
    public function rules(): array
    {
        return [
            [['author', 'text'], 'trim'],
            [['author', 'text'], 'required'],
            ['author', 'string', 'max' => Comment::AUTHOR_MAX_LENGTH],
            ['text', 'string', 'max' => Comment::TEXT_MAX_LENGTH],
            // Empty values skip this rule, so it fails only when a bot has filled the field in
            ['website', 'compare', 'compareValue' => '', 'message' => 'Your comment looks like spam.'],
        ];
    }

    /**
     * Human-readable attribute names for the form.
     *
     * @return array<string, string>
     */
    public function attributeLabels(): array
    {
        return [
            'author' => 'Your name',
            'text' => 'Comment',
            'website' => 'Leave this field empty',
        ];
    }
}
