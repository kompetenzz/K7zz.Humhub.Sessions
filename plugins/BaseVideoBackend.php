<?php

namespace humhub\modules\sessions\plugins;

use humhub\modules\sessions\interfaces\VideoBackendInterface;
use humhub\modules\sessions\models\Session;
use humhub\modules\user\models\User;
use humhub\modules\sessions\Module;
use yii\base\Component;
use Yii;

/**
 * Abstract base class for video backend implementations.
 * Provides common functionality and default implementations.
 */
abstract class BaseVideoBackend extends Component implements VideoBackendInterface
{
    /**
     * @var Module
     */
    protected $module;

    public function __construct(Module $module, $config = [])
    {
        $this->module = $module;
        parent::__construct($config);
    }

    /**
     * Get module settings
     * @return \humhub\modules\ui\form\widgets\ActiveForm
     */
    protected function getSettings()
    {
        return $this->module->settings;
    }

    /**
     * Get backend-specific setting
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getSetting(string $key, $default = null)
    {
        return $this->getSettings()->get($this->getId() . '.' . $key, $default);
    }

    /**
     * Set backend-specific setting
     * @param string $key
     * @param mixed $value
     */
    protected function setSetting(string $key, $value): void
    {
        $this->getSettings()->set($this->getId() . '.' . $key, $value);
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return Yii::t('SessionsModule.backend', '{name} video conferencing backend', [
            'name' => $this->getName()
        ]);
    }

    /**
     * @inheritdoc
     * Default: No session-specific config fields
     */
    public function getSessionConfigFields(): array
    {
        return [];
    }

    /**
     * Default: No recordings support
     */
    public function supportsRecordings(): bool
    {
        return false;
    }

    /**
     * Default: Waiting room supported
     */
    public function supportsWaitingRoom(): bool
    {
        return true;
    }

    /**
     * Default: No presentation upload
     */
    public function supportsPresentationUpload(): bool
    {
        return false;
    }

    /**
     * Default: Public join supported
     */
    public function supportsPublicJoin(): bool
    {
        return true;
    }

    /**
     * Default: No camera background
     */
    public function supportsCameraBackground(): bool
    {
        return false;
    }

    /**
     * Default: No layout options
     */
    public function supportsLayoutOptions(): bool
    {
        return false;
    }

    /**
     * Default: Embedding in iframe supported
     */
    public function supportsEmbed(): bool
    {
        return true;
    }

    /**
     * Default: Recordings not supported
     */
    public function getRecordings(Session $session): array
    {
        throw new \Exception('Recordings not supported by this backend');
    }

    /**
     * Default: Publishing not supported
     */
    public function publishRecording(string $recordingId, bool $publish): bool
    {
        throw new \Exception('Recording publishing not supported by this backend');
    }

    /**
     * Default: Deletion not supported
     */
    public function deleteRecording(string $recordingId): bool
    {
        throw new \Exception('Recording deletion not supported by this backend');
    }

    /**
     * Default: Anonymous join same as regular join
     */
    public function anonymousJoinUrl(Session $session, string $displayName): string
    {
        if (!$this->supportsPublicJoin()) {
            throw new \Exception('Public join not supported by this backend');
        }

        // Default implementation - backends can override
        return $this->joinUrl($session, null, false);
    }

    /**
     * Generate unique meeting ID
     * @param Session $session
     * @return string
     */
    protected function generateMeetingId(Session $session): string
    {
        return $session->uuid ?: \Yii::$app->security->generateRandomString(32);
    }

    /**
     * Get user's display name
     * @param User|null $user
     * @return string
     */
    protected function getUserDisplayName(?User $user): string
    {
        if (!$user) {
            return Yii::t('SessionsModule.base', 'Guest');
        }
        return $user->displayName ?? $user->username;
    }

    /**
     * Get user's avatar URL
     * @param User|null $user
     * @return string|null
     */
    protected function getUserAvatarUrl(?User $user): ?string
    {
        if (!$user) {
            return null;
        }
        return $user->getProfileImage()->getUrl();
    }

    /**
     * Get backend logo as HTML (SVG or img tag)
     * @param int $size Size in pixels (default 20)
     * @return string HTML for the logo
     */
    public function getLogo(int $size = 20): string
    {
        // Default: fall back to Font Awesome icon
        return '<i class="fa ' . $this->getIcon() . '" style="font-size: ' . $size . 'px;"></i>';
    }
}
