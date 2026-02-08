<?php

namespace humhub\modules\sessions\services;

use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\sessions\interfaces\VideoBackendInterface;
use humhub\modules\sessions\models\forms\ContainerSettingsForm;
use humhub\modules\sessions\models\forms\GlobalSettingsForm;
use yii\base\Component;

/**
 * Registry for video backend providers.
 * Manages registration and retrieval of available backends.
 */
class BackendRegistry extends Component
{
    /**
     * @var VideoBackendInterface[]
     */
    private static $backends = [];

    /**
     * Register a backend
     * @param VideoBackendInterface $backend
     */
    public static function register(VideoBackendInterface $backend): void
    {
        static::$backends[$backend->getId()] = $backend;
    }

    /**
     * Get a specific backend by ID
     * @param string $id Backend identifier (e.g., 'bbb', 'jitsi')
     * @return VideoBackendInterface|null
     */
    public static function get(string $id): ?VideoBackendInterface
    {
        return static::$backends[$id] ?? null;
    }

    /**
     * Get all registered backends
     * @return VideoBackendInterface[]
     */
    public static function getAll(): array
    {
        return static::$backends;
    }

    /**
     * Get only configured backends
     * @return VideoBackendInterface[]
     */
    public static function getConfigured(): array
    {
        return array_filter(static::$backends, function($backend) {
            return $backend->isConfigured();
        });
    }

    /**
     * Get globally allowed backends (configured and enabled by admin).
     * @return VideoBackendInterface[]
     */
    public static function getGloballyAllowed(): array
    {
        $allowedIds = GlobalSettingsForm::getAllowedBackendIds();

        return array_filter(static::$backends, function($backend) use ($allowedIds) {
            return $backend->isConfigured() && in_array($backend->getId(), $allowedIds);
        });
    }

    /**
     * Get backends allowed for a specific container.
     * Respects both global and container-level settings.
     * @param ContentContainerActiveRecord|null $container
     * @return VideoBackendInterface[]
     */
    public static function getAllowedForContainer(?ContentContainerActiveRecord $container = null): array
    {
        if ($container === null) {
            return static::getGloballyAllowed();
        }

        $allowedIds = ContainerSettingsForm::getAllowedBackendIds($container);

        return array_filter(static::$backends, function($backend) use ($allowedIds) {
            return $backend->isConfigured() && in_array($backend->getId(), $allowedIds);
        });
    }

    /**
     * Check if a backend is allowed for a container.
     * @param string $backendId
     * @param ContentContainerActiveRecord|null $container
     * @return bool
     */
    public static function isAllowedForContainer(string $backendId, ?ContentContainerActiveRecord $container = null): bool
    {
        $allowed = static::getAllowedForContainer($container);
        return isset($allowed[$backendId]);
    }

    /**
     * Check if a backend exists
     * @param string $id
     * @return bool
     */
    public static function has(string $id): bool
    {
        return isset(static::$backends[$id]);
    }

    /**
     * Get backend names as key-value pairs (for dropdowns)
     * @param bool $onlyConfigured Only include configured backends
     * @return array
     */
    public static function getBackendOptions(bool $onlyConfigured = true): array
    {
        $backends = $onlyConfigured ? static::getConfigured() : static::getAll();
        $options = [];
        foreach ($backends as $backend) {
            $options[$backend->getId()] = $backend->getName();
        }
        return $options;
    }

    /**
     * Get backend options for a specific container (respects permissions).
     * @param ContentContainerActiveRecord|null $container
     * @return array [id => name]
     */
    public static function getBackendOptionsForContainer(?ContentContainerActiveRecord $container = null): array
    {
        $backends = static::getAllowedForContainer($container);
        $options = [];
        foreach ($backends as $backend) {
            $options[$backend->getId()] = $backend->getName();
        }
        return $options;
    }

    /**
     * Unregister a backend (mainly for testing)
     * @param string $id
     */
    public static function unregister(string $id): void
    {
        unset(static::$backends[$id]);
    }

    /**
     * Clear all backends (mainly for testing)
     */
    public static function clear(): void
    {
        static::$backends = [];
    }
}
