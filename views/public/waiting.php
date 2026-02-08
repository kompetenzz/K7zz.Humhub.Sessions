<?php

use yii\helpers\Html;

/**
 * @var \humhub\modules\sessions\models\Session $session
 * @var string $token
 */
?>

<div class="container" style="max-width: 500px; margin-top: 50px;">
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="fa fa-video-camera"></i>
                <?= Html::encode($session->title ?: $session->name) ?>
            </h3>
        </div>
        <div class="panel-body text-center">
            <div style="padding: 30px 0;">
                <i class="fa fa-clock-o" style="font-size: 48px; color: #999;"></i>
                <h4><?= Yii::t('SessionsModule.views', 'Waiting for the session to start...') ?></h4>
                <p class="text-muted">
                    <?= Yii::t('SessionsModule.views', 'The session has not started yet. Please wait for a moderator to begin the meeting.') ?>
                </p>
            </div>

            <hr>

            <p>
                <a href="<?= \yii\helpers\Url::to(['join', 'token' => $token]) ?>" class="btn btn-default">
                    <i class="fa fa-refresh"></i> <?= Yii::t('SessionsModule.views', 'Check again') ?>
                </a>
            </p>
        </div>
    </div>
</div>

<script>
// Auto-refresh every 10 seconds
setTimeout(function() {
    window.location.reload();
}, 10000);
</script>
