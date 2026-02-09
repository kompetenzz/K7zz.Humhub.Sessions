<?php

namespace sessions;

use humhub\modules\sessions\interfaces\VideoBackendInterface;
use humhub\modules\sessions\models\Session;
use humhub\modules\user\models\User;

/**
 * Stub backend for unit testing.
 * Always returns isConfigured() = true and provides minimal implementations.
 */
class TestBackend implements VideoBackendInterface
{
    private string $id;

    /** @var array Log of all API calls made to this backend */
    public array $callLog = [];

    /** @var self[] Registry of all TestBackend instances by ID (for test assertions) */
    private static array $instances = [];

    public function __construct(string $id = 'bbb')
    {
        $this->id = $id;
        self::$instances[$id] = $this;
    }

    /**
     * Get a TestBackend instance by ID (for test assertions)
     */
    public static function getInstance(string $id = 'bbb'): ?self
    {
        return self::$instances[$id] ?? null;
    }

    /**
     * Reset all instances (call in test teardown)
     */
    public static function resetInstances(): void
    {
        self::$instances = [];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return 'Test Backend (' . $this->id . ')';
    }

    public function getIcon(): string
    {
        return 'fa-video-camera';
    }

    public function getLogo(int $size = 20): string
    {
        return '<i class="fa fa-video-camera"></i>';
    }

    public function getDescription(): string
    {
        return 'Test backend for unit tests';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function getSettingsFormClass(): ?string
    {
        return null;
    }

    public function getSessionConfigFields(): array
    {
        return [
            'layout' => [
                'type' => 'radio',
                'label' => 'Layout',
                'default' => 'CUSTOM_LAYOUT',
                'options' => [
                    'CUSTOM_LAYOUT' => 'Custom Layout',
                    'SMART_LAYOUT' => 'Smart Layout',
                ],
            ],
        ];
    }

    public function createMeeting(Session $session): array
    {
        $this->callLog[] = [
            'method' => 'createMeeting',
            'session' => $session,
        ];
        return ['meetingId' => $session->uuid, 'internalMeetingId' => 'test-' . $session->uuid];
    }

    public function joinUrl(Session $session, User $user, bool $isModerator): string
    {
        $this->callLog[] = [
            'method' => 'joinUrl',
            'session' => $session,
            'user' => $user,
            'isModerator' => $isModerator,
        ];
        return 'https://test.example.com/join/' . $session->uuid;
    }

    public function anonymousJoinUrl(Session $session, string $displayName): string
    {
        $this->callLog[] = [
            'method' => 'anonymousJoinUrl',
            'session' => $session,
            'displayName' => $displayName,
        ];
        return 'https://test.example.com/join/' . $session->uuid . '?name=' . urlencode($displayName);
    }

    public function isRunning(Session $session): bool
    {
        $this->callLog[] = [
            'method' => 'isRunning',
            'session' => $session,
        ];
        return false;
    }

    public function endMeeting(Session $session): bool
    {
        $this->callLog[] = [
            'method' => 'endMeeting',
            'session' => $session,
        ];
        return true;
    }

    public function supportsRecordings(): bool
    {
        return false;
    }

    public function supportsWaitingRoom(): bool
    {
        return true;
    }

    public function supportsPresentationUpload(): bool
    {
        return false;
    }

    public function supportsPublicJoin(): bool
    {
        return true;
    }

    public function supportsCameraBackground(): bool
    {
        return false;
    }

    public function supportsLayoutOptions(): bool
    {
        return true;
    }

    public function supportsEmbed(): bool
    {
        return true;
    }

    public function isAlwaysJoinable(): bool
    {
        return false;
    }

    public function getRecordings(Session $session): array
    {
        return [];
    }

    public function publishRecording(string $recordingId, bool $publish): bool
    {
        return true;
    }

    public function deleteRecording(string $recordingId): bool
    {
        return true;
    }
}
