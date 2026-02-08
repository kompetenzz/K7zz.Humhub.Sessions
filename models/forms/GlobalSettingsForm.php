<?php

namespace humhub\modules\sessions\models\forms;

use humhub\modules\sessions\services\BackendRegistry;
use yii\base\Model;
use Yii;

/**
 * Global settings form for the Sessions module.
 */
class GlobalSettingsForm extends Model
{
    /**
     * @var bool Add navigation item in top menu
     */
    public $addNavItem = true;

    /**
     * @var string Label for navigation item
     */
    public $navItemLabel = 'Sessions';

    /**
     * @var array Globally allowed backend IDs
     */
    public $allowedBackends = [];

    /**
     * @var \humhub\modules\ui\form\widgets\ActiveForm
     */
    private $settings;

    public function init()
    {
        parent::init();
        $this->settings = Yii::$app->getModule('sessions')->settings;
        $this->addNavItem = (bool) $this->settings->get('addNavItem', true);
        $this->navItemLabel = $this->settings->get('navItemLabel', 'Sessions');

        // Load allowed backends (default: all configured backends)
        $stored = $this->settings->get('allowedBackends');
        if ($stored !== null) {
            $this->allowedBackends = json_decode($stored, true) ?: [];
        } else {
            // Default: all backends allowed
            $this->allowedBackends = array_keys(BackendRegistry::getAll());
        }
    }

    public function rules(): array
    {
        return [
            [['addNavItem'], 'boolean'],
            [['navItemLabel'], 'string', 'max' => 50],
            [['allowedBackends'], 'each', 'rule' => ['string']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'addNavItem' => Yii::t('SessionsModule.config', 'Show in top navigation'),
            'navItemLabel' => Yii::t('SessionsModule.config', 'Navigation label'),
            'allowedBackends' => Yii::t('SessionsModule.config', 'Allowed Backends'),
        ];
    }

    public function attributeHints(): array
    {
        return [
            'allowedBackends' => Yii::t('SessionsModule.config', 'Select which video backends are available for use. Only configured backends can be enabled.'),
        ];
    }

    /**
     * Get all available backends with their configuration status.
     * @return array [id => ['name' => string, 'configured' => bool, 'icon' => string]]
     */
    public function getAvailableBackends(): array
    {
        $result = [];
        foreach (BackendRegistry::getAll() as $id => $backend) {
            $result[$id] = [
                'name' => $backend->getName(),
                'configured' => $backend->isConfigured(),
                'icon' => $backend->getIcon(),
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
        $this->settings->set('allowedBackends', json_encode($this->allowedBackends));

        return true;
    }

    /**
     * Get globally allowed backends.
     * @return array Backend IDs
     */
    public static function getAllowedBackendIds(): array
    {
        $settings = Yii::$app->getModule('sessions')->settings;
        $stored = $settings->get('allowedBackends');

        if ($stored !== null) {
            return json_decode($stored, true) ?: [];
        }

        // Default: all backends
        return array_keys(BackendRegistry::getAll());
    }
}
