<?php

namespace humhub\modules\sessions\models;

use humhub\modules\user\models\User;
use yii\db\ActiveRecord;

/**
 * Logs session events (start, stop, join, leave).
 *
 * @property int $id
 * @property int $session_id
 * @property int|null $user_id
 * @property string $event_type
 * @property int $created_at
 */
class SessionEventLog extends ActiveRecord
{
    public const EVENT_STARTED = 'started';
    public const EVENT_STOPPED = 'stopped';
    public const EVENT_JOINED = 'joined';
    public const EVENT_LEFT = 'left';

    public static function tableName(): string
    {
        return 'sessions_event_log';
    }

    public function rules(): array
    {
        return [
            [['session_id', 'event_type', 'created_at'], 'required'],
            [['session_id', 'user_id', 'created_at'], 'integer'],
            ['event_type', 'in', 'range' => [self::EVENT_STARTED, self::EVENT_STOPPED, self::EVENT_JOINED, self::EVENT_LEFT]],
        ];
    }

    public function getSession(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Session::class, ['id' => 'session_id']);
    }

    public function getUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Log a session event.
     */
    public static function log(int $sessionId, string $eventType, ?int $userId = null): bool
    {
        $entry = new self();
        $entry->session_id = $sessionId;
        $entry->user_id = $userId;
        $entry->event_type = $eventType;
        $entry->created_at = time();
        return $entry->save();
    }
}
