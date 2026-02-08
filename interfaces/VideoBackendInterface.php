<?php

namespace humhub\modules\sessions\interfaces;

use humhub\modules\sessions\models\Session;
use humhub\modules\user\models\User;

/**
 * Interface for video conferencing backend providers.
 * Each backend (BBB, Jitsi, Opentalk, Zoom) must implement this interface.
 */
interface VideoBackendInterface
{
    // ========== Identification ==========

    /**
     * Get unique backend identifier
     * @return string e.g., 'bbb', 'jitsi', 'opentalk', 'zoom'
     */
    public function getId(): string;

    /**
     * Get human-readable backend name
     * @return string e.g., 'BigBlueButton', 'Jitsi Meet'
     */
    public function getName(): string;

    /**
     * Get FontAwesome icon class
     * @return string e.g., 'fa-video', 'fa-broadcast-tower'
     */
    public function getIcon(): string;

    /**
     * Get backend description
     * @return string
     */
    public function getDescription(): string;

    // ========== Configuration ==========

    /**
     * Check if backend is properly configured
     * @return bool
     */
    public function isConfigured(): bool;

    /**
     * Get settings form class name
     * @return string|null Full class name or null if no settings form
     */
    public function getSettingsFormClass(): ?string;

    /**
     * Get session-specific settings fields.
     * Returns field definitions for backend-specific session options.
     *
     * @return array Field definitions: [
     *   'fieldName' => [
     *     'type' => 'text|checkbox|select|number',
     *     'label' => 'Field Label',
     *     'hint' => 'Help text',
     *     'default' => 'default value',
     *     'options' => ['key' => 'label'], // for select type
     *   ]
     * ]
     */
    public function getSessionConfigFields(): array;

    // ========== Session Management ==========

    /**
     * Create a new meeting on the backend server
     * @param Session $session
     * @return array Meeting data (e.g., meeting ID, internal ID)
     * @throws \Exception if meeting creation fails
     */
    public function createMeeting(Session $session): array;

    /**
     * Generate join URL for a user
     * @param Session $session
     * @param User $user
     * @param bool $isModerator
     * @return string Join URL
     * @throws \Exception if URL generation fails
     */
    public function joinUrl(Session $session, User $user, bool $isModerator): string;

    /**
     * Generate anonymous join URL
     * @param Session $session
     * @param string $displayName Guest display name
     * @return string Join URL
     * @throws \Exception if backend doesn't support anonymous join
     */
    public function anonymousJoinUrl(Session $session, string $displayName): string;

    /**
     * Check if meeting is currently running
     * @param Session $session
     * @return bool
     */
    public function isRunning(Session $session): bool;

    /**
     * End a meeting
     * @param Session $session
     * @return bool Success
     */
    public function endMeeting(Session $session): bool;

    // ========== Feature Support ==========

    /**
     * Does backend support recording?
     * @return bool
     */
    public function supportsRecordings(): bool;

    /**
     * Does backend support waiting room/lobby?
     * @return bool
     */
    public function supportsWaitingRoom(): bool;

    /**
     * Does backend support uploading presentation files?
     * @return bool
     */
    public function supportsPresentationUpload(): bool;

    /**
     * Does backend support public/guest join without authentication?
     * @return bool
     */
    public function supportsPublicJoin(): bool;

    /**
     * Does backend support camera background images?
     * @return bool
     */
    public function supportsCameraBackground(): bool;

    /**
     * Does backend support layout options?
     * @return bool
     */
    public function supportsLayoutOptions(): bool;

    /**
     * Does backend support being embedded in an iframe?
     * If false, the controller should redirect directly instead of embedding.
     * @return bool
     */
    public function supportsEmbed(): bool;

    // ========== Recordings (optional) ==========

    /**
     * Get recordings for a session
     * @param Session $session
     * @return array Array of Recording objects
     * @throws \Exception if backend doesn't support recordings
     */
    public function getRecordings(Session $session): array;

    /**
     * Publish or unpublish a recording
     * @param string $recordingId Backend-specific recording ID
     * @param bool $publish
     * @return bool Success
     * @throws \Exception if backend doesn't support recordings
     */
    public function publishRecording(string $recordingId, bool $publish): bool;

    /**
     * Delete a recording
     * @param string $recordingId Backend-specific recording ID
     * @return bool Success
     * @throws \Exception if backend doesn't support recordings
     */
    public function deleteRecording(string $recordingId): bool;
}
