<?php

use humhub\modules\sessions\assets\SessionAssets;
use humhub\modules\sessions\services\BackendRegistry;
use humhub\widgets\Button;
use yii\helpers\Html;

/**
 * @var \humhub\modules\ui\view\components\View $this
 * @var array $rows
 * @var int $highlightId
 * @var array $backends
 * @var string|null $scope Current filter scope (only in global view)
 * @var bool $isGlobalView Whether we're in global view (no container)
 */

SessionAssets::register($this);
$this->pageTitle = Yii::t('SessionsModule.views', 'Sessions');
?>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h1 class="mb-0"><?= Yii::t('SessionsModule.views', 'Video Sessions') ?></h1>
        <?php if (Yii::$app->user->can('humhub\modules\sessions\permissions\StartSession')): ?>
            <?= Button::primary(Yii::t('SessionsModule.views', 'Create Session'))
                ->link($this->context->contentContainer
                    ? $this->context->contentContainer->createUrl('/sessions/session/create')
                    : ['/sessions/session/create'])
                ->icon('plus') ?>
        <?php endif; ?>
    </div>
    <div class="card-body">

        <?php if (!empty($isGlobalView)): ?>
            <div class="btn-group mb-3" role="group">
                <?= Html::a(
                    '<i class="fa fa-globe"></i> ' . Yii::t('SessionsModule.views', 'Global'),
                    ['/sessions/list', 'scope' => 'global'],
                    ['class' => 'btn btn-sm ' . ($scope === 'global' ? 'btn-primary' : 'btn-secondary')]
                ) ?>
                <?= Html::a(
                    '<i class="fa fa-users"></i> ' . Yii::t('SessionsModule.views', 'Spaces'),
                    ['/sessions/list', 'scope' => 'spaces'],
                    ['class' => 'btn btn-sm ' . ($scope === 'spaces' ? 'btn-primary' : 'btn-secondary')]
                ) ?>
                <?= Html::a(
                    '<i class="fa fa-user"></i> ' . Yii::t('SessionsModule.views', 'Users'),
                    ['/sessions/list', 'scope' => 'users'],
                    ['class' => 'btn btn-sm ' . ($scope === 'users' ? 'btn-primary' : 'btn-secondary')]
                ) ?>
                <?= Html::a(
                    '<i class="fa fa-list"></i> ' . Yii::t('SessionsModule.views', 'All'),
                    ['/sessions/list', 'scope' => 'all'],
                    ['class' => 'btn btn-sm ' . ($scope === 'all' ? 'btn-primary' : 'btn-secondary')]
                ) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($rows)): ?>
            <div class="text-center text-muted" style="padding: 40px 20px;">
                <i class="fa fa-video-camera" style="font-size: 48px; opacity: 0.3;"></i>
                <p style="margin-top: 15px;"><?= Yii::t('SessionsModule.views', 'No sessions available.') ?></p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-top mb-0" style="--bs-table-cell-padding-x: .75rem; --bs-table-cell-padding-y: .6rem;">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 56px;"></th>
                            <th><?= Yii::t('SessionsModule.views', 'Session') ?></th>
                            <?php if (!empty($isGlobalView) && $scope !== 'global'): ?>
                                <th><?= Yii::t('SessionsModule.views', 'Location') ?></th>
                            <?php endif; ?>
                            <th style="white-space: nowrap;"><?= Yii::t('SessionsModule.views', 'Backend') ?></th>
                            <th><?= Yii::t('SessionsModule.views', 'Status') ?></th>
                            <th class="text-center" style="white-space: nowrap;"><?= Yii::t('SessionsModule.views', 'Options') ?></th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $model = $row['model'];
                            $running = $row['running'];
                            $backend = $row['backend'] ?? null;
                            $isHighlighted = $model->id === $highlightId;
                            $sessionContainer = $model->content->container ?? null;
                            $isPublic = isset($model->content) && $model->content->visibility == \humhub\modules\content\models\Content::VISIBILITY_PUBLIC;
                        ?>
                            <tr class="<?= $isHighlighted ? 'table-info' : '' ?>" data-session-id="<?= $model->id ?>">
                                <td style="padding-left: 1rem;">
                                    <?php if ($model->outputImage): ?>
                                        <img src="<?= $model->outputImage->getUrl() ?>"
                                             alt="" class="rounded"
                                             style="width: 48px; height: 48px; object-fit: cover; display: block;">
                                    <?php else: ?>
                                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; background: #e9ecef; border-radius: 6px;">
                                            <i class="fa fa-video-camera text-muted" style="font-size: 18px;"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= Html::encode($model->title ?: $model->name) ?></strong>
                                    <?php if ($model->description): ?>
                                        <br><small class="text-muted"><?= Html::encode(mb_substr(strip_tags($model->description), 0, 80)) ?></small>
                                    <?php endif; ?>
                                    <?php if ($model->created_at && $model->created_at > 86400): ?>
                                        <br><small class="text-muted"><?= Yii::$app->formatter->asDatetime($model->created_at, 'short') ?></small>
                                    <?php endif; ?>
                                </td>
                                <?php if (!empty($isGlobalView) && $scope !== 'global'): ?>
                                    <td>
                                        <?php if ($sessionContainer): ?>
                                            <?php
                                            $isSpace = $sessionContainer instanceof \humhub\modules\space\models\Space;
                                            $icon = $isSpace ? 'fa-users' : 'fa-user';
                                            ?>
                                            <a href="<?= $sessionContainer->getUrl() ?>">
                                                <i class="fa <?= $icon ?>"></i>
                                                <?= Html::encode($sessionContainer->displayName) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                <i class="fa fa-globe"></i>
                                                <?= Yii::t('SessionsModule.views', 'Global') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td style="white-space: nowrap;">
                                    <?php if ($backend): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 6px;">
                                            <?= $backend->getLogo(18) ?>
                                            <?= Html::encode($backend->getName()) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted"><?= Html::encode($model->backend_type) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($running): ?>
                                        <span class="badge bg-success"><i class="fa fa-circle"></i> <?= Yii::t('SessionsModule.views', 'Running') ?></span>
                                    <?php elseif (!$model->enabled): ?>
                                        <span class="badge bg-secondary"><?= Yii::t('SessionsModule.views', 'Disabled') ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><?= Yii::t('SessionsModule.views', 'Stopped') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center" style="white-space: nowrap;">
                                    <i class="fa <?= $isPublic ? 'fa-globe' : 'fa-lock' ?>"
                                       title="<?= $isPublic ? Yii::t('SessionsModule.views', 'Public') : Yii::t('SessionsModule.views', 'Private') ?>"
                                       style="color: <?= $isPublic ? '#5cb85c' : '#999' ?>; margin-right: 4px;"></i>
                                    <?php if ($model->has_waitingroom): ?>
                                        <i class="fa fa-clock-o" title="<?= Yii::t('SessionsModule.views', 'Waiting room enabled') ?>" style="color: #5cb85c; margin-right: 4px;"></i>
                                    <?php endif; ?>
                                    <?php if ($model->allow_recording): ?>
                                        <i class="fa fa-circle" title="<?= Yii::t('SessionsModule.views', 'Recording enabled') ?>" style="color: #d9534f;"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end" style="white-space: nowrap;">
                                    <?php
                                    $actionContainer = $sessionContainer ?? $this->context->contentContainer;
                                    $urlBase = $actionContainer ? function($route, $params = []) use ($actionContainer) {
                                        return $actionContainer->createUrl($route, $params);
                                    } : function($route, $params = []) {
                                        return array_merge([$route], $params);
                                    };

                                    $internalUrl = yii\helpers\Url::to($urlBase('/sessions/session/lobby', ['id' => $model->id]), true);
                                    $publicUrl = ($model->public_join && $model->public_token)
                                        ? yii\helpers\Url::to(['/sessions/public/join', 'token' => $model->public_token], true)
                                        : null;
                                    ?>

                                    <?php if ($model->canAdminister()): ?>
                                        <div class="btn-group btn-group-sm me-1">
                                            <button type="button"
                                                    class="btn btn-outline-secondary copy-url-btn"
                                                    data-url="<?= Html::encode($internalUrl) ?>"
                                                    title="<?= Yii::t('SessionsModule.views', 'Copy member link') ?>">
                                                <i class="fa fa-link"></i>
                                            </button>
                                            <?php if ($publicUrl): ?>
                                                <button type="button"
                                                        class="btn btn-outline-info copy-url-btn"
                                                        data-url="<?= Html::encode($publicUrl) ?>"
                                                        title="<?= Yii::t('SessionsModule.views', 'Copy public/guest link') ?>">
                                                    <i class="fa fa-globe"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($running && $model->canJoin()): ?>
                                        <a href="#"
                                           class="btn btn-success btn-sm session-launch-window"
                                           data-url="<?= Html::encode(yii\helpers\Url::to($urlBase('/sessions/session/join', ['id' => $model->id]), true)) ?>"
                                           title="<?= Yii::t('SessionsModule.views', 'Join') ?>">
                                            <i class="fa fa-sign-in"></i> <?= Yii::t('SessionsModule.views', 'Join') ?>
                                        </a>
                                    <?php elseif ($model->canStart()): ?>
                                        <a href="#"
                                           class="btn btn-primary btn-sm session-launch-window"
                                           data-url="<?= Html::encode(yii\helpers\Url::to($urlBase('/sessions/session/start', ['id' => $model->id]), true)) ?>"
                                           title="<?= Yii::t('SessionsModule.views', 'Start') ?>">
                                            <i class="fa fa-play"></i> <?= Yii::t('SessionsModule.views', 'Start') ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($model->allow_recording): ?>
                                        <a href="<?= yii\helpers\Url::to($urlBase('/sessions/session/recordings', ['id' => $model->id])) ?>"
                                           class="btn btn-outline-secondary btn-sm"
                                           title="<?= Yii::t('SessionsModule.views', 'Recordings') ?>">
                                            <i class="fa fa-film"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($model->canAdminister()): ?>
                                        <?= Button::defaultType()
                                            ->link($urlBase('/sessions/session/edit', ['id' => $model->id]))
                                            ->icon('cog')
                                            ->sm()
                                            ->title(Yii::t('SessionsModule.views', 'Settings')) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$copiedText = Yii::t('SessionsModule.views', 'Copied!');
$js = <<<JS
$(document).on('click', '.copy-url-btn', function() {
    var btn = $(this);
    var url = btn.data('url');
    var originalHtml = btn.html();

    navigator.clipboard.writeText(url).then(function() {
        btn.html('<i class="fa fa-check"></i>');
        setTimeout(function() { btn.html(originalHtml); }, 1500);
    }).catch(function() {
        var temp = $('<input>');
        $('body').append(temp);
        temp.val(url).select();
        document.execCommand('copy');
        temp.remove();
        btn.html('<i class="fa fa-check"></i>');
        setTimeout(function() { btn.html(originalHtml); }, 1500);
    });
});
JS;
$this->registerJs($js);
?>
