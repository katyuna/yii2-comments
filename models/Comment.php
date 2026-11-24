<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * A comment shown in the public feed and managed in the admin area.
 *
 * @property int $id
 * @property string $author
 * @property string $text
 * @property string $ip
 * @property int $created_at
 * @property int $updated_at
 */
class Comment extends ActiveRecord
{
    public const AUTHOR_MAX_LENGTH = 64;
    public const TEXT_MAX_LENGTH = 1000;

    /**
     * Returns the DB table name.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%comment}}';
    }

    /**
     * Fills created_at / updated_at automatically on save.
     *
     * @return array<int|string, mixed>
     */
    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * Validation rules; only author and text can be set from user input.
     *
     * @return array<int, array<int|string, mixed>>
     */
    public function rules(): array
    {
        return [
            [['author', 'text'], 'trim'],
            [['author', 'text'], 'required'],
            ['author', 'string', 'max' => self::AUTHOR_MAX_LENGTH],
            ['text', 'string', 'max' => self::TEXT_MAX_LENGTH],
        ];
    }

    /**
     * Human-readable attribute names for forms and error messages.
     *
     * @return array<string, string>
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'author' => 'Name',
            'text' => 'Comment',
            'ip' => 'IP',
            'created_at' => 'Posted',
            'updated_at' => 'Updated',
        ];
    }
}
