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

<div class="panel panel-default">
    <div class="panel-heading">
        <h1><?= Yii::t('SessionsModule.views', 'Video Sessions') ?></h1>
    </div>
    <div class="panel-body">
        <?php if (Yii::$app->user->can('humhub\modules\sessions\permissions\StartSession')): ?>
            <div class="pull-right">
                <?= Button::primary(Yii::t('SessionsModule.views', 'Create Session'))
                    ->link($this->context->contentContainer
                        ? $this->context->contentContainer->createUrl('/sessions/session/create')
                        : ['/sessions/session/create'])
                    ->icon('plus') ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($isGlobalView)): ?>
            <div class="btn-group" role="group" style="margin-bottom: 15px;">
                <?= Html::a(
                    '<i class="fa fa-globe"></i> ' . Yii::t('SessionsModule.views', 'Global'),
                    ['/sessions/list', 'scope' => 'global'],
                    ['class' => 'btn btn-sm ' . ($scope === 'global' ? 'btn-primary' : 'btn-default')]
                ) ?>
                <?= Html::a(
                    '<i class="fa fa-users"></i> ' . Yii::t('SessionsModule.views', 'Spaces'),
                    ['/sessions/list', 'scope' => 'spaces'],
                    ['class' => 'btn btn-sm ' . ($scope === 'spaces' ? 'btn-primary' : 'btn-default')]
                ) ?>
                <?= Html::a(
                    '<i class="fa fa-user"></i> ' . Yii::t('SessionsModule.views', 'Users'),
                    ['/sessions/list', 'scope' => 'users'],
                    ['class' => 'btn btn-sm ' . ($scope === 'users' ? 'btn-primary' : 'btn-default')]
                ) ?>
                <?= Html::a(
                    '<i class="fa fa-list"></i> ' . Yii::t('SessionsModule.views', 'All'),
                    ['/sessions/list', 'scope' => 'all'],
                    ['class' => 'btn btn-sm ' . ($scope === 'all' ? 'btn-primary' : 'btn-default')]
                ) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($rows)): ?>
            <div class="text-center text-muted" style="padding: 40px 20px;">
                <i class="fa fa-video-camera" style="font-size: 48px; opacity: 0.3;"></i>
                <p style="margin-top: 15px;"><?= Yii::t('SessionsModule.views', 'No sessions available.') ?></p>
            </div>
        <?php else: ?>
            <div class="table-responsive" style="margin-top: 15px;">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th></th>
                            <th><?= Yii::t('SessionsModule.views', 'Session') ?></th>
                            <?php if (!empty($isGlobalView) && $scope !== 'global'): ?>
                                <th><?= Yii::t('SessionsModule.views', 'Location') ?></th>
                            <?php endif; ?>
                            <th><?= Yii::t('SessionsModule.views', 'Backend') ?></th>
                            <th><?= Yii::t('SessionsModule.views', 'Created') ?></th>
                            <th style="text-align: center;" title="<?= Yii::t('SessionsModule.views', 'Visibility') ?>"><i class="fa fa-eye"></i></th>
                            <th style="text-align: center;" title="<?= Yii::t('SessionsModule.views', 'Waiting Room') ?>"><i class="fa fa-clock-o"></i></th>
                            <th style="text-align: center;" title="<?= Yii::t('SessionsModule.views', 'Recording') ?>"><i class="fa fa-circle"></i></th>
                            <th><?= Yii::t('SessionsModule.views', 'Status') ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $model = $row['model'];
                            $running = $row['running'];
                            $backend = $row['backend'] ?? null;
                            $isHighlighted = $model->id === $highlightId;
                            $sessionContainer = $model->content->container ?? null;
                        ?>
                            <tr class="<?= $isHighlighted ? 'info' : '' ?>" data-session-id="<?= $model->id ?>">
                                <td style="padding: 0 !important; vertical-align: middle;">
                                    <?php if ($model->outputImage): ?>
                                        <img src="<?= $model->outputImage->getUrl() ?>"
                                             alt="" class="img-rounded"
                                             style="width: 36px; height: 36px; object-fit: cover; display: block; margin: -1px 0;">
                                    <?php else: ?>
                                        <span class="fa-stack" style="font-size: 18px;">
                                            <i class="fa fa-square fa-stack-2x text-muted"></i>
                                            <i class="fa fa-video-camera fa-stack-1x fa-inverse"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= Html::encode($model->title ?: $model->name) ?></strong>
                                    <?php if ($model->description): ?>
                                        <br><small class="text-muted"><?= Html::encode(mb_substr(strip_tags($model->description), 0, 80)) ?>...</small>
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
                                <td>
                                    <?php if ($backend): ?>
                                        <span title="<?= Html::encode($backend->getName()) ?>" style="display: inline-flex; align-items: center; gap: 6px;">
                                            <?= $backend->getLogo(18) ?>
                                            <?= Html::encode($backend->getName()) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted"><?= $model->backend_type ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($model->created_at): ?>
                                        <small><?= Yii::$app->formatter->asDatetime($model->created_at, 'short') ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php
                                    $isPublic = isset($model->content) && $model->content->visibility == \humhub\modules\content\models\Content::VISIBILITY_PUBLIC;
                                    ?>
                                    <i class="fa <?= $isPublic ? 'fa-globe' : 'fa-lock' ?>"
                                       title="<?= $isPublic
                                           ? Yii::t('SessionsModule.views', 'Public')
                                           : Yii::t('SessionsModule.views', 'Private') ?>"
                                       style="color: <?= $isPublic ? '#5cb85c' : '#999' ?>;"></i>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($model->has_waitingroom): ?>
                                        <i class="fa fa-check" title="<?= Yii::t('SessionsModule.views', 'Waiting room enabled') ?>" style="color: #5cb85c;"></i>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($model->allow_recording): ?>
                                        <i class="fa fa-circle" title="<?= Yii::t('SessionsModule.views', 'Recording enabled') ?>" style="color: #d9534f;"></i>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($running): ?>
                                        <span class="label label-success">
                                            <i class="fa fa-circle"></i> <?= Yii::t('SessionsModule.views', 'Running') ?>
                                        </span>
                                    <?php elseif (!$model->enabled): ?>
                                        <span class="label label-default"><?= Yii::t('SessionsModule.views', 'Disabled') ?></span>
                                    <?php else: ?>
                                        <span class="label label-default"><?= Yii::t('SessionsModule.views', 'Stopped') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <?php
                                    // Use session's container for URLs (important in global view)
                                    $actionContainer = $sessionContainer ?? $this->context->contentContainer;
                                    $urlBase = $actionContainer ? function($route, $params = []) use ($actionContainer) {
                                        return $actionContainer->createUrl($route, $params);
                                    } : function($route, $params = []) {
                                        return array_merge([$route], $params);
                                    };

                                    // Internal join URL (for members)
                                    $internalUrl = yii\helpers\Url::to($urlBase('/sessions/session/start', ['id' => $model->id]), true);

                                    // Public join URL (for guests, if enabled)
                                    $publicUrl = ($model->public_join && $model->public_token)
                                        ? yii\helpers\Url::to(['/sessions/public/join', 'token' => $model->public_token], true)
                                        : null;
                                    ?>

                                    <?php // Copy buttons for admins ?>
                                    <?php if ($model->canAdminister()): ?>
                                        <button type="button"
                                                class="btn btn-default btn-sm copy-url-btn"
                                                data-url="<?= Html::encode($internalUrl) ?>"
                                                title="<?= Yii::t('SessionsModule.views', 'Copy member link') ?>">
                                            <i class="fa fa-link"></i>
                                        </button>
                                        <?php if ($publicUrl): ?>
                                            <button type="button"
                                                    class="btn btn-info btn-sm copy-url-btn"
                                                    data-url="<?= Html::encode($publicUrl) ?>"
                                                    title="<?= Yii::t('SessionsModule.views', 'Copy public/guest link') ?>">
                                                <i class="fa fa-globe"></i>
                                            </button>
                                        <?php endif; ?>
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
$copyFailedText = Yii::t('SessionsModule.views', 'Copy failed');
$js = <<<JS
$(document).on('click', '.copy-url-btn', function() {
    var btn = $(this);
    var url = btn.data('url');
    var originalTitle = btn.attr('title');

    navigator.clipboard.writeText(url).then(function() {
        // Success feedback
        btn.find('i').removeClass('fa-link').addClass('fa-check');
        btn.attr('title', '{$copiedText}');
        btn.tooltip('show');

        setTimeout(function() {
            btn.find('i').removeClass('fa-check').addClass('fa-link');
            btn.attr('title', originalTitle);
            btn.tooltip('hide');
        }, 1500);
    }).catch(function() {
        // Fallback for older browsers
        var temp = $('<input>');
        $('body').append(temp);
        temp.val(url).select();
        document.execCommand('copy');
        temp.remove();

        btn.find('i').removeClass('fa-link').addClass('fa-check');
        setTimeout(function() {
            btn.find('i').removeClass('fa-check').addClass('fa-link');
        }, 1500);
    });
});
JS;
$this->registerJs($js);
?>
