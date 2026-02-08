<?php

namespace humhub\modules\sessions\plugins\jitsi;

use humhub\modules\sessions\Module;
use Yii;
use yii\base\Model;

/**
 * Settings form for Jitsi Meet backend configuration.
 */
class JitsiSettingsForm extends Model
{
    /**
     * @var string Jitsi Meet domain (e.g., meet.jit.si or your own server)
     */
    public $domain;

    /**
     * @var string Optional JWT secret for authenticated rooms
     */
    public $jwtSecret;

    /**
     * @var string Optional JWT app ID
     */
    public $jwtAppId;

    /**
     * @var string Optional room name prefix
     */
    public $roomPrefix;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->loadSettings();
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['domain', 'required'],
            ['domain', 'string', 'max' => 255],
            ['domain', 'validateDomain'],
            [['jwtSecret', 'jwtAppId', 'roomPrefix'], 'string', 'max' => 255],
            [['jwtSecret', 'jwtAppId', 'roomPrefix'], 'trim'],
        ];
    }

    /**
     * Validate domain format.
     */
    public function validateDomain($attribute, $params)
    {
        $value = $this->$attribute;

        // Remove protocol if present
        $value = preg_replace('#^https?://#', '', $value);
        $this->$attribute = rtrim($value, '/');

        // Basic domain validation
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-._]+[a-zA-Z0-9]$/', $this->$attribute)) {
            $this->addError($attribute, Yii::t('SessionsModule.jitsi', 'Invalid domain format.'));
        }
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'domain' => Yii::t('SessionsModule.jitsi', 'Jitsi Meet Domain'),
            'jwtSecret' => Yii::t('SessionsModule.jitsi', 'JWT Secret'),
            'jwtAppId' => Yii::t('SessionsModule.jitsi', 'JWT App ID'),
            'roomPrefix' => Yii::t('SessionsModule.jitsi', 'Room Name Prefix'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeHints()
    {
        return [
            'domain' => Yii::t('SessionsModule.jitsi', 'The Jitsi Meet server domain (e.g., meet.jit.si or your own server). Do not include https://.'),
            'jwtSecret' => Yii::t('SessionsModule.jitsi', 'Optional: JWT secret for authenticated rooms. Leave empty for public Jitsi servers.'),
            'jwtAppId' => Yii::t('SessionsModule.jitsi', 'Optional: JWT app ID. Required if using JWT authentication.'),
            'roomPrefix' => Yii::t('SessionsModule.jitsi', 'Optional: Prefix for room names (e.g., your organization name).'),
        ];
    }

    /**
     * Load settings from module.
     */
    public function loadSettings(): void
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('sessions');

        $this->domain = $module->settings->get('jitsi.domain', 'meet.jit.si');
        $this->jwtSecret = $module->settings->get('jitsi.jwtSecret', '');
        $this->jwtAppId = $module->settings->get('jitsi.jwtAppId', '');
        $this->roomPrefix = $module->settings->get('jitsi.roomPrefix', '');
    }

    /**
     * Save settings to module.
     */
    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        /** @var Module $module */
        $module = Yii::$app->getModule('sessions');

        $module->settings->set('jitsi.domain', $this->domain);
        $module->settings->set('jitsi.jwtSecret', $this->jwtSecret);
        $module->settings->set('jitsi.jwtAppId', $this->jwtAppId);
        $module->settings->set('jitsi.roomPrefix', $this->roomPrefix);

        return true;
    }
}
