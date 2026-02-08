<?php

namespace humhub\modules\sessions\plugins\opentalk;

use humhub\modules\sessions\plugins\BaseVideoBackend;
use humhub\modules\sessions\models\Session;
use humhub\modules\user\models\User;
use Yii;
use yii\httpclient\Client;

/**
 * OpenTalk video backend implementation.
 *
 * OpenTalk is a German open-source video conferencing solution
 * with a REST API for managing rooms and meetings.
 *
 * @see https://opentalk.eu
 */
class OpentalkBackend extends BaseVideoBackend
{
    /**
     * @var Client HTTP client for API requests
     */
    protected $httpClient;

    /**
     * @inheritdoc
     */
    public function getId(): string
    {
        return 'opentalk';
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'OpenTalk';
    }

    /**
     * @inheritdoc
     */
    public function getIcon(): string
    {
        return 'fa-comments';
    }

    /**
     * @inheritdoc
     */
    public function getLogo(int $size = 20): string
    {
        // OpenTalk logo - teal/cyan speech bubble style
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
            <circle cx="50" cy="50" r="48" fill="#00838f"/>
            <path d="M30 35 h40 a5 5 0 0 1 5 5 v20 a5 5 0 0 1 -5 5 h-25 l-10 10 v-10 h-5 a5 5 0 0 1 -5 -5 v-20 a5 5 0 0 1 5 -5 z" fill="#fff"/>
            <circle cx="40" cy="50" r="4" fill="#00838f"/>
            <circle cx="55" cy="50" r="4" fill="#00838f"/>
            <circle cx="70" cy="50" r="4" fill="#00838f"/>
        </svg>';
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return Yii::t('SessionsModule.opentalk', 'German open-source video conferencing solution with REST API. GDPR-compliant and self-hosted.');
    }

    /**
     * @inheritdoc
     */
    public function getSettingsFormClass(): ?string
    {
        return OpentalkSettingsForm::class;
    }

    /**
     * @inheritdoc
     */
    public function isConfigured(): bool
    {
        $url = $this->getSetting('apiUrl');
        $token = $this->getSetting('apiToken');
        return !empty($url) && !empty($token);
    }

    /**
     * @inheritdoc
     * OpenTalk-specific session options (beyond common fields in Session model).
     */
    public function getSessionConfigFields(): array
    {
        return [
            'enableSip' => [
                'type' => 'checkbox',
                'label' => Yii::t('SessionsModule.opentalk', 'Enable SIP dial-in'),
                'hint' => Yii::t('SessionsModule.opentalk', 'Allow participants to join via phone/SIP'),
                'default' => false,
            ],
            'enableChat' => [
                'type' => 'checkbox',
                'label' => Yii::t('SessionsModule.opentalk', 'Enable chat'),
                'hint' => Yii::t('SessionsModule.opentalk', 'Allow text chat during the meeting'),
                'default' => true,
            ],
            'enableScreenShare' => [
                'type' => 'checkbox',
                'label' => Yii::t('SessionsModule.opentalk', 'Enable screen sharing'),
                'hint' => Yii::t('SessionsModule.opentalk', 'Allow participants to share their screen'),
                'default' => true,
            ],
            'enableTimer' => [
                'type' => 'checkbox',
                'label' => Yii::t('SessionsModule.opentalk', 'Show meeting timer'),
                'hint' => Yii::t('SessionsModule.opentalk', 'Display elapsed meeting time'),
                'default' => false,
            ],
        ];
    }

    /**
     * Get API base URL.
     */
    protected function getApiUrl(): string
    {
        return rtrim($this->getSetting('apiUrl', ''), '/');
    }

    /**
     * Get API token.
     */
    protected function getApiToken(): string
    {
        return $this->getSetting('apiToken', '');
    }

    /**
     * Get HTTP client for API requests.
     */
    protected function getHttpClient(): Client
    {
        if ($this->httpClient === null) {
            $this->httpClient = new Client([
                'baseUrl' => $this->getApiUrl(),
                'requestConfig' => [
                    'format' => Client::FORMAT_JSON,
                ],
                'responseConfig' => [
                    'format' => Client::FORMAT_JSON,
                ],
            ]);
        }
        return $this->httpClient;
    }

    /**
     * Make an API request.
     */
    protected function apiRequest(string $method, string $endpoint, array $data = []): array
    {
        try {
            $request = $this->getHttpClient()
                ->createRequest()
                ->setMethod($method)
                ->setUrl($endpoint)
                ->addHeaders([
                    'Authorization' => 'Bearer ' . $this->getApiToken(),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]);

            if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
                $request->setData($data);
            }

            $response = $request->send();

            if ($response->isOk) {
                return [
                    'success' => true,
                    'data' => $response->data,
                ];
            }

            return [
                'success' => false,
                'error' => $response->data['message'] ?? 'API request failed',
                'statusCode' => $response->statusCode,
            ];
        } catch (\Exception $e) {
            Yii::error('OpenTalk API error: ' . $e->getMessage(), 'sessions');
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @inheritdoc
     */
    public function createMeeting(Session $session): array
    {
        $data = [
            'title' => $session->title ?: $session->name,
            'description' => $session->description ?? '',
            'password' => $session->public_join ? null : $this->generatePassword(),
            'waiting_room' => (bool) $session->has_waitingroom,
            'enable_sip' => false,
        ];

        $result = $this->apiRequest('POST', '/v1/rooms', $data);

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Failed to create OpenTalk room',
            ];
        }

        $roomData = $result['data'];

        return [
            'success' => true,
            'meetingId' => $roomData['id'] ?? null,
            'inviteCode' => $roomData['invite_code'] ?? null,
            'message' => 'OpenTalk room created',
        ];
    }

    /**
     * Generate a random password.
     */
    protected function generatePassword(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * @inheritdoc
     */
    public function joinUrl(Session $session, User $user, bool $isModerator): string
    {
        $roomId = $session->backend_meeting_id;

        if (empty($roomId)) {
            // Create room if it doesn't exist
            $result = $this->createMeeting($session);
            if ($result['success']) {
                $roomId = $result['meetingId'];
                $session->backend_meeting_id = $roomId;
                $session->save(false);
            }
        }

        $baseUrl = rtrim($this->getSetting('frontendUrl', $this->getApiUrl()), '/');

        // Generate join token for the user
        $tokenData = [
            'room_id' => $roomId,
            'display_name' => $user->displayName,
            'email' => $user->email ?? '',
            'avatar_url' => $user->getProfileImage()->getUrl() ?? '',
            'role' => $isModerator ? 'moderator' : 'participant',
        ];

        $result = $this->apiRequest('POST', '/v1/rooms/' . $roomId . '/invites', $tokenData);

        if ($result['success'] && isset($result['data']['invite_link'])) {
            return $result['data']['invite_link'];
        }

        // Fallback to basic room URL
        return $baseUrl . '/room/' . $roomId;
    }

    /**
     * @inheritdoc
     */
    public function isRunning(Session $session): bool
    {
        if (empty($session->backend_meeting_id)) {
            return false;
        }

        $result = $this->apiRequest('GET', '/v1/rooms/' . $session->backend_meeting_id);

        if (!$result['success']) {
            return false;
        }

        // Check if room has active participants
        return isset($result['data']['participant_count']) && $result['data']['participant_count'] > 0;
    }

    /**
     * @inheritdoc
     */
    public function endMeeting(Session $session): bool
    {
        if (empty($session->backend_meeting_id)) {
            return true;
        }

        $result = $this->apiRequest('DELETE', '/v1/rooms/' . $session->backend_meeting_id . '/meeting');

        return $result['success'];
    }

    /**
     * @inheritdoc
     */
    public function supportsRecordings(): bool
    {
        // OpenTalk supports recordings but implementation depends on version
        return (bool) $this->getSetting('enableRecordings', false);
    }

    /**
     * @inheritdoc
     */
    public function supportsWaitingRoom(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsPresentationUpload(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsPublicJoin(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getRecordings(Session $session): array
    {
        if (empty($session->backend_meeting_id) || !$this->supportsRecordings()) {
            return [];
        }

        $result = $this->apiRequest('GET', '/v1/rooms/' . $session->backend_meeting_id . '/recordings');

        if (!$result['success'] || empty($result['data'])) {
            return [];
        }

        $recordings = [];
        foreach ($result['data'] as $recording) {
            $recordings[] = \humhub\modules\sessions\models\Recording::createFromOpentalk($recording, $session);
        }

        return $recordings;
    }

    /**
     * @inheritdoc
     */
    public function publishRecording(string $recordingId, bool $publish): bool
    {
        $result = $this->apiRequest('PATCH', '/v1/recordings/' . $recordingId, [
            'published' => $publish,
        ]);

        return $result['success'];
    }

    /**
     * @inheritdoc
     */
    public function deleteRecording(string $recordingId): bool
    {
        $result = $this->apiRequest('DELETE', '/v1/recordings/' . $recordingId);

        return $result['success'];
    }

    /**
     * @inheritdoc
     */
    public function anonymousJoinUrl(Session $session, string $displayName): string
    {
        $roomId = $session->backend_meeting_id;

        if (empty($roomId)) {
            $result = $this->createMeeting($session);
            if ($result['success']) {
                $roomId = $result['meetingId'];
                $session->backend_meeting_id = $roomId;
                $session->save(false);
            }
        }

        $baseUrl = rtrim($this->getSetting('frontendUrl', $this->getApiUrl()), '/');

        // Generate guest invite
        $tokenData = [
            'room_id' => $roomId,
            'display_name' => $displayName,
            'role' => 'guest',
        ];

        $result = $this->apiRequest('POST', '/v1/rooms/' . $roomId . '/invites', $tokenData);

        if ($result['success'] && isset($result['data']['invite_link'])) {
            return $result['data']['invite_link'];
        }

        // Fallback to basic room URL with name parameter
        return $baseUrl . '/room/' . $roomId . '?name=' . urlencode($displayName);
    }
}
