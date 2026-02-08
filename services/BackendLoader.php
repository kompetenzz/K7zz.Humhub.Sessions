<?php

namespace humhub\modules\sessions\services;

use humhub\modules\sessions\interfaces\VideoBackendInterface;
use humhub\modules\sessions\Module;
use Yii;
use yii\base\Component;

/**
 * Dynamically loads video backends from the plugins directory.
 *
 * Scans the plugins/ directory for backend implementations.
 * Each backend must:
 * - Be in its own subdirectory (e.g., plugins/bbb/)
 * - Have a class named {Dirname}Backend (e.g., BbbBackend)
 * - Implement VideoBackendInterface
 *
 * This allows adding new backends without modifying Module.php.
 */
class BackendLoader extends Component
{
    /**
     * @var Module
     */
    private $module;

    /**
     * @var string Path to plugins directory
     */
    private $pluginsPath;

    /**
     * @var array Cached list of loaded backends
     */
    private static $loadedBackends = [];

    public function __construct(Module $module, $config = [])
    {
        $this->module = $module;
        $this->pluginsPath = dirname(__DIR__) . '/plugins';
        parent::__construct($config);
    }

    /**
     * Discover and register all backends from the plugins directory.
     */
    public function loadAll(): void
    {
        if (!empty(self::$loadedBackends)) {
            // Already loaded - just register
            foreach (self::$loadedBackends as $backend) {
                BackendRegistry::register($backend);
            }
            return;
        }

        $backends = $this->discoverBackends();

        foreach ($backends as $backend) {
            self::$loadedBackends[] = $backend;
            BackendRegistry::register($backend);
        }
    }

    /**
     * Discover all backend classes in the plugins directory.
     *
     * @return VideoBackendInterface[]
     */
    public function discoverBackends(): array
    {
        $backends = [];

        if (!is_dir($this->pluginsPath)) {
            Yii::warning("Plugins directory not found: {$this->pluginsPath}", 'sessions');
            return $backends;
        }

        $dirs = scandir($this->pluginsPath);

        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $dirPath = $this->pluginsPath . '/' . $dir;

            if (!is_dir($dirPath)) {
                continue;
            }

            // Skip BaseVideoBackend.php which is in the plugins root
            if (!is_dir($dirPath)) {
                continue;
            }

            $backend = $this->loadBackendFromDir($dir, $dirPath);
            if ($backend) {
                $backends[] = $backend;
            }
        }

        // Sort backends by name for consistent ordering
        usort($backends, fn($a, $b) => strcmp($a->getName(), $b->getName()));

        return $backends;
    }

    /**
     * Load a backend from a directory.
     *
     * Expected structure:
     * - plugins/bbb/BbbBackend.php -> class BbbBackend
     * - plugins/jitsi/JitsiBackend.php -> class JitsiBackend
     *
     * @param string $dirname Directory name (e.g., 'bbb')
     * @param string $dirPath Full path to directory
     * @return VideoBackendInterface|null
     */
    private function loadBackendFromDir(string $dirname, string $dirPath): ?VideoBackendInterface
    {
        // Convert dirname to class name: bbb -> Bbb, opentalk -> Opentalk
        $className = ucfirst(strtolower($dirname)) . 'Backend';
        $filePath = $dirPath . '/' . $className . '.php';

        if (!file_exists($filePath)) {
            // Try alternative: BBBBackend, JitsiBackend (preserve case)
            $className = ucfirst($dirname) . 'Backend';
            $filePath = $dirPath . '/' . $className . '.php';

            if (!file_exists($filePath)) {
                Yii::debug("No backend class found in {$dirPath}", 'sessions');
                return null;
            }
        }

        $fullClassName = "humhub\\modules\\sessions\\plugins\\{$dirname}\\{$className}";

        // Ensure class exists
        if (!class_exists($fullClassName)) {
            require_once $filePath;
        }

        if (!class_exists($fullClassName)) {
            Yii::warning("Backend class not found: {$fullClassName}", 'sessions');
            return null;
        }

        // Verify it implements the interface
        if (!is_subclass_of($fullClassName, VideoBackendInterface::class)) {
            Yii::warning("Class {$fullClassName} does not implement VideoBackendInterface", 'sessions');
            return null;
        }

        try {
            return new $fullClassName($this->module);
        } catch (\Exception $e) {
            Yii::error("Failed to instantiate backend {$fullClassName}: " . $e->getMessage(), 'sessions');
            return null;
        }
    }

    /**
     * Get the plugins directory path.
     *
     * @return string
     */
    public function getPluginsPath(): string
    {
        return $this->pluginsPath;
    }

    /**
     * Check if a specific backend exists.
     *
     * @param string $backendId
     * @return bool
     */
    public function backendExists(string $backendId): bool
    {
        $dirPath = $this->pluginsPath . '/' . $backendId;
        return is_dir($dirPath);
    }

    /**
     * Clear the cached backends (useful for testing).
     */
    public static function clearCache(): void
    {
        self::$loadedBackends = [];
    }
}
