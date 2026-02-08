<?php

namespace humhub\modules\sessions\models;

use Yii;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\libs\BasePermission;
use yii\db\ActiveQuery;
use yii\helpers\Json;
use humhub\modules\file\converter\PreviewImage;
use humhub\modules\file\models\File;
use humhub\modules\user\components\User as UserComponent;
use humhub\modules\user\models\User;
use humhub\modules\content\widgets\richtext\RichText;
use humhub\modules\sessions\widgets\WallEntry;
use humhub\modules\sessions\permissions\{
    Admin,
    StartSession,
    JoinSession
};

/**
 * ActiveRecord model for a video conferencing session.
 * Supports multiple backends (BBB, Jitsi, Opentalk, Zoom) through backend_type field.
 *
 * @property int $id
 * @property string $uuid Unique meeting identifier
 * @property string $backend_type Backend identifier ('bbb', 'jitsi', 'opentalk', 'zoom')
 * @property string|null $backend_meeting_id Backend-specific meeting ID
 * @property string|null $backend_config JSON config for backend-specific settings
 * @property string $name URL slug
 * @property string $title
 * @property string|null $description
 * @property string $moderator_pw
 * @property string $attendee_pw
 * @property int|null $contentcontainer_id
 * @property int $creator_user_id
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property bool $enabled
 * @property int $ord
 * @property bool $public_join
 * @property string|null $public_token
 * @property bool $join_can_start
 * @property bool $join_can_moderate
 * @property bool $has_waitingroom
 * @property bool $allow_recording
 * @property bool $mute_on_entry
 * @property int|null $image_file_id
 * @property int|null $camera_bg_image_file_id
 * @property int|null $presentation_file_id
 * @property int|null $presentation_preview_file_id
 */
class Session extends ContentActiveRecord
{
    protected $moduleId = 'sessions';

    public $outputImage = null;

    public $wallEntryClass = WallEntry::class;
    public $autoAddToWall = true;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'sessions_session';
    }

    /**
     * @inheritdoc
     */
    public function afterFind()
    {
        parent::afterFind();
        if ($this->image_file_id !== null) {
            $image = $this->getImageFile();
            $previewImage = new PreviewImage();
            if ($image && $previewImage->applyFile($image)) {
                $this->outputImage = $previewImage;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['uuid', 'name', 'backend_type', 'moderator_pw', 'attendee_pw', 'creator_user_id'], 'required'],
            [['uuid', 'name', 'title', 'moderator_pw', 'attendee_pw'], 'string', 'max' => 255],
            [['backend_type'], 'string', 'max' => 20],
            [['backend_meeting_id'], 'string', 'max' => 255],
            [['description', 'backend_config'], 'string'],
            [['uuid'], 'unique'],
            [
                [
                    'creator_user_id',
                    'contentcontainer_id',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                    'ord',
                    'image_file_id',
                    'camera_bg_image_file_id',
                    'presentation_file_id',
                    'presentation_preview_file_id'
                ],
                'integer'
            ],
            [
                [
                    'enabled',
                    'public_join',
                    'join_can_start',
                    'join_can_moderate',
                    'has_waitingroom',
                    'allow_recording',
                    'mute_on_entry'
                ],
                'boolean'
            ],
            [['public_token'], 'string', 'max' => 64],
        ];
    }

    /**
     * Get backend-specific configuration as array
     * @return array
     */
    public function getBackendConfig(): array
    {
        if (empty($this->backend_config)) {
            return [];
        }
        try {
            return Json::decode($this->backend_config);
        } catch (\Exception $e) {
            Yii::error("Failed to decode backend_config: " . $e->getMessage(), 'sessions');
            return [];
        }
    }

    /**
     * Set backend-specific configuration from array
     * @param array $config
     */
    public function setBackendConfig(array $config): void
    {
        $this->backend_config = Json::encode($config);
    }

    /**
     * Get backend-specific config value
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getBackendConfigValue(string $key, $default = null)
    {
        $config = $this->getBackendConfig();
        return $config[$key] ?? $default;
    }

    /**
     * Set backend-specific config value
     * @param string $key
     * @param mixed $value
     */
    public function setBackendConfigValue(string $key, $value): void
    {
        $config = $this->getBackendConfig();
        $config[$key] = $value;
        $this->setBackendConfig($config);
    }

    public function getUrl()
    {
        $relUrl = '/sessions/list?highlight=' . $this->id;
        return $this->content->container ? $this->content->container->createUrl($relUrl) : \yii\helpers\Url::to($relUrl);
    }

    /**
     * @inheritdoc
     */
    public function getIcon()
    {
        return 'fa-video-camera';
    }

    /**
     * @inheritdoc
     */
    public function getContentName()
    {
        return $this->title ?: Yii::t('SessionsModule.base', 'A video session');
    }

    /**
     * @inheritdoc
     */
    public function getContentDescription()
    {
        return $this->description ?: Yii::t('SessionsModule.base', 'Video conferencing session');
    }

    /**
     * @inheritdoc
     */
    public function getSearchAttributes()
    {
        $attrs = [
            'title' => $this->title,
            'description' => $this->description,
            'backend' => $this->backend_type,
        ];

        $topics = [];
        foreach ($this->content->tags as $tag) {
            if ($tag->module_id === 'topic') {
                $topics[] = $tag->name;
            }
        }
        $attrs['topics'] = implode(' ', $topics);

        return $attrs;
    }

    // ========== Permission Methods ==========

    public function canAdminister(?UserComponent $user = null): bool
    {
        $user ??= Yii::$app->user;

        if ($this->content->canEdit($user->identity)) {
            return true;
        }

        return $this->can($user, Admin::class);
    }

    public function canStart(?UserComponent $user = null): bool
    {
        $user ??= Yii::$app->user;

        if ($this->canAdminister($user)) {
            return true;
        }

        if ($this->join_can_start && $this->hasJoinPermission($user)) {
            return true;
        }

        return $this->hasStartPermission($user);
    }

    private function hasStartPermission(?UserComponent $user = null): bool
    {
        $user ??= Yii::$app->user;

        if ($this->can($user, StartSession::class)) {
            return true;
        }

        $pivot = SessionUser::findOne(['session_id' => $this->id, 'user_id' => $user->id]);
        return $pivot ? (bool) $pivot->can_start : false;
    }

    public function canJoin(?UserComponent $user = null): bool
    {
        $user ??= Yii::$app->user;

        if ($this->canStart($user)) {
            return true;
        }

        return $this->hasJoinPermission($user);
    }

    private function hasJoinPermission(?UserComponent $user = null): bool
    {
        $user ??= Yii::$app->user;

        if ($this->can($user, JoinSession::class)) {
            return true;
        }

        $pivot = SessionUser::findOne(['session_id' => $this->id, 'user_id' => $user->id]);
        return $pivot ? (bool) $pivot->can_join : false;
    }

    public function isModerator(?UserComponent $user = null): bool
    {
        $user ??= Yii::$app->user;

        if ($this->can($user, Admin::class)) {
            return true;
        }

        if ($this->join_can_moderate && $this->canJoin($user)) {
            return true;
        }

        $pivot = SessionUser::findOne(['session_id' => $this->id, 'user_id' => $user->id]);
        return $pivot ? ($pivot->role === 'moderator' || $this->join_can_moderate) : false;
    }

    private function can(?UserComponent $user, BasePermission|string $permission): bool
    {
        $user ??= Yii::$app->user;

        if (!$this->content || !$this->content->container) {
            return $user->can($permission);
        }

        $container = $this->content->container;
        if ($container instanceof ContentContainerActiveRecord && $container->can($permission, ['user' => $user])) {
            return true;
        }

        return false;
    }

    // ========== Relations ==========

    public function getSessionUsers(): ActiveQuery
    {
        return $this->hasMany(SessionUser::class, ['session_id' => 'id']);
    }

    public function getUsers(): ActiveQuery
    {
        return $this->hasMany(User::class, ['id' => 'user_id'])->via('sessionUsers');
    }

    public function getAttendeeUsers(): ActiveQuery
    {
        return $this->hasMany(User::class, ['id' => 'user_id'])
            ->via('sessionUsers', function (ActiveQuery $q) {
                $q->andWhere(['role' => 'attendee']);
            });
    }

    public function getModeratorUsers(): ActiveQuery
    {
        return $this->hasMany(User::class, ['id' => 'user_id'])
            ->via('sessionUsers', function (ActiveQuery $q) {
                $q->andWhere(['role' => 'moderator']);
            });
    }

    // ========== File Relations ==========

    private function getFile(string $ref): ?File
    {
        return $this->hasOne(File::class, ['id' => $ref])->one();
    }

    public function getImageFile(): ?File
    {
        return $this->getFile('image_file_id');
    }

    public function getCameraBgImageFile(): ?File
    {
        return $this->getFile('camera_bg_image_file_id');
    }

    public function getPresentationFile(): ?File
    {
        return $this->getFile('presentation_file_id');
    }

    public function getPresentationPreviewImageFile(): ?File
    {
        return $this->getFile('presentation_preview_file_id');
    }

    // ========== Public Token ==========

    public function ensurePublicToken()
    {
        if (!$this->public_token) {
            $this->public_token = Yii::$app->security->generateRandomString(48);
        }
    }

    public function beforeValidate()
    {
        // Set integer timestamps before validation
        // (HumHub core's ActiveRecord sets datetime strings, but our columns are int)
        if ($this->isNewRecord) {
            $this->created_at = time();
        }
        $this->updated_at = time();

        return parent::beforeValidate();
    }

    public function beforeSave($insert)
    {
        if ($this->public_join) {
            $this->ensurePublicToken();
        }

        return parent::beforeSave($insert);
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        RichText::postProcess($this->description, $this);
    }
}
