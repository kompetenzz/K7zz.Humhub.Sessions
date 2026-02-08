<?php

use yii\helpers\Html;

/**
 * @var \humhub\modules\sessions\models\Session $session
 * @var string $joinUrl
 */
?>

<style>
    body { margin: 0; overflow: hidden; }
    .session-fullscreen {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
    }
    .session-fullscreen iframe {
        width: 100%;
        height: 100%;
        border: 0;
    }
</style>

<div class="session-fullscreen">
    <iframe src="<?= Html::encode($joinUrl) ?>"
            allow="camera; microphone; display-capture; fullscreen; autoplay"
            allowfullscreen></iframe>
</div>
