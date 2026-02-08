<?php

namespace humhub\modules\sessions\models\forms;

use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\sessions\services\BackendRegistry;
use yii\base\Model;
use Yii;

/**
 * Container-specific settings form for the Sessions module.
 */
class ContainerSettingsForm extends Model
{
    /**
     * @var ContentContainerActiveRecord
     */
    public $contentContainer;

    /**
     * @var bool Add navigation item in container menu
     */
    public $addNavItem = true;

    /**
     * @var string Label for navigation item
     */
    public $navItemLabel = 'Sessions';

    /**
     * @var string Default backend for this container
     */
    public $defaultBackend = '';

    /**
     * @var array Allowed backends for this container (subset of globally allowed)
     */
    public $allowedBackends = [];

    /**
     * @var bool Use global backend settings (inherit from global)
     */
    public $inheritBackends = true;

    private $settings;

    public function init()
    {
        parent::init();

        if ($this->contentContainer) {
            $this->settings = Yii::$app->getModule('sessions')->settings->contentContainer($this->contentContainer);
            $this->addNavItem = (bool) $this->settings->get('addNavItem', true);
            $this->navItemLabel = $this->settings->get('navItemLabel', 'Sessions');
            $this->defaultBackend = $this->settings->get('defaultBackend', '');
            $this->inheritBackends = (bool) $this->settings->get('inheritBackends', true);

            $stored = $this->settings->get('allowedBackends');
            if ($stored !== null) {
                $this->allowedBackends = json_decode($stored, true) ?: [];
            } else {
                // Default: inherit from global
                $this->allowedBackends = GlobalSettingsForm::getAllowedBackendIds();
            }
        }
    }

    public function rules(): array
    {
        return [
            [['addNavItem', 'inheritBackends'], 'boolean'],
            [['navItemLabel'], 'string', 'max' => 50],
            [['defaultBackend'], 'string', 'max' => 20],
            [['allowedBackends'], 'each', 'rule' => ['string']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'addNavItem' => Yii::t('SessionsModule.config', 'Show in navigation'),
            'navItemLabel' => Yii::t('SessionsModule.config', 'Navigation label'),
            'defaultBackend' => Yii::t('SessionsModule.config', 'Default video backend'),
            'allowedBackends' => Yii::t('SessionsModule.config', 'Allowed Backends'),
            'inheritBackends' => Yii::t('SessionsModule.config', 'Use global backend settings'),
        ];
    }

    public function attributeHints(): array
    {
        return [
            'inheritBackends' => Yii::t('SessionsModule.config', 'If enabled, uses the globally configured allowed backends. Disable to customize which backends are available in this space/profile.'),
            'allowedBackends' => Yii::t('SessionsModule.config', 'Select which backends are available. Only backends that are globally enabled can be selected.'),
        ];
    }

    /**
     * Get backends available for selection in this container.
     * Returns only backends that are globally allowed.
     * @return array [id => ['name' => string, 'configured' => bool, 'icon' => string, 'globallyAllowed' => bool]]
     */
    public function getAvailableBackends(): array
    {
        $globallyAllowed = GlobalSettingsForm::getAllowedBackendIds();
        $result = [];

        foreach (BackendRegistry::getAll() as $id => $backend) {
            $isGloballyAllowed = in_array($id, $globallyAllowed);
            $result[$id] = [
                'name' => $backend->getName(),
                'configured' => $backend->isConfigured(),
                'icon' => $backend->getIcon(),
                'globallyAllowed' => $isGloballyAllowed,
            ];
        }
        return $result;
    }

    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $this->settings->set('addNavItem', $this->addNavItem);
        $this->settings->set('navItemLabel', $this->navItemLabel);
        $this->settings->set('defaultBackend', $this->defaultBackend);
        $this->settings->set('inheritBackends', $this->inheritBackends);
        $this->settings->set('allowedBackends', json_encode($this->allowedBackends));

        return true;
    }

    /**
     * Get allowed backend IDs for a specific container.
     * @param ContentContainerActiveRecord $container
     * @return array Backend IDs
     */
    public static function getAllowedBackendIds(ContentContainerActiveRecord $container): array
    {
        $settings = Yii::$app->getModule('sessions')->settings->contentContainer($container);
        $inheritBackends = (bool) $settings->get('inheritBackends', true);

        if ($inheritBackends) {
            return GlobalSettingsForm::getAllowedBackendIds();
        }

        $stored = $settings->get('allowedBackends');
        if ($stored !== null) {
            $containerBackends = json_decode($stored, true) ?: [];
            // Ensure container backends are subset of global
            $globalBackends = GlobalSettingsForm::getAllowedBackendIds();
            return array_intersect($containerBackends, $globalBackends);
        }

        return GlobalSettingsForm::getAllowedBackendIds();
    }
}
