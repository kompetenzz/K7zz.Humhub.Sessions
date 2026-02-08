<?php

namespace humhub\modules\sessions\assets;

use yii\web\AssetBundle;

class SessionAssets extends AssetBundle
{
    public $sourcePath = '@sessions/resources';

    public $publishOptions = [
        'forceCopy' => true,
        'only' => [
            'js/*.js',
        ],
    ];

    public $js = [
        'js/Slugifyer.js',
        'js/SessionLauncher.js',
    ];
}
