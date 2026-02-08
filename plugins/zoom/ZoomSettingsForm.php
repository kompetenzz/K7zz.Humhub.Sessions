<?php

namespace humhub\modules\sessions\plugins\zoom;

use humhub\modules\sessions\Module;
use Yii;
use yii\base\Model;

/**
 * Settings form for Zoom backend configuration.
 *
 * Uses Zoom Server-to-Server OAuth for authentication.
 *
 * @see https://developers.zoom.us/docs/internal-apps/create/
 */
class ZoomSettingsForm extends Model
{
    /**
     * @var string Zoom Account ID
     */
    public $accountId;

    /**
     * @var string OAuth Client ID
     */
    public $clientId;

    /**
     * @var string OAuth Client Secret
     */
    public $clientSecret;

    /**
     * @var string Zoom User ID or email for creating meetings (default: 'me')
     */
    public $userId;

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
            [['accountId', 'clientId', 'clientSecret'], 'required'],
            [['accountId', 'clientId', 'clientSecret'], 'string', 'max' => 255],
            ['userId', 'string', 'max' => 255],
            [['accountId', 'clientId', 'clientSecret', 'userId'], 'trim'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'accountId' => Yii::t('SessionsModule.zoom', 'Account ID'),
            'clientId' => Yii::t('SessionsModule.zoom', 'Client ID'),
            'clientSecret' => Yii::t('SessionsModule.zoom', 'Client Secret'),
            'userId' => Yii::t('SessionsModule.zoom', 'User ID'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeHints()
    {
        return [
            'accountId' => Yii::t('SessionsModule.zoom', 'Your Zoom Account ID. Found in the Zoom App Marketplace under your Server-to-Server OAuth app.'),
            'clientId' => Yii::t('SessionsModule.zoom', 'OAuth Client ID from your Zoom Server-to-Server OAuth app.'),
            'clientSecret' => Yii::t('SessionsModule.zoom', 'OAuth Client Secret from your Zoom Server-to-Server OAuth app.'),
            'userId' => Yii::t('SessionsModule.zoom', 'Optional: Zoom User ID or email for creating meetings. Leave empty to use "me" (the app owner).'),
        ];
    }

    /**
     * Load settings from module.
     */
    public function loadSettings(): void
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('sessions');

        $this->accountId = $module->settings->get('zoom.accountId', '');
        $this->clientId = $module->settings->get('zoom.clientId', '');
        $this->clientSecret = $module->settings->get('zoom.clientSecret', '');
        $this->userId = $module->settings->get('zoom.userId', 'me');
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

        $module->settings->set('zoom.accountId', $this->accountId);
        $module->settings->set('zoom.clientId', $this->clientId);
        $module->settings->set('zoom.clientSecret', $this->clientSecret);
        $module->settings->set('zoom.userId', $this->userId ?: 'me');

        return true;
    }
}
