<?php

use yii\helpers\Html;

/**
 * @var \humhub\modules\ui\view\components\View $this
 * @var \humhub\modules\sessions\models\Session $session
 * @var string $joinUrl
 */

$this->pageTitle = Html::encode($session->title ?: $session->name);
?>

<style>
    .session-frame-container {
        position: fixed;
        top: 50px;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1000;
        background: #000;
    }
    .session-frame-container iframe {
        width: 100%;
        height: 100%;
        border: 0;
    }
    .session-frame-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 50px;
        background: #333;
        color: #fff;
        display: flex;
        align-items: center;
        padding: 0 15px;
        z-index: 1001;
    }
    .session-frame-header .session-title {
        flex: 1;
        font-weight: bold;
        margin-left: 15px;
    }
    .session-frame-header .btn {
        margin-left: 10px;
    }
</style>

<div class="session-frame-header">
    <i class="fa fa-video-camera"></i>
    <span class="session-title"><?= Html::encode($session->title ?: $session->name) ?></span>

    <a href="<?= $joinUrl ?>" target="_blank" class="btn btn-sm btn-secondary" title="<?= Yii::t('SessionsModule.views', 'Open in new window') ?>">
        <i class="fa fa-external-link"></i>
    </a>

    <?php
    $exitUrl = $this->context->contentContainer
        ? $this->context->contentContainer->createUrl('/sessions/session/exit', ['highlight' => $session->id])
        : ['/sessions/session/exit', 'highlight' => $session->id];
    ?>
    <a href="<?= \yii\helpers\Url::to($exitUrl) ?>" class="btn btn-sm btn-danger">
        <i class="fa fa-times"></i> <?= Yii::t('SessionsModule.views', 'Leave') ?>
    </a>
</div>

<div class="session-frame-container">
    <iframe src="<?= Html::encode($joinUrl) ?>"
            allow="camera; microphone; display-capture; fullscreen; autoplay"
            allowfullscreen></iframe>
</div>
