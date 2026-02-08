<?php

use humhub\modules\sessions\assets\SessionAssets;
use humhub\widgets\Button;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var \humhub\modules\sessions\models\Session $session
 * @var \humhub\modules\sessions\interfaces\VideoBackendInterface|null $backend
 */

SessionAssets::register($this);
$container = $session->content->container ?? null;
$urlFunc = $container
    ? fn($route, $params = []) => $container->createUrl($route, $params)
    : fn($route, $params = []) => array_merge([$route], $params);
?>

<div class="session-wall-entry">
    <div class="media">
        <?php if ($session->outputImage): ?>
            <div class="media-left">
                <img src="<?= $session->outputImage->getUrl() ?>"
                     alt="" class="media-object"
                     style="width: 80px; height: 60px; object-fit: cover; border-radius: 4px;">
            </div>
        <?php endif; ?>

        <div class="media-body">
            <h4 class="media-heading">
                <i class="fa fa-video-camera"></i>
                <?= Html::encode($session->title ?: $session->name) ?>

                <?php if ($backend): ?>
                    <small class="text-muted">
                        <i class="fa <?= $backend->getIcon() ?>"></i>
                        <?= Html::encode($backend->getName()) ?>
                    </small>
                <?php endif; ?>
            </h4>

            <?php if ($session->description): ?>
                <p><?= Html::encode(mb_substr(strip_tags($session->description), 0, 150)) ?>...</p>
            <?php endif; ?>

            <div style="margin-top: 10px;">
                <?php if ($session->canStart()): ?>
                    <a href="#"
                       class="btn btn-primary btn-sm session-launch-window"
                       data-url="<?= Html::encode(Url::to($urlFunc('/sessions/session/start', ['id' => $session->id]), true)) ?>">
                        <i class="fa fa-play"></i> <?= Yii::t('SessionsModule.views', 'Start / Join') ?>
                    </a>
                <?php elseif ($session->canJoin()): ?>
                    <a href="#"
                       class="btn btn-success btn-sm session-launch-window"
                       data-url="<?= Html::encode(Url::to($urlFunc('/sessions/session/join', ['id' => $session->id]), true)) ?>">
                        <i class="fa fa-sign-in"></i> <?= Yii::t('SessionsModule.views', 'Join') ?>
                    </a>
                <?php endif; ?>

                <?= Button::defaultType(Yii::t('SessionsModule.views', 'Details'))
                    ->link($urlFunc('/sessions/list', ['highlight' => $session->id]))
                    ->icon('info-circle')
                    ->sm() ?>
            </div>
        </div>
    </div>
</div>
