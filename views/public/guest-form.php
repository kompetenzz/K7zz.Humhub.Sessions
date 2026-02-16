<?php

use yii\helpers\Html;
use humhub\widgets\Button;

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

            <p><?= Yii::t('SessionsModule.views', 'Enter your name to join as a guest.') ?></p>

            <?= Html::beginForm(['join', 'token' => $token], 'post') ?>
                <div class="form-group">
                    <label for="displayName"><?= Yii::t('SessionsModule.views', 'Your Name') ?></label>
                    <?= Html::textInput('displayName', '', [
                        'class' => 'form-control',
                        'id' => 'displayName',
                        'required' => true,
                        'autofocus' => true,
                        'placeholder' => Yii::t('SessionsModule.views', 'Enter your name...')
                    ]) ?>
                </div>

                <?= Button::primary(Yii::t('SessionsModule.views', 'Join Session'))
                    ->icon('sign-in')
                    ->submit() ?>
            <?= Html::endForm() ?>
        </div>
    </div>
</div>
