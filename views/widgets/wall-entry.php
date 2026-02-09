<?php

use humhub\modules\sessions\assets\SessionAssets;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var \humhub\modules\sessions\models\Session $session
 * @var \humhub\modules\sessions\interfaces\VideoBackendInterface|null $backend
 * @var bool $running
 */

SessionAssets::register($this);
$container = $session->content->container ?? null;
$urlFunc = $container
    ? fn($route, $params = []) => $container->createUrl($route, $params)
    : fn($route, $params = []) => array_merge([$route], $params);

// Collect feature tags
$features = [];
if ($session->has_waitingroom) {
    $features[] = '<i class="fa fa-clock-o"></i> ' . Yii::t('SessionsModule.views', 'Waiting Room');
}
if ($session->allow_recording) {
    $features[] = '<i class="fa fa-circle text-danger"></i> ' . Yii::t('SessionsModule.views', 'Recording');
}
if ($session->mute_on_entry) {
    $features[] = '<i class="fa fa-microphone-slash"></i> ' . Yii::t('SessionsModule.views', 'Muted on entry');
}
?>

<div class="session-wall-entry" style="color: #555; line-height: 2;">
    <?php // Backend line ?>
    <i class="fa fa-video-camera" style="width: 20px; text-align: center;"></i>
    <?= $backend ? Html::encode($backend->getName()) : Html::encode($session->backend_type) ?>

    <?php // Description line ?>
    <?php if ($session->description): ?>
        <br>
        <i class="fa fa-align-left" style="width: 20px; text-align: center;"></i>
        <?= Html::encode(mb_substr(strip_tags($session->description), 0, 200)) ?>
    <?php endif; ?>

    <?php // Features line ?>
    <?php if (!empty($features)): ?>
        <br>
        <i class="fa fa-cog" style="width: 20px; text-align: center;"></i>
        <?= implode(' &middot; ', $features) ?>
    <?php endif; ?>

    <?php // Image ?>
    <?php if ($session->outputImage): ?>
        <div style="margin-top: 8px;">
            <img src="<?= $session->outputImage->getUrl() ?>"
                 alt="" style="max-width: 300px; max-height: 150px; object-fit: cover; border-radius: 4px;">
        </div>
    <?php endif; ?>

    <?php // Action buttons ?>
    <div style="margin-top: 8px;">
        <?php if ($running && $session->canJoin()): ?>
            <a href="<?= Html::encode(Url::to($urlFunc('/sessions/session/lobby', ['id' => $session->id]))) ?>"
               class="btn btn-success btn-sm">
                <i class="fa fa-sign-in"></i> <?= Yii::t('SessionsModule.views', 'Join') ?>
            </a>
        <?php elseif ($session->canStart()): ?>
            <a href="<?= Html::encode(Url::to($urlFunc('/sessions/session/lobby', ['id' => $session->id]))) ?>"
               class="btn btn-primary btn-sm">
                <i class="fa fa-play"></i> <?= Yii::t('SessionsModule.views', 'Start') ?>
            </a>
        <?php endif; ?>
    </div>
</div>
