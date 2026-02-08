<?php

namespace humhub\modules\sessions\models;

use Yii;

/**
 * Unified Recording model for all video backends.
 * Wraps backend-specific recording data in a common interface.
 */
class Recording
{
    /**
     * @var string Backend identifier ('bbb', 'zoom', etc.)
     */
    public $backend;

    /**
     * @var string Backend-specific recording ID
     */
    public $recordingId;

    /**
     * @var mixed Original backend-specific recording object
     */
    public $originalRecord;

    /**
     * @var string|null Playback URL
     */
    public $url;

    /**
     * @var int Start timestamp (Unix timestamp)
     */
    public $startTime;

    /**
     * @var int End timestamp (Unix timestamp)
     */
    public $endTime;

    /**
     * @var int Duration in seconds
     */
    public $duration;

    /**
     * @var string Recording state ('published', 'unpublished', 'processing', etc.)
     */
    public $state;

    /**
     * @var array Image preview URLs
     */
    public $imagePreviews = [];

    /**
     * @var string|null Recording name/title
     */
    public $name;

    /**
     * @var array Additional metadata
     */
    public $metadata = [];

    /**
     * Constructor
     * @param array $data Recording data
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Get playback URL
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Get formatted date
     * @return string
     */
    public function getDate(): string
    {
        return Yii::$app->formatter->asDate($this->startTime);
    }

    /**
     * Get formatted time
     * @return string
     */
    public function getTime(): string
    {
        return Yii::$app->formatter->asTime($this->startTime, "HH:mm");
    }

    /**
     * Get formatted duration
     * @return string
     */
    public function getDuration(): string
    {
        return gmdate("H:i:s", $this->duration);
    }

    /**
     * Get image previews
     * @return array
     */
    public function getImagePreviews(): array
    {
        return $this->imagePreviews;
    }

    /**
     * Check if recording has image previews
     * @return bool
     */
    public function hasImagePreviews(): bool
    {
        return !empty($this->imagePreviews);
    }

    /**
     * Check if recording is published
     * @return bool
     */
    public function isPublished(): bool
    {
        return $this->state === 'published';
    }

    /**
     * Check if recording is processing
     * @return bool
     */
    public function isProcessing(): bool
    {
        return $this->state === 'processing';
    }

    /**
     * Get recording ID
     * @return string
     */
    public function getId(): string
    {
        return $this->recordingId;
    }

    /**
     * Get recording name
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Create Recording from BBB Record object
     * @param mixed $record BigBlueButton Record object
     * @return static
     */
    public static function fromBbbRecord($record): self
    {
        $format = $record->getPlaybackFormats()[0] ?? null;

        return new self([
            'backend' => 'bbb',
            'recordingId' => $record->getRecordId(),
            'originalRecord' => $record,
            'url' => $format?->getUrl(),
            'startTime' => intval($record->getStartTime() / 1000),
            'endTime' => intval($record->getEndTime() / 1000),
            'duration' => intval(($record->getEndTime() - $record->getStartTime()) / 1000),
            'state' => $record->getState(),
            'imagePreviews' => $format?->getImagePreviews() ?? [],
            'name' => $record->getName(),
            'metadata' => $record->getMetas(),
        ]);
    }

    /**
     * Create Recording from Zoom recording data
     * @param array $data Zoom API recording data
     * @return static
     */
    public static function fromZoomData(array $data): self
    {
        return new self([
            'backend' => 'zoom',
            'recordingId' => $data['id'] ?? $data['uuid'] ?? '',
            'originalRecord' => $data,
            'url' => $data['play_url'] ?? $data['share_url'] ?? null,
            'startTime' => isset($data['start_time']) ? strtotime($data['start_time']) : null,
            'endTime' => isset($data['start_time']) && isset($data['duration'])
                ? strtotime($data['start_time']) + ($data['duration'] * 60)
                : null,
            'duration' => ($data['duration'] ?? 0) * 60, // Zoom duration is in minutes
            'state' => $data['status'] ?? 'unknown',
            'name' => $data['topic'] ?? null,
            'metadata' => $data,
        ]);
    }

    /**
     * Create Recording from OpenTalk recording data
     * @param array $data OpenTalk API recording data
     * @param Session $session The session this recording belongs to
     * @return static
     */
    public static function createFromOpentalk(array $data, Session $session): self
    {
        $startTime = isset($data['started_at']) ? strtotime($data['started_at']) : null;
        $endTime = isset($data['ended_at']) ? strtotime($data['ended_at']) : null;
        $duration = ($startTime && $endTime) ? ($endTime - $startTime) : ($data['duration'] ?? 0);

        return new self([
            'backend' => 'opentalk',
            'recordingId' => $data['id'] ?? '',
            'originalRecord' => $data,
            'url' => $data['download_url'] ?? $data['playback_url'] ?? null,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'duration' => $duration,
            'state' => $data['published'] ?? false ? 'published' : 'unpublished',
            'name' => $data['title'] ?? $session->title ?? null,
            'metadata' => $data,
        ]);
    }
}
