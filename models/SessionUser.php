<?php

namespace humhub\modules\sessions\models;

use yii\db\ActiveRecord;
use yii\db\ActiveQuery;
use humhub\modules\user\models\User;

/**
 * ActiveRecord model for the relation between sessions and users.
 * Represents user-specific permissions and roles for a session.
 *
 * @property int $id
 * @property int $session_id
 * @property int $user_id
 * @property bool $can_start
 * @property bool $can_join
 * @property string $role 'moderator' or 'attendee'
 * @property int|null $created_at
 */
class SessionUser extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'sessions_session_user';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['session_id', 'user_id'], 'required'],
            [['session_id', 'user_id', 'created_at'], 'integer'],
            [['can_start', 'can_join'], 'boolean'],
            [['role'], 'in', 'range' => ['moderator', 'attendee']],
            [['role'], 'default', 'value' => 'attendee'],
            [['can_start'], 'default', 'value' => false],
            [['can_join'], 'default', 'value' => true],
        ];
    }

    /**
     * Gets the related session.
     * @return ActiveQuery
     */
    public function getSession(): ActiveQuery
    {
        return $this->hasOne(Session::class, ['id' => 'session_id']);
    }

    /**
     * Gets the related user.
     * @return ActiveQuery
     */
    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
