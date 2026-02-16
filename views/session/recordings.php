<?php

use humhub\modules\sessions\models\Recording;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var \humhub\modules\ui\view\components\View $this
 * @var \humhub\modules\sessions\models\Session $session
 * @var Recording[] $recordings
 * @var bool $canAdminister
 */

$this->pageTitle = Yii::t('SessionsModule.views', 'Recordings') . ' – ' . Html::encode($session->title ?: $session->name);

$container = $this->context->contentContainer ?? null;
$urlFunc = $container
    ? fn($route, $params = []) => $container->createUrl($route, $params)
    : fn($route, $params = []) => array_merge([$route], $params);
?>

<div class="container" style="max-width: 800px; margin-top: 20px;">
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h3 class="mb-0">
                <i class="fa fa-circle text-danger"></i>
                <?= Yii::t('SessionsModule.views', 'Recordings') ?>
                – <?= Html::encode($session->title ?: $session->name) ?>
            </h3>
            <a href="<?= Url::to($urlFunc('/sessions/session/lobby', ['id' => $session->id])) ?>"
               class="btn btn-secondary btn-sm">
                <i class="fa fa-arrow-left"></i> <?= Yii::t('SessionsModule.views', 'Back to session') ?>
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($recordings)): ?>
                <div class="text-center text-muted" style="padding: 30px 20px;">
                    <i class="fa fa-film" style="font-size: 48px; opacity: 0.3;"></i>
                    <p style="margin-top: 15px;"><?= Yii::t('SessionsModule.views', 'No recordings available.') ?></p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recordings as $rec): ?>
                        <div class="list-group-item d-flex align-items-center flex-wrap gap-2 py-3">
                            <div class="flex-grow-1">
                                <strong>
                                    <?= Html::encode($rec->getDate()) ?>, <?= Html::encode($rec->getTime()) ?>
                                </strong>
                                <span class="text-muted small ms-2">
                                    <i class="fa fa-clock-o"></i> <?= Html::encode($rec->getDuration()) ?>
                                </span>

                                <?php if (!$rec->isPublished()): ?>
                                    <span class="badge bg-warning text-dark ms-2">
                                        <?= Yii::t('SessionsModule.views', 'Unpublished') ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex gap-1 flex-wrap align-items-center">
                                <?php foreach ($rec->getFormats() as $format): ?>
                                    <a href="<?= Html::encode($format['url']) ?>"
                                       class="btn btn-outline-primary btn-sm"
                                       target="_blank"
                                       title="<?= Html::encode($format['label']) ?>">
                                        <i class="fa <?= $format['icon'] ?>"></i>
                                        <?= Html::encode($format['label']) ?>
                                    </a>
                                <?php endforeach; ?>

                                <?php if (empty($rec->getFormats()) && $rec->getUrl()): ?>
                                    <a href="<?= Html::encode($rec->getUrl()) ?>"
                                       class="btn btn-outline-primary btn-sm"
                                       target="_blank">
                                        <i class="fa fa-play-circle"></i>
                                        <?= Yii::t('SessionsModule.views', 'Play') ?>
                                    </a>
                                <?php endif; ?>

                                <?php if ($canAdminister): ?>
                                    <a href="<?= Url::to($urlFunc('/sessions/session/publish-recording', [
                                        'id' => $session->id,
                                        'recordingId' => $rec->getId(),
                                        'publish' => $rec->isPublished() ? 0 : 1,
                                    ])) ?>"
                                       class="btn btn-sm <?= $rec->isPublished() ? 'btn-success' : 'btn-warning' ?>"
                                       title="<?= $rec->isPublished()
                                           ? Yii::t('SessionsModule.views', 'Unpublish')
                                           : Yii::t('SessionsModule.views', 'Publish') ?>">
                                        <i class="fa <?= $rec->isPublished() ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
