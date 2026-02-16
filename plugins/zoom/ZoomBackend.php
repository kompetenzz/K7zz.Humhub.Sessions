<?php

namespace humhub\modules\sessions\plugins\zoom;

use humhub\modules\sessions\plugins\BaseVideoBackend;
use humhub\modules\sessions\models\Session;
use humhub\modules\sessions\models\Recording;
use humhub\modules\user\models\User;
use Yii;
use yii\httpclient\Client;

/**
 * Zoom video backend implementation.
 *
 * Uses Zoom REST API for meeting management.
 * Supports Server-to-Server OAuth or JWT authentication.
 *
 * @see https://developers.zoom.us/docs/api/
 */
class ZoomBackend extends BaseVideoBackend
{
    /**
     * @var Client HTTP client for API requests
     */
    protected $httpClient;

    /**
     * @var string|null Cached access token
     */
    protected $accessToken;

    /**
     * @var int|null Access token expiration timestamp
     */
    protected $tokenExpires;

    /**
     * @inheritdoc
     */
    public function getId(): string
    {
        return 'zoom';
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Zoom';
    }

    /**
     * @inheritdoc
     */
    public function getIcon(): string
    {
        return 'fa-video-camera';
    }

    /**
     * @inheritdoc
     */
    public function getLogo(int $size = 20): string
    {
        // Zoom logo - blue circle with video camera icon
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
            <circle cx="50" cy="50" r="48" fill="#2d8cff"/>
            <rect x="25" y="35" width="35" height="30" rx="5" fill="#fff"/>
            <polygon points="65,42 80,32 80,68 65,58" fill="#fff"/>
        </svg>';
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return Yii::t('SessionsModule.zoom', 'Enterprise video conferencing with cloud recordings, waiting rooms, and advanced features. Requires Zoom account.');
    }

    /**
     * @inheritdoc
     */
    public function getSettingsFormClass(): ?string
    {
        return ZoomSettingsForm::class;
    }

    /**
     * @inheritdoc
     */
    public function isConfigured(): bool
    {
        $accountId = $this->getSetting('accountId');
        $clientId = $this->getSetting('clientId');
        $clientSecret = $this->getSetting('clientSecret');

        return !empty($accountId) && !empty($clientId) && !empty($clientSecret);
    }

    /**
     * @inheritdoc
     * Zoom-specific session options (beyond common fields in Session model).
     */
    public function getSessionConfigFields(): array
    {
        return [
            'hostVideo' => [
                'type' => 'checkbox',
                'label' => Yii::t('SessionsModule.zoom', 'Host starts with video on'),
                'hint' => Yii::t('SessionsModule.zoom', 'Start meeting with host video enabled'),
                'default' => true,
            ],
            'participantVideo' => [
                'type' => 'checkbox',
                'label' => Yii::t('SessionsModule.zoom', 'Participants start with video on'),
                'hint' => Yii::t('SessionsModule.zoom', 'Participants join with video enabled by default'),
                'default' => true,
            ],
            'alternativeHosts' => [
                'type' => 'text',
                'label' => Yii::t('SessionsModule.zoom', 'Alternative hosts'),
                'hint' => Yii::t('SessionsModule.zoom', 'Comma-separated email addresses of alternative hosts'),
                'default' => '',
            ],
            'enableBreakoutRooms' => [
                'type' => 'checkbox',
                'label' => Yii::t('SessionsModule.zoom', 'Enable breakout rooms'),
                'hint' => Yii::t('SessionsModule.zoom', 'Allow host to create breakout rooms during meeting'),
                'default' => false,
            ],
            'autoRecording' => [
                'type' => 'select',
                'label' => Yii::t('SessionsModule.zoom', 'Auto recording'),
                'hint' => Yii::t('SessionsModule.zoom', 'Automatically start recording when meeting begins'),
                'default' => 'none',
                'options' => [
                    'none' => Yii::t('SessionsModule.zoom', 'Disabled'),
                    'local' => Yii::t('SessionsModule.zoom', 'Local recording'),
                    'cloud' => Yii::t('SessionsModule.zoom', 'Cloud recording'),
                ],
            ],
        ];
    }

    /**
     * Get OAuth access token.
     */
    protected function getAccessToken(): ?string
    {
        // Check if we have a valid cached token
        if ($this->accessToken && $this->tokenExpires && time() < $this->tokenExpires) {
            return $this->accessToken;
        }

        $accountId = $this->getSetting('accountId');
        $clientId = $this->getSetting('clientId');
        $clientSecret = $this->getSetting('clientSecret');

        try {
            $client = new Client();
            $request = $client->createRequest()
                ->setMethod('POST')
                ->setUrl('https://zoom.us/oauth/token')
                ->setFormat(Client::FORMAT_URLENCODED)
                ->addHeaders([
                    'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
                ])
                ->setData([
                    'grant_type' => 'account_credentials',
                    'account_id' => $accountId,
                ]);

            $response = $request->send();

            if ($response->isOk && isset($response->data['access_token'])) {
                $this->accessToken = $response->data['access_token'];
                $this->tokenExpires = time() + (isset($response->data['expires_in']) ? $response->data['expires_in'] - 300 : 3300);
                return $this->accessToken;
            }

            Yii::error('Zoom OAuth failed [' . $response->statusCode . ']: ' . $response->content, 'sessions');
            return null;
        } catch (\Exception $e) {
            Yii::error('Zoom OAuth error: ' . $e->getMessage(), 'sessions');
            return null;
        }
    }

    /**
     * Get HTTP client for API requests.
     */
    protected function getHttpClient(): Client
    {
        if ($this->httpClient === null) {
            $this->httpClient = new Client([
                'baseUrl' => 'https://api.zoom.us/v2',
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
        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'success' => false,
                'error' => 'Failed to obtain access token',
            ];
        }

        try {
            $request = $this->getHttpClient()
                ->createRequest()
                ->setMethod($method)
                ->setUrl($endpoint)
                ->addHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
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

            if ((int) $response->statusCode !== 404) {
                Yii::error("Zoom API {$method} {$endpoint} [{$response->statusCode}]: {$response->content}", 'sessions');
            }

            return [
                'success' => false,
                'error' => $response->data['message'] ?? 'API request failed',
                'statusCode' => $response->statusCode,
            ];
        } catch (\Exception $e) {
            Yii::error('Zoom API error: ' . $e->getMessage(), 'sessions');
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Append display name to a Zoom join URL.
     */
    protected function appendDisplayName(string $url, string $displayName): string
    {
        $separator = strpos($url, '?') !== false ? '&' : '?';
        // Use + for spaces to avoid double-encoding (%2520) through redirects.
        return $url . $separator . 'uname=' . str_replace(' ', '+', $displayName);
    }

    /**
     * Get Zoom user ID for creating meetings.
     */
    protected function getZoomUserId(): string
    {
        return $this->getSetting('userId', 'me');
    }

    /**
     * Build Zoom meeting data from session settings.
     */
    protected function buildMeetingData(Session $session): array
    {
        $backendConfig = $session->backend_config ? json_decode($session->backend_config, true) : [];

        $data = [
            'topic' => $session->title ?: $session->name,
            'agenda' => $session->description ?? '',
            'settings' => [
                'host_video' => (bool) ($backendConfig['hostVideo'] ?? true),
                'participant_video' => (bool) ($backendConfig['participantVideo'] ?? true),
                'join_before_host' => true,
                'mute_upon_entry' => (bool) $session->mute_on_entry,
                'waiting_room' => (bool) $session->has_waitingroom,
                'auto_recording' => $backendConfig['autoRecording'] ?? 'none',
                'approval_type' => 2,
                'meeting_authentication' => false,
                'breakout_room' => [
                    'enable' => (bool) ($backendConfig['enableBreakoutRooms'] ?? false),
                ],
            ],
        ];

        if (!empty($backendConfig['alternativeHosts'])) {
            $data['settings']['alternative_hosts'] = $backendConfig['alternativeHosts'];
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function createMeeting(Session $session): array
    {
        $userId = $this->getZoomUserId();

        $meetingData = $this->buildMeetingData($session);
        $meetingData['type'] = 2; // Scheduled meeting (persistent)

        $result = $this->apiRequest('POST', '/users/' . $userId . '/meetings', $meetingData);

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Failed to create Zoom meeting',
            ];
        }

        $meeting = $result['data'];

        return [
            'success' => true,
            'meetingId' => (string) $meeting['id'],
            'joinUrl' => $meeting['join_url'] ?? null,
            'startUrl' => $meeting['start_url'] ?? null,
            'password' => $meeting['password'] ?? null,
            'message' => 'Zoom meeting created',
        ];
    }

    /**
     * Sync session settings to an existing Zoom meeting.
     */
    protected function updateMeeting(Session $session): void
    {
        if (empty($session->backend_meeting_id)) {
            return;
        }

        $this->apiRequest('PATCH', '/meetings/' . $session->backend_meeting_id, $this->buildMeetingData($session));
    }

    /**
     * @inheritdoc
     */
    public function joinUrl(Session $session, User $user, bool $isModerator): string
    {
        $meetingId = $session->backend_meeting_id;

        if (empty($meetingId)) {
            // Create meeting if it doesn't exist
            $result = $this->createMeeting($session);
            if ($result['success']) {
                $meetingId = $result['meetingId'];
                $session->backend_meeting_id = $meetingId;

                // Store start/join URLs in backend_config
                $config = $session->backend_config ? json_decode($session->backend_config, true) : [];
                $config['zoom_join_url'] = $result['joinUrl'] ?? null;
                $config['zoom_start_url'] = $result['startUrl'] ?? null;
                $config['zoom_password'] = $result['password'] ?? null;
                $session->backend_config = json_encode($config);

                $session->save(false);
            } else {
                return '#error';
            }
        } else {
            // Sync current session settings to existing Zoom meeting
            $this->updateMeeting($session);
        }

        // Use join_url for everyone (HumHub users are not Zoom account holders)
        $config = $session->backend_config ? json_decode($session->backend_config, true) : [];

        if (isset($config['zoom_join_url'])) {
            return $this->appendDisplayName($config['zoom_join_url'], $user->displayName);
        }

        // Fallback: fetch fresh join_url from API
        $result = $this->apiRequest('GET', '/meetings/' . $meetingId);
        if ($result['success'] && isset($result['data']['join_url'])) {
            return $this->appendDisplayName($result['data']['join_url'], $user->displayName);
        }

        return '#error';
    }

    /**
     * @inheritdoc
     */
    public function isRunning(Session $session): bool
    {
        if (empty($session->backend_meeting_id)) {
            return false;
        }

        $result = $this->apiRequest('GET', '/meetings/' . $session->backend_meeting_id);

        if (!$result['success']) {
            // Meeting no longer exists in Zoom - clear stale ID
            if ((int) ($result['statusCode'] ?? 0) === 404) {
                $session->backend_meeting_id = null;
                $session->save(false);
            }
            return false;
        }

        $status = $result['data']['status'] ?? 'waiting';
        return $status === 'started';
    }

    /**
     * @inheritdoc
     */
    public function endMeeting(Session $session): bool
    {
        if (empty($session->backend_meeting_id)) {
            return true;
        }

        $result = $this->apiRequest('PUT', '/meetings/' . $session->backend_meeting_id . '/status', [
            'action' => 'end',
        ]);

        return $result['success'];
    }

    /**
     * @inheritdoc
     */
    public function supportsRecordings(): bool
    {
        return true;
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
     * Zoom blocks iframing (X-Frame-Options), always redirect directly.
     */
    public function supportsEmbed(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getRecordings(Session $session): array
    {
        if (empty($session->backend_meeting_id)) {
            return [];
        }

        $result = $this->apiRequest('GET', '/meetings/' . $session->backend_meeting_id . '/recordings');

        if (!$result['success'] || empty($result['data']['recording_files'])) {
            return [];
        }

        // share_url includes the passcode and works without Zoom login
        $shareUrl = $result['data']['share_url'] ?? null;
        $password = $result['data']['password'] ?? '';

        $recordings = [];
        foreach ($result['data']['recording_files'] as $file) {
            if (in_array($file['file_type'] ?? '', ['MP4', 'M4A'])) {
                // Prefer share_url (no auth needed), fall back to play_url with passcode param
                $playUrl = $shareUrl;
                if (!$playUrl) {
                    $playUrl = $file['play_url'] ?? null;
                    if ($playUrl && $password) {
                        $playUrl .= (str_contains($playUrl, '?') ? '&' : '?') . 'pwd=' . urlencode($password);
                    }
                }

                $fileType = $file['file_type'] ?? 'MP4';
                $formatType = match ($fileType) {
                    'MP4' => 'video',
                    'M4A' => 'podcast',
                    default => 'video',
                };

                $recordings[] = Recording::fromZoomData([
                    'id' => $file['id'],
                    'play_url' => $playUrl,
                    'share_url' => $shareUrl,
                    'download_url' => $file['download_url'] ?? null,
                    'start_time' => $result['data']['start_time'] ?? null,
                    'duration' => $result['data']['duration'] ?? 0,
                    'topic' => $result['data']['topic'] ?? $session->title,
                    'status' => $file['status'] ?? 'completed',
                    'file_size' => $file['file_size'] ?? 0,
                    'file_type' => $fileType,
                    'format_type' => $formatType,
                ]);
            }
        }

        return $recordings;
    }

    /**
     * @inheritdoc
     */
    public function publishRecording(string $recordingId, bool $publish): bool
    {
        // Zoom doesn't have a publish/unpublish concept for individual recordings
        // Recordings can be deleted or have password protection changed
        return true;
    }

    /**
     * @inheritdoc
     */
    public function deleteRecording(string $recordingId): bool
    {
        $result = $this->apiRequest('DELETE', '/recordings/' . $recordingId);
        return $result['success'];
    }

    /**
     * @inheritdoc
     */
    public function anonymousJoinUrl(Session $session, string $displayName): string
    {
        $meetingId = $session->backend_meeting_id;

        if (empty($meetingId)) {
            $result = $this->createMeeting($session);
            if ($result['success']) {
                $meetingId = $result['meetingId'];
                $session->backend_meeting_id = $meetingId;

                $config = $session->backend_config ? json_decode($session->backend_config, true) : [];
                $config['zoom_join_url'] = $result['joinUrl'] ?? null;
                $config['zoom_password'] = $result['password'] ?? null;
                $session->backend_config = json_encode($config);

                $session->save(false);
            } else {
                return '#error';
            }
        }

        $config = $session->backend_config ? json_decode($session->backend_config, true) : [];

        if (isset($config['zoom_join_url'])) {
            return $this->appendDisplayName($config['zoom_join_url'], $displayName);
        }

        // Fallback
        $result = $this->apiRequest('GET', '/meetings/' . $meetingId);
        if ($result['success'] && isset($result['data']['join_url'])) {
            return $this->appendDisplayName($result['data']['join_url'], $displayName);
        }

        return '#error';
    }
}
