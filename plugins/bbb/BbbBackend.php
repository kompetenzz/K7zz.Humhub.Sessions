<?php

namespace humhub\modules\sessions\plugins\bbb;

use humhub\modules\sessions\plugins\BaseVideoBackend;
use humhub\modules\sessions\models\Session;
use humhub\modules\sessions\models\Recording;
use humhub\modules\user\models\User;
use BigBlueButton\BigBlueButton;
use BigBlueButton\Parameters\{
    CreateMeetingParameters,
    IsMeetingRunningParameters,
    JoinMeetingParameters,
    GetRecordingsParameters,
    PublishRecordingsParameters,
    EndMeetingParameters
};
use BigBlueButton\Enum\Role;
use humhub\libs\UUID;
use Yii;
use yii\helpers\Url;

/**
 * BigBlueButton backend implementation.
 */
class BbbBackend extends BaseVideoBackend
{
    /**
     * @var BigBlueButton|null
     */
    private $bbb;

    /**
     * Get BBB API client instance
     * @return BigBlueButton|null
     */
    private function getBbbClient(): ?BigBlueButton
    {
        if ($this->bbb === null) {
            $baseUrl = rtrim($this->getSetting('url', ''), '/') . '/';
            $secret = $this->getSetting('secret', '');

            if (empty($baseUrl) || empty($secret)) {
                return null;
            }

            $this->bbb = new BigBlueButton($baseUrl, $secret);
        }

        return $this->bbb;
    }

    /**
     * @inheritdoc
     */
    public function getId(): string
    {
        return 'bbb';
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'BigBlueButton';
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
        // BigBlueButton logo - simplified blue circle with play button
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
            <circle cx="50" cy="50" r="48" fill="#283593"/>
            <polygon points="38,28 38,72 75,50" fill="#fff"/>
        </svg>';
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return Yii::t('SessionsModule.backend', 'Open-source web conferencing system for online learning');
    }

    /**
     * @inheritdoc
     */
    public function isConfigured(): bool
    {
        $url = $this->getSetting('url');
        $secret = $this->getSetting('secret');
        return !empty($url) && !empty($secret);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsFormClass(): ?string
    {
        return 'humhub\modules\sessions\plugins\bbb\BbbSettingsForm';
    }

    /**
     * @inheritdoc
     * BBB-specific session options (beyond common fields in Session model).
     */
    public function getSessionConfigFields(): array
    {
        return [
            'webcamsOnlyForModerator' => [
                'type' => 'checkbox',
                'label' => Yii::t('SessionsModule.bbb', 'Webcams only for moderators'),
                'hint' => Yii::t('SessionsModule.bbb', 'Participants cannot share their webcam'),
                'default' => false,
            ],
            'lockSettingsDisableMic' => [
                'type' => 'checkbox',
                'label' => Yii::t('SessionsModule.bbb', 'Disable microphone for participants'),
                'default' => false,
            ],
            'lockSettingsDisablePrivateChat' => [
                'type' => 'checkbox',
                'label' => Yii::t('SessionsModule.bbb', 'Disable private chat'),
                'default' => false,
            ],
            'lockSettingsDisablePublicChat' => [
                'type' => 'checkbox',
                'label' => Yii::t('SessionsModule.bbb', 'Disable public chat'),
                'default' => false,
            ],
            'maxParticipants' => [
                'type' => 'number',
                'label' => Yii::t('SessionsModule.bbb', 'Max participants'),
                'hint' => Yii::t('SessionsModule.bbb', '0 = unlimited'),
                'default' => 0,
            ],
            'welcome' => [
                'type' => 'textarea',
                'label' => Yii::t('SessionsModule.bbb', 'Welcome message'),
                'default' => '',
            ],
            'layout' => [
                'type' => 'radio',
                'label' => Yii::t('SessionsModule.bbb', 'Layout'),
                'default' => 'CUSTOM_LAYOUT',
                'options' => [
                    'CUSTOM_LAYOUT' => Yii::t('SessionsModule.bbb', 'Custom Layout'),
                    'SMART_LAYOUT' => Yii::t('SessionsModule.bbb', 'Smart Layout'),
                    'PRESENTATION_FOCUS' => Yii::t('SessionsModule.bbb', 'Presentation Focus'),
                    'VIDEO_FOCUS' => Yii::t('SessionsModule.bbb', 'Video Focus'),
                ],
                'descriptions' => [
                    'CUSTOM_LAYOUT' => Yii::t('SessionsModule.bbb', 'User defined layout'),
                    'SMART_LAYOUT' => Yii::t('SessionsModule.bbb', 'Automagically adjusts based on participants and shared content'),
                    'PRESENTATION_FOCUS' => Yii::t('SessionsModule.bbb', 'Set focus on shared content'),
                    'VIDEO_FOCUS' => Yii::t('SessionsModule.bbb', 'Set focus on video participants'),
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function createMeeting(Session $session): array
    {
        $bbb = $this->getBbbClient();
        if (!$bbb) {
            throw new \Exception('BBB backend not configured');
        }

        // Build exit URL
        $container = $session->content->container ?? null;
        $exitUrl = $container
            ? $container->createUrl('/sessions/session/exit')
            : Url::to('/sessions/session/exit');

        // Build description with public join link if enabled
        $description = $session->description ?? '';
        if ($session->public_token && $session->public_join) {
            $anonymousJoinUrl = Url::to('/sessions/public/join/' . $session->public_token, true);
            $description .= "\n\n<br><br>" . Yii::t('SessionsModule.base', 'Public join link: <a href="{link}">{link}</a>', [
                'link' => $anonymousJoinUrl
            ]);
        }

        // Moderator info message
        $moderatorInfo = Yii::t(
            'SessionsModule.base',
            'You are the moderator of this session. You have additional permissions and responsibilities.'
        );

        $moderatorInfo .= ($session->has_waitingroom
            ? Yii::t('SessionsModule.base', ' Participants will wait until a moderator accepts them.')
            : Yii::t('SessionsModule.base', ' Participants will enter directly.'));

        // Get layout from backend config
        $layout = $session->getBackendConfigValue('layout', 'CUSTOM_LAYOUT');

        // Create meeting parameters
        $p = (new CreateMeetingParameters($session->uuid, $session->title))
            ->setRecord((bool) $session->allow_recording)
            ->setAllowStartStopRecording((bool) $session->allow_recording)
            ->setWelcome($description)
            ->setMuteOnStart((bool) $session->mute_on_entry)
            ->setAllowModsToUnmuteUsers(true)
            ->setAllowModsToEjectCameras(true)
            ->setAllowPromoteGuestToModerator(true)
            ->setBreakout(false)
            ->setMeetingKeepEvents(true)
            ->setGuestPolicy(
                $session->has_waitingroom ? "ASK_MODERATOR" : "ALWAYS_ACCEPT"
            )
            ->setModeratorOnlyMessage($moderatorInfo)
            ->setLogoutURL(Yii::$app->urlManager->createAbsoluteUrl($exitUrl . "?highlight=" . $session->id))
            ->setMeetingLayout($layout);

        // Add presentation if available
        if ($session->presentation_file_id > 0) {
            $presentationUrl = Url::to('/sessions/public/download', true)
                . "?id=" . $session->id
                . "&type=presentation";

            try {
                $p->addPresentation(
                    $presentationUrl,
                    file_get_contents($presentationUrl),
                    $session->name . "_presentation.pdf"
                );
            } catch (\Exception $e) {
                Yii::error("Failed to add presentation: " . $e->getMessage(), 'sessions');
            }
        }

        // Create meeting
        $r = $bbb->createMeeting($p);
        if (!$r->success()) {
            Yii::error("BBB-CreateMeeting failed for session {$session->name} ({$session->id}): " . $r->getMessage(), 'sessions');
            throw new \Exception('Failed to create BBB meeting: ' . $r->getMessage());
        }

        return [
            'meetingId' => $session->uuid,
            'internalMeetingId' => $r->getMeetingId(),
        ];
    }

    /**
     * @inheritdoc
     */
    public function joinUrl(Session $session, User $user, bool $isModerator): string
    {
        $bbb = $this->getBbbClient();
        if (!$bbb) {
            throw new \Exception('BBB backend not configured');
        }

        $jp = (new JoinMeetingParameters(
            $session->uuid,
            $user->displayName,
            $isModerator ? Role::MODERATOR : Role::VIEWER
        ))
            ->setUserID($user->email ?? $user->id);

        // Add avatar
        if ($user->getProfileImage()) {
            $jp->setAvatarURL(Url::to($user->getProfileImage()->getUrl(), true));
        }

        // Add camera background if available
        if ($session->camera_bg_image_file_id > 0) {
            $cameraBgImageUrl = Url::to('/sessions/public/download', true)
                . "?id=" . $session->id
                . "&type=camera-bg-image&inline=true&embeddable=true";
            $jp->setWebcamBackgroundURL($cameraBgImageUrl);
        }

        return $bbb->getJoinMeetingURL($jp);
    }

    /**
     * @inheritdoc
     */
    public function anonymousJoinUrl(Session $session, string $displayName): string
    {
        $bbb = $this->getBbbClient();
        if (!$bbb) {
            throw new \Exception('BBB backend not configured');
        }

        $jp = (new JoinMeetingParameters($session->uuid, $displayName, Role::VIEWER))
            ->setUserID(UUID::v4());

        return $bbb->getJoinMeetingURL($jp);
    }

    /**
     * @inheritdoc
     */
    public function isRunning(Session $session): bool
    {
        $bbb = $this->getBbbClient();
        if (!$bbb) {
            return false;
        }

        try {
            return $bbb->isMeetingRunning(new IsMeetingRunningParameters($session->uuid))->isRunning();
        } catch (\Exception $e) {
            Yii::error("Failed to check if meeting is running: " . $e->getMessage(), 'sessions');
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function endMeeting(Session $session): bool
    {
        $bbb = $this->getBbbClient();
        if (!$bbb) {
            return false;
        }

        try {
            $params = new EndMeetingParameters($session->uuid, $session->moderator_pw);
            $response = $bbb->endMeeting($params);
            return $response->success();
        } catch (\Exception $e) {
            Yii::error("Failed to end meeting: " . $e->getMessage(), 'sessions');
            return false;
        }
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
        return true;
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
    public function supportsCameraBackground(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsLayoutOptions(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getRecordings(Session $session): array
    {
        $bbb = $this->getBbbClient();
        if (!$bbb) {
            return [];
        }

        try {
            $params = new GetRecordingsParameters();
            $params->setMeetingID($session->uuid);

            $response = $bbb->getRecordings($params);
            if ($response && $response->success()) {
                $recordings = [];
                foreach ($response->getRecords() as $record) {
                    $recordings[] = Recording::fromBbbRecord($record);
                }
                return $recordings;
            }
        } catch (\Exception $e) {
            Yii::error("BBB-GetRecordings failed for session {$session->name}: " . $e->getMessage(), 'sessions');
        }

        return [];
    }

    /**
     * @inheritdoc
     */
    public function publishRecording(string $recordingId, bool $publish): bool
    {
        $bbb = $this->getBbbClient();
        if (!$bbb) {
            return false;
        }

        try {
            $params = new PublishRecordingsParameters($recordingId, $publish);
            $response = $bbb->publishRecordings($params);
            return $response->getReturnCode() === 'SUCCESS';
        } catch (\Exception $e) {
            Yii::error("BBB-PublishRecordings failed for record {$recordingId}: " . $e->getMessage(), 'sessions');
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteRecording(string $recordingId): bool
    {
        $bbb = $this->getBbbClient();
        if (!$bbb) {
            return false;
        }

        try {
            $params = new \BigBlueButton\Parameters\DeleteRecordingsParameters($recordingId);
            $response = $bbb->deleteRecordings($params);
            return $response->getReturnCode() === 'SUCCESS';
        } catch (\Exception $e) {
            Yii::error("BBB-DeleteRecordings failed for record {$recordingId}: " . $e->getMessage(), 'sessions');
            return false;
        }
    }
}
