<?php

namespace humhub\modules\sessions\models;

use Yii;

/**
 * Unified Recording model for all video backends.
 * Wraps backend-specific recording data in a common interface.
 */
class Recording
{
    /** @var string Backend identifier ('bbb', 'zoom', etc.) */
    public $backend;

    /** @var string Backend-specific recording ID */
    public $recordingId;

    /** @var mixed Original backend-specific recording object */
    public $originalRecord;

    /** @var string|null Primary playback URL */
    public $url;

    /** @var int Start timestamp (Unix timestamp) */
    public $startTime;

    /** @var int End timestamp (Unix timestamp) */
    public $endTime;

    /** @var int Duration in seconds */
    public $duration;

    /** @var string Recording state ('published', 'unpublished', 'processing', etc.) */
    public $state;

    /** @var array Image preview URLs */
    public $imagePreviews = [];

    /** @var string|null Recording name/title */
    public $name;

    /** @var array Additional metadata */
    public $metadata = [];

    /**
     * @var array Playback formats: [['type' => 'presentation', 'url' => '...', 'label' => '...', 'icon' => '...'], ...]
     */
    public $formats = [];

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getDate(): string
    {
        return Yii::$app->formatter->asDate($this->startTime);
    }

    public function getTime(): string
    {
        return Yii::$app->formatter->asTime($this->startTime, "HH:mm");
    }

    public function getDuration(): string
    {
        return gmdate("H:i:s", $this->duration);
    }

    public function getImagePreviews(): array
    {
        return $this->imagePreviews;
    }

    public function hasImagePreviews(): bool
    {
        return !empty($this->imagePreviews);
    }

    public function isPublished(): bool
    {
        return $this->state === 'published';
    }

    public function isProcessing(): bool
    {
        return $this->state === 'processing';
    }

    public function getId(): string
    {
        return $this->recordingId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return array Playback formats
     */
    public function getFormats(): array
    {
        return $this->formats;
    }

    /**
     * Returns a human-readable label for a playback format type.
     */
    public static function formatLabel(string $type): string
    {
        return match ($type) {
            'presentation' => Yii::t('SessionsModule.base', 'Presentation'),
            'video' => Yii::t('SessionsModule.base', 'Video'),
            'podcast' => Yii::t('SessionsModule.base', 'Podcast'),
            'screenshare' => Yii::t('SessionsModule.base', 'Screenshare'),
            'notes' => Yii::t('SessionsModule.base', 'Notes'),
            default => ucfirst($type),
        };
    }

    /**
     * Returns a FontAwesome icon class for a playback format type.
     */
    public static function formatIcon(string $type): string
    {
        return match ($type) {
            'presentation' => 'fa-desktop',
            'video' => 'fa-film',
            'podcast' => 'fa-microphone',
            'screenshare' => 'fa-window-maximize',
            'notes' => 'fa-file-text-o',
            default => 'fa-play-circle',
        };
    }

    /**
     * Create Recording from BBB Record object
     */
    public static function fromBbbRecord($record): self
    {
        $playbackFormats = $record->getPlaybackFormats();
        $primaryFormat = $playbackFormats[0] ?? null;

        $formats = [];
        foreach ($playbackFormats as $f) {
            $formats[] = [
                'type' => $f->getType(),
                'url' => $f->getUrl(),
                'label' => self::formatLabel($f->getType()),
                'icon' => self::formatIcon($f->getType()),
            ];
        }

        return new self([
            'backend' => 'bbb',
            'recordingId' => $record->getRecordId(),
            'originalRecord' => $record,
            'url' => $primaryFormat?->getUrl(),
            'startTime' => intval($record->getStartTime() / 1000),
            'endTime' => intval($record->getEndTime() / 1000),
            'duration' => intval(($record->getEndTime() - $record->getStartTime()) / 1000),
            'state' => $record->getState(),
            'imagePreviews' => $primaryFormat?->getImagePreviews() ?? [],
            'name' => $record->getName(),
            'metadata' => $record->getMetas(),
            'formats' => $formats,
        ]);
    }

    /**
     * Create Recording from Zoom recording data
     */
    public static function fromZoomData(array $data): self
    {
        $url = $data['play_url'] ?? $data['share_url'] ?? null;
        $formatType = $data['format_type'] ?? 'video';

        return new self([
            'backend' => 'zoom',
            'recordingId' => $data['id'] ?? $data['uuid'] ?? '',
            'originalRecord' => $data,
            'url' => $url,
            'startTime' => isset($data['start_time']) ? strtotime($data['start_time']) : null,
            'endTime' => isset($data['start_time']) && isset($data['duration'])
                ? strtotime($data['start_time']) + ($data['duration'] * 60)
                : null,
            'duration' => ($data['duration'] ?? 0) * 60,
            'state' => $data['status'] ?? 'unknown',
            'name' => $data['topic'] ?? null,
            'metadata' => $data,
            'formats' => $url ? [[
                'type' => $formatType,
                'url' => $url,
                'label' => self::formatLabel($formatType),
                'icon' => self::formatIcon($formatType),
            ]] : [],
        ]);
    }

    /**
     * Create Recording from OpenTalk recording data
     */
    public static function createFromOpentalk(array $data, Session $session): self
    {
        $startTime = isset($data['started_at']) ? strtotime($data['started_at']) : null;
        $endTime = isset($data['ended_at']) ? strtotime($data['ended_at']) : null;
        $duration = ($startTime && $endTime) ? ($endTime - $startTime) : ($data['duration'] ?? 0);
        $url = $data['download_url'] ?? $data['playback_url'] ?? null;

        return new self([
            'backend' => 'opentalk',
            'recordingId' => $data['id'] ?? '',
            'originalRecord' => $data,
            'url' => $url,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'duration' => $duration,
            'state' => $data['published'] ?? false ? 'published' : 'unpublished',
            'name' => $data['title'] ?? $session->title ?? null,
            'metadata' => $data,
            'formats' => $url ? [['type' => 'video', 'url' => $url, 'label' => 'Video', 'icon' => 'fa-film']] : [],
        ]);
    }
}
