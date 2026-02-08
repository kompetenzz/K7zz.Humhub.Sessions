<?php

namespace humhub\modules\sessions\plugins\opentalk;

use humhub\modules\sessions\Module;
use Yii;
use yii\base\Model;

/**
 * Settings form for OpenTalk backend configuration.
 */
class OpentalkSettingsForm extends Model
{
    /**
     * @var string OpenTalk API URL
     */
    public $apiUrl;

    /**
     * @var string API authentication token
     */
    public $apiToken;

    /**
     * @var string Frontend URL (if different from API URL)
     */
    public $frontendUrl;

    /**
     * @var bool Enable recordings feature
     */
    public $enableRecordings = false;

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
            [['apiUrl', 'apiToken'], 'required'],
            [['apiUrl', 'frontendUrl'], 'url', 'defaultScheme' => 'https'],
            [['apiUrl', 'apiToken', 'frontendUrl'], 'string', 'max' => 500],
            [['apiUrl', 'apiToken', 'frontendUrl'], 'trim'],
            ['enableRecordings', 'boolean'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'apiUrl' => Yii::t('SessionsModule.opentalk', 'API URL'),
            'apiToken' => Yii::t('SessionsModule.opentalk', 'API Token'),
            'frontendUrl' => Yii::t('SessionsModule.opentalk', 'Frontend URL'),
            'enableRecordings' => Yii::t('SessionsModule.opentalk', 'Enable Recordings'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeHints()
    {
        return [
            'apiUrl' => Yii::t('SessionsModule.opentalk', 'The OpenTalk API endpoint URL (e.g., https://your-opentalk-server.com/api).'),
            'apiToken' => Yii::t('SessionsModule.opentalk', 'API authentication token for your OpenTalk server.'),
            'frontendUrl' => Yii::t('SessionsModule.opentalk', 'Optional: Frontend URL if different from API URL. Leave empty to use API URL.'),
            'enableRecordings' => Yii::t('SessionsModule.opentalk', 'Enable recording management if your OpenTalk server supports it.'),
        ];
    }

    /**
     * Load settings from module.
     */
    public function loadSettings(): void
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('sessions');

        $this->apiUrl = $module->settings->get('opentalk.apiUrl', '');
        $this->apiToken = $module->settings->get('opentalk.apiToken', '');
        $this->frontendUrl = $module->settings->get('opentalk.frontendUrl', '');
        $this->enableRecordings = (bool) $module->settings->get('opentalk.enableRecordings', false);
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

        $module->settings->set('opentalk.apiUrl', $this->apiUrl);
        $module->settings->set('opentalk.apiToken', $this->apiToken);
        $module->settings->set('opentalk.frontendUrl', $this->frontendUrl);
        $module->settings->set('opentalk.enableRecordings', $this->enableRecordings ? '1' : '0');

        return true;
    }
}
