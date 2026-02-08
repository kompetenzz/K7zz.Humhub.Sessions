<?php

namespace humhub\modules\sessions\plugins\bbb;

use yii\base\Model;
use Yii;

/**
 * Settings form for BigBlueButton backend configuration.
 */
class BbbSettingsForm extends Model
{
    /**
     * @var string BBB server URL
     */
    public $url = '';

    /**
     * @var string BBB shared secret
     */
    public $secret = '';

    /**
     * @var \humhub\modules\ui\form\widgets\ActiveForm Settings manager
     */
    private $settings;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->settings = Yii::$app->getModule('sessions')->settings;
        $this->url = $this->settings->get('bbb.url', '');
        $this->secret = $this->settings->get('bbb.secret', '');
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['url', 'secret'], 'required'],
            ['url', 'url', 'defaultScheme' => 'https'],
            ['url', 'string', 'max' => 255],
            ['secret', 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'url' => Yii::t('SessionsModule.config', 'BigBlueButton Server URL'),
            'secret' => Yii::t('SessionsModule.config', 'Shared Secret'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeHints(): array
    {
        return [
            'url' => Yii::t('SessionsModule.config', 'The URL of your BigBlueButton server (e.g., https://bbb.example.com/bigbluebutton/)'),
            'secret' => Yii::t('SessionsModule.config', 'The shared secret from your BigBlueButton server configuration'),
        ];
    }

    /**
     * Save settings
     * @return bool
     */
    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $this->settings->set('bbb.url', $this->url);
        $this->settings->set('bbb.secret', $this->secret);

        return true;
    }
}
