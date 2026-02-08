<?php

namespace humhub\modules\sessions\services;

use humhub\modules\sessions\models\Session;
use humhub\modules\sessions\models\Recording;
use humhub\modules\sessions\interfaces\VideoBackendInterface;
use humhub\modules\sessions\Module;
use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\user\models\User;
use Yii;
use yii\base\Component;

/**
 * Central service for managing video conference sessions.
 * Delegates operations to the appropriate backend based on session's backend_type.
 */
class SessionService extends Component
{
    /**
     * @var Module
     */
    private $module;

    public function __construct(Module $module, $config = [])
    {
        $this->module = $module;
        parent::__construct($config);
    }

    /**
     * Get backend for a session
     * @param Session $session
     * @return VideoBackendInterface|null
     */
    public function getBackend(Session $session): ?VideoBackendInterface
    {
        return BackendRegistry::get($session->backend_type);
    }

    /**
     * Get backend by ID
     * @param string $backendId
     * @return VideoBackendInterface|null
     */
    public function getBackendById(string $backendId): ?VideoBackendInterface
    {
        return BackendRegistry::get($backendId);
    }

    // ========== Session Queries ==========

    /**
     * Get base query for sessions
     * @param ContentContainerActiveRecord|null $container
     * @param bool $everywhere Search across all containers
     * @return \yii\db\ActiveQuery
     */
    private function getQueryStarter(?ContentContainerActiveRecord $container = null, bool $everywhere = false)
    {
        if ($everywhere) {
            return Session::find();
        }
        return Session::find()->contentContainer($container);
    }

    /**
     * List sessions for a container
     * @param ContentContainerActiveRecord|null $container
     * @param bool $onlyEnabled
     * @return Session[]
     */
    public function list(?ContentContainerActiveRecord $container = null, bool $onlyEnabled = false): array
    {
        $query = $this->getQueryStarter($container)
            ->alias('session')
            ->joinWith('content')
            ->where(['session.deleted_at' => null]);

        if ($onlyEnabled) {
            $query->andWhere(['session.enabled' => true]);
        }

        return $query->orderBy(['session.ord' => SORT_ASC, 'session.id' => SORT_DESC])->all();
    }

    /**
     * List sessions with extended filter options (for global overview)
     * @param string $scope 'global'|'spaces'|'users'|'all'
     * @param bool $onlyEnabled
     * @return Session[]
     */
    public function listAll(string $scope = 'global', bool $onlyEnabled = false): array
    {
        $query = Session::find()
            ->alias('session')
            ->joinWith('content content')
            ->where(['session.deleted_at' => null]);

        if ($onlyEnabled) {
            $query->andWhere(['session.enabled' => true]);
        }

        switch ($scope) {
            case 'global':
                // Sessions without container (global sessions)
                $query->andWhere(['content.contentcontainer_id' => null]);
                break;

            case 'spaces':
                // Sessions in spaces only
                $query->innerJoin(
                    'contentcontainer cc',
                    'content.contentcontainer_id = cc.id'
                )->andWhere(['cc.class' => \humhub\modules\space\models\Space::class]);
                break;

            case 'users':
                // Sessions in user profiles only
                $query->innerJoin(
                    'contentcontainer cc',
                    'content.contentcontainer_id = cc.id'
                )->andWhere(['cc.class' => \humhub\modules\user\models\User::class]);
                break;

            case 'all':
            default:
                // All sessions (no filter)
                break;
        }

        return $query->orderBy(['session.ord' => SORT_ASC, 'session.id' => SORT_DESC])->all();
    }

    /**
     * Get a single session by ID
     * @param int|null $id
     * @param ContentContainerActiveRecord|null $container
     * @param bool $everywhere
     * @return Session|null
     */
    public function get(?int $id, ?ContentContainerActiveRecord $container = null, bool $everywhere = false): ?Session
    {
        if ($id === null) {
            return null;
        }

        return $this->getQueryStarter($container, $everywhere)
            ->alias('session')
            ->joinWith('content')
            ->where(['session.id' => $id, 'session.deleted_at' => null])
            ->one();
    }

    /**
     * Get session by UUID
     * @param string $uuid
     * @return Session|null
     */
    public function getByUuid(string $uuid): ?Session
    {
        return Session::find()
            ->where(['uuid' => $uuid, 'deleted_at' => null])
            ->one();
    }

    /**
     * Get session by public token
     * @param string $token
     * @return Session|null
     */
    public function getByPublicToken(string $token): ?Session
    {
        if (empty($token)) {
            return null;
        }

        return Session::find()
            ->where([
                'public_token' => $token,
                'public_join' => true,
                'deleted_at' => null
            ])
            ->one();
    }

    // ========== Session Operations ==========

    /**
     * Start a session (creates meeting on backend)
     * @param Session $session
     * @return array|null Meeting data or null on failure
     */
    public function start(Session $session): ?array
    {
        $backend = $this->getBackend($session);
        if (!$backend) {
            Yii::error("No backend found for session {$session->id} (type: {$session->backend_type})", 'sessions');
            return null;
        }

        if (!$backend->isConfigured()) {
            Yii::error("Backend {$session->backend_type} is not configured", 'sessions');
            return null;
        }

        try {
            return $backend->createMeeting($session);
        } catch (\Exception $e) {
            Yii::error("Failed to start session {$session->id}: " . $e->getMessage(), 'sessions');
            return null;
        }
    }

    /**
     * Get join URL for a user
     * @param Session $session
     * @param User $user
     * @param bool $isModerator
     * @return string|null
     */
    public function joinUrl(Session $session, User $user, bool $isModerator = false): ?string
    {
        $backend = $this->getBackend($session);
        if (!$backend || !$backend->isConfigured()) {
            return null;
        }

        try {
            return $backend->joinUrl($session, $user, $isModerator);
        } catch (\Exception $e) {
            Yii::error("Failed to get join URL for session {$session->id}: " . $e->getMessage(), 'sessions');
            return null;
        }
    }

    /**
     * Get anonymous join URL
     * @param Session $session
     * @param string $displayName
     * @return string|null
     */
    public function anonymousJoinUrl(Session $session, string $displayName): ?string
    {
        $backend = $this->getBackend($session);
        if (!$backend || !$backend->isConfigured()) {
            return null;
        }

        if (!$backend->supportsPublicJoin()) {
            return null;
        }

        try {
            return $backend->anonymousJoinUrl($session, $displayName);
        } catch (\Exception $e) {
            Yii::error("Failed to get anonymous join URL for session {$session->id}: " . $e->getMessage(), 'sessions');
            return null;
        }
    }

    /**
     * Check if session is running
     * @param Session $session
     * @return bool
     */
    public function isRunning(Session $session): bool
    {
        $backend = $this->getBackend($session);
        if (!$backend || !$backend->isConfigured()) {
            return false;
        }

        try {
            return $backend->isRunning($session);
        } catch (\Exception $e) {
            Yii::error("Failed to check if session {$session->id} is running: " . $e->getMessage(), 'sessions');
            return false;
        }
    }

    /**
     * End a session
     * @param Session $session
     * @return bool
     */
    public function end(Session $session): bool
    {
        $backend = $this->getBackend($session);
        if (!$backend || !$backend->isConfigured()) {
            return false;
        }

        try {
            return $backend->endMeeting($session);
        } catch (\Exception $e) {
            Yii::error("Failed to end session {$session->id}: " . $e->getMessage(), 'sessions');
            return false;
        }
    }

    /**
     * Soft-delete a session
     * @param int|null $id
     * @param ContentContainerActiveRecord|null $container
     * @return bool
     */
    public function delete(?int $id, ?ContentContainerActiveRecord $container = null): bool
    {
        $session = $this->get($id, $container);
        if (!$session) {
            return false;
        }

        $session->deleted_at = time();
        return $session->save(false);
    }

    // ========== Recordings ==========

    /**
     * Get recordings for a session
     * @param Session $session
     * @return Recording[]
     */
    public function getRecordings(Session $session): array
    {
        $backend = $this->getBackend($session);
        if (!$backend || !$backend->isConfigured() || !$backend->supportsRecordings()) {
            return [];
        }

        try {
            return $backend->getRecordings($session);
        } catch (\Exception $e) {
            Yii::error("Failed to get recordings for session {$session->id}: " . $e->getMessage(), 'sessions');
            return [];
        }
    }

    /**
     * Publish or unpublish a recording
     * @param Session $session
     * @param string $recordingId
     * @param bool $publish
     * @return bool
     */
    public function publishRecording(Session $session, string $recordingId, bool $publish): bool
    {
        $backend = $this->getBackend($session);
        if (!$backend || !$backend->isConfigured() || !$backend->supportsRecordings()) {
            return false;
        }

        try {
            return $backend->publishRecording($recordingId, $publish);
        } catch (\Exception $e) {
            Yii::error("Failed to publish recording {$recordingId}: " . $e->getMessage(), 'sessions');
            return false;
        }
    }

    /**
     * Delete a recording
     * @param Session $session
     * @param string $recordingId
     * @return bool
     */
    public function deleteRecording(Session $session, string $recordingId): bool
    {
        $backend = $this->getBackend($session);
        if (!$backend || !$backend->isConfigured() || !$backend->supportsRecordings()) {
            return false;
        }

        try {
            return $backend->deleteRecording($recordingId);
        } catch (\Exception $e) {
            Yii::error("Failed to delete recording {$recordingId}: " . $e->getMessage(), 'sessions');
            return false;
        }
    }

    // ========== Backend Feature Checks ==========

    /**
     * Check if session's backend supports recordings
     * @param Session $session
     * @return bool
     */
    public function supportsRecordings(Session $session): bool
    {
        $backend = $this->getBackend($session);
        return $backend ? $backend->supportsRecordings() : false;
    }

    /**
     * Check if session's backend supports waiting room
     * @param Session $session
     * @return bool
     */
    public function supportsWaitingRoom(Session $session): bool
    {
        $backend = $this->getBackend($session);
        return $backend ? $backend->supportsWaitingRoom() : false;
    }

    /**
     * Check if session's backend supports presentation upload
     * @param Session $session
     * @return bool
     */
    public function supportsPresentationUpload(Session $session): bool
    {
        $backend = $this->getBackend($session);
        return $backend ? $backend->supportsPresentationUpload() : false;
    }

    /**
     * Check if session's backend supports public join
     * @param Session $session
     * @return bool
     */
    public function supportsPublicJoin(Session $session): bool
    {
        $backend = $this->getBackend($session);
        return $backend ? $backend->supportsPublicJoin() : false;
    }
}
