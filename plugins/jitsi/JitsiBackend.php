<?php

namespace humhub\modules\sessions\plugins\jitsi;

use humhub\modules\sessions\plugins\BaseVideoBackend;
use humhub\modules\sessions\models\Session;
use humhub\modules\user\models\User;
use Yii;

/**
 * Jitsi Meet video backend implementation.
 *
 * Jitsi is URL-based and doesn't require a server API.
 * Optionally supports JWT for private rooms.
 */
class JitsiBackend extends BaseVideoBackend
{
    /**
     * @inheritdoc
     */
    public function getId(): string
    {
        return 'jitsi';
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Jitsi Meet';
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
        // Jitsi Meet logo - blue circle with stylized person icon
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
            <circle cx="50" cy="50" r="48" fill="#17a8e3"/>
            <circle cx="50" cy="35" r="12" fill="#fff"/>
            <ellipse cx="50" cy="70" rx="20" ry="15" fill="#fff"/>
        </svg>';
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return Yii::t('SessionsModule.jitsi', 'Free, open-source video conferencing. No account required. Uses public or self-hosted Jitsi servers.');
    }

    /**
     * @inheritdoc
     */
    public function getSettingsFormClass(): ?string
    {
        return JitsiSettingsForm::class;
    }

    /**
     * @inheritdoc
     */
    public function isConfigured(): bool
    {
        $domain = $this->getSetting('domain');
        return !empty($domain);
    }

    /**
     * @inheritdoc
     * Jitsi-specific session options (beyond common fields in Session model).
     */
    public function getSessionConfigFields(): array
    {
        return [
            'startWithVideoMuted' => [
                'type' => 'checkbox',
                'label' => Yii::t('SessionsModule.jitsi', 'Start with video muted'),
                'hint' => Yii::t('SessionsModule.jitsi', 'Participants join with camera off'),
                'default' => false,
            ],
            'disableDeepLinking' => [
                'type' => 'checkbox',
                'label' => Yii::t('SessionsModule.jitsi', 'Disable app download prompt'),
                'hint' => Yii::t('SessionsModule.jitsi', 'Skip the mobile app download suggestion'),
                'default' => true,
            ],
        ];
    }

    /**
     * Get Jitsi domain.
     */
    protected function getDomain(): string
    {
        return rtrim($this->getSetting('domain', 'meet.jit.si'), '/');
    }

    /**
     * Get JWT secret for authenticated rooms (optional).
     */
    protected function getJwtSecret(): ?string
    {
        $secret = $this->getSetting('jwtSecret');
        return !empty($secret) ? $secret : null;
    }

    /**
     * Get JWT app ID (required if using JWT).
     */
    protected function getJwtAppId(): ?string
    {
        $appId = $this->getSetting('jwtAppId');
        return !empty($appId) ? $appId : null;
    }

    /**
     * Check if JWT authentication is enabled.
     */
    protected function isJwtEnabled(): bool
    {
        return $this->getJwtSecret() !== null && $this->getJwtAppId() !== null;
    }

    /**
     * @inheritdoc
     */
    public function createMeeting(Session $session): array
    {
        // Jitsi doesn't require pre-creation - rooms are created on-the-fly
        // We just generate a unique room name based on the session UUID
        $roomName = $this->generateRoomName($session);

        return [
            'success' => true,
            'meetingId' => $roomName,
            'message' => 'Jitsi room ready'
        ];
    }

    /**
     * Generate a unique room name for the session.
     */
    protected function generateRoomName(Session $session): string
    {
        // Use session UUID to ensure uniqueness
        // Replace dashes with empty string for cleaner URLs
        $uuid = str_replace('-', '', $session->uuid);

        // Optionally prefix with a configured room prefix
        $prefix = $this->getSetting('roomPrefix', '');
        if (!empty($prefix)) {
            return $prefix . '_' . $uuid;
        }

        return 'humhub_' . $uuid;
    }

    /**
     * @inheritdoc
     */
    public function joinUrl(Session $session, User $user, bool $isModerator): string
    {
        $roomName = $session->backend_meeting_id ?: $this->generateRoomName($session);
        $domain = $this->getDomain();

        $baseUrl = "https://{$domain}/{$roomName}";

        $params = [];

        // Set display name
        $displayName = $user->displayName;
        $params['userInfo.displayName'] = $displayName;

        // Set email if available
        if (!empty($user->email)) {
            $params['userInfo.email'] = $user->email;
        }

        // Configure based on session settings
        $config = $this->buildConfigParams($session, $isModerator);
        if (!empty($config)) {
            $params['config'] = $config;
        }

        // Add JWT if configured
        if ($this->isJwtEnabled()) {
            $jwt = $this->generateJwt($session, $user, $isModerator);
            if ($jwt) {
                $params['jwt'] = $jwt;
            }
        }

        // Build URL with hash parameters (Jitsi style)
        $url = $baseUrl;
        if (!empty($params)) {
            $url .= '#' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }

    /**
     * Build Jitsi configuration parameters based on session settings.
     */
    protected function buildConfigParams(Session $session, bool $isModerator): string
    {
        $config = [];

        // Start with audio/video muted based on session settings
        if ($session->mute_on_entry) {
            $config['startWithAudioMuted'] = true;
            $config['startWithVideoMuted'] = true;
        }

        // Enable lobby (waiting room) if configured
        if ($session->has_waitingroom && $isModerator) {
            $config['enableLobby'] = true;
        }

        // Subject/title
        if (!empty($session->title)) {
            $config['subject'] = $session->title;
        }

        // Disable recording UI if not allowed
        if (!$session->allow_recording) {
            $config['disableRecording'] = true;
        }

        if (empty($config)) {
            return '';
        }

        return json_encode($config);
    }

    /**
     * Generate JWT token for authenticated access.
     */
    protected function generateJwt(Session $session, User $user, bool $isModerator): ?string
    {
        $secret = $this->getJwtSecret();
        $appId = $this->getJwtAppId();

        if (!$secret || !$appId) {
            return null;
        }

        $roomName = $session->backend_meeting_id ?: $this->generateRoomName($session);

        $payload = [
            'aud' => $appId,
            'iss' => $appId,
            'sub' => $this->getDomain(),
            'room' => $roomName,
            'exp' => time() + 86400, // 24 hours
            'nbf' => time() - 60,
            'context' => [
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->displayName,
                    'email' => $user->email ?? '',
                    'avatar' => $user->getProfileImage()->getUrl() ?? '',
                ],
                'features' => [
                    'recording' => $session->allow_recording,
                    'livestreaming' => false,
                    'transcription' => false,
                ],
            ],
            'moderator' => $isModerator,
        ];

        return $this->encodeJwt($payload, $secret);
    }

    /**
     * Encode JWT token (simple HS256 implementation).
     */
    protected function encodeJwt(array $payload, string $secret): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $segments = [];
        $segments[] = $this->base64UrlEncode(json_encode($header));
        $segments[] = $this->base64UrlEncode(json_encode($payload));

        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * Base64 URL-safe encoding.
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @inheritdoc
     */
    public function isRunning(Session $session): bool
    {
        // Jitsi doesn't provide an API to check if a room is active
        // Always return false - rooms are created on-the-fly when joining
        // The "Start" button works as "Join/Create" for Jitsi
        return false;
    }

    /**
     * @inheritdoc
     * Jitsi rooms are ephemeral and always available — no explicit start needed.
     */
    public function isAlwaysJoinable(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function endMeeting(Session $session): bool
    {
        // Jitsi rooms automatically end when all participants leave
        // There's no API to forcefully end a room
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsRecordings(): bool
    {
        // Jitsi supports local recordings but no server-side API
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsWaitingRoom(): bool
    {
        // Jitsi supports lobby feature
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsPresentationUpload(): bool
    {
        // Jitsi doesn't support presentation upload
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
        // Jitsi doesn't provide a server-side recording API
        return [];
    }

    /**
     * @inheritdoc
     */
    public function anonymousJoinUrl(Session $session, string $displayName): string
    {
        $roomName = $session->backend_meeting_id ?: $this->generateRoomName($session);
        $domain = $this->getDomain();

        $baseUrl = "https://{$domain}/{$roomName}";

        $params = [];
        $params['userInfo.displayName'] = $displayName;

        // Configure based on session settings
        $config = $this->buildConfigParams($session, false);
        if (!empty($config)) {
            $params['config'] = $config;
        }

        $url = $baseUrl;
        if (!empty($params)) {
            $url .= '#' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }
}
