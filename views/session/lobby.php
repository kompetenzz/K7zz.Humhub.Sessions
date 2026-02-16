<?php

use humhub\modules\sessions\assets\SessionAssets;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var \humhub\modules\sessions\models\Session $session
 * @var bool $running
 * @var bool $alwaysJoinable
 * @var callable $urlFunc
 */

SessionAssets::register($this);
$this->pageTitle = Html::encode($session->title ?: $session->name);
?>

<div class="container" style="max-width: 500px; margin-top: 30px;">
    <div class="card">
        <?php if ($session->outputImage): ?>
            <div>
                <img src="<?= $session->outputImage->getUrl() ?>"
                     alt="" style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 4px 4px 0 0; display: block;">
            </div>
        <?php endif; ?>

        <div class="card-header">
            <h3 class="card-title">
                <i class="fa fa-video-camera"></i>
                <?= Html::encode($session->title ?: $session->name) ?>
            </h3>
        </div>
        <div class="card-body">
            <?php if ($session->description): ?>
                <p class="text-muted" style="margin-bottom: 15px;">
                    <?= Html::encode(mb_substr(strip_tags($session->description), 0, 300)) ?>
                </p>
                <hr>
            <?php endif; ?>

            <?php if ($alwaysJoinable): ?>
                <?php // Always-joinable backends (e.g. Jitsi) — always show Join button ?>
                <div class="text-center" style="margin-top: 15px;">
                    <a href="#"
                       class="btn btn-success btn-lg session-launch-window"
                       data-url="<?= Html::encode(Url::to($urlFunc('/sessions/session/start', ['id' => $session->id]), true)) ?>">
                        <i class="fa fa-sign-in"></i> <?= Yii::t('SessionsModule.views', 'Join Session') ?>
                    </a>
                </div>

            <?php elseif ($running && $session->canJoin()): ?>
                <div class="text-center" style="padding: 10px 0;">
                    <span class="badge bg-success" style="font-size: 14px; padding: 5px 12px;">
                        <i class="fa fa-circle"></i> <?= Yii::t('SessionsModule.views', 'Running') ?>
                    </span>
                </div>
                <div class="text-center" style="margin-top: 15px;">
                    <a href="#"
                       class="btn btn-success btn-lg session-launch-window"
                       data-url="<?= Html::encode(Url::to($urlFunc('/sessions/session/join', ['id' => $session->id]), true)) ?>">
                        <i class="fa fa-sign-in"></i> <?= Yii::t('SessionsModule.views', 'Join Session') ?>
                    </a>
                </div>

            <?php elseif ($session->canStart()): ?>
                <div class="text-center" style="padding: 10px 0;">
                    <span class="badge bg-secondary" style="font-size: 14px; padding: 5px 12px;">
                        <?= Yii::t('SessionsModule.views', 'Not started yet') ?>
                    </span>
                </div>
                <div class="text-center" style="margin-top: 15px;">
                    <a href="#"
                       class="btn btn-primary btn-lg session-launch-window"
                       data-url="<?= Html::encode(Url::to($urlFunc('/sessions/session/start', ['id' => $session->id]), true)) ?>">
                        <i class="fa fa-play"></i> <?= Yii::t('SessionsModule.views', 'Start Session') ?>
                    </a>
                </div>

            <?php else: ?>
                <div class="text-center" style="padding: 20px 0;">
                    <i class="fa fa-clock-o" style="font-size: 48px; color: #999;"></i>
                    <h4><?= Yii::t('SessionsModule.views', 'Waiting for the session to start...') ?></h4>
                    <p class="text-muted">
                        <?= Yii::t('SessionsModule.views', 'The session has not started yet. Please wait for a moderator to begin the meeting.') ?>
                    </p>
                </div>
                <div class="text-center">
                    <a href="<?= Url::to($urlFunc('/sessions/session/lobby', ['id' => $session->id])) ?>" class="btn btn-secondary">
                        <i class="fa fa-refresh"></i> <?= Yii::t('SessionsModule.views', 'Check again') ?>
                    </a>
                </div>
                <script>
                setTimeout(function() { window.location.reload(); }, 10000);
                </script>
            <?php endif; ?>
        </div>

        <?php if ($session->allow_recording): ?>
            <div class="card-footer text-center">
                <a href="<?= Url::to($urlFunc('/sessions/session/recordings', ['id' => $session->id])) ?>"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-film"></i> <?= Yii::t('SessionsModule.views', 'Recordings') ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
