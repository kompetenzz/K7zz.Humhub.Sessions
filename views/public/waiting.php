<?php

use yii\helpers\Html;

/**
 * @var \humhub\modules\sessions\models\Session $session
 * @var string $token
 */
?>

<div class="container" style="max-width: 500px; margin-top: 50px;">
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

            <div class="text-center" style="padding: 20px 0;">
                <i class="fa fa-clock-o" style="font-size: 48px; color: #999;"></i>
                <h4><?= Yii::t('SessionsModule.views', 'Waiting for the session to start...') ?></h4>
                <p class="text-muted">
                    <?= Yii::t('SessionsModule.views', 'The session has not started yet. Please wait for a moderator to begin the meeting.') ?>
                </p>
            </div>

            <div class="text-center">
                <a href="<?= \yii\helpers\Url::to(['join', 'token' => $token]) ?>" class="btn btn-secondary">
                    <i class="fa fa-refresh"></i> <?= Yii::t('SessionsModule.views', 'Check again') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh every 10 seconds
setTimeout(function() {
    window.location.reload();
}, 10000);
</script>
