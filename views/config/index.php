<?php

use humhub\modules\ui\form\widgets\ActiveForm;
use humhub\widgets\Button;
use yii\helpers\Html;

/**
 * @var \humhub\modules\ui\view\components\View $this
 * @var array $backends
 * @var array $forms
 * @var string|null $activeBackend
 * @var \humhub\modules\sessions\models\forms\GlobalSettingsForm $globalForm
 */

$this->pageTitle = Yii::t('SessionsModule.config', 'Sessions Configuration');
?>

<div class="card">
    <div class="card-header">
        <h1><?= Yii::t('SessionsModule.config', 'Sessions Module Settings') ?></h1>
    </div>
    <div class="card-body">

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs" style="margin-bottom: 20px;">
            <li class="nav-item">
                <a class="nav-link <?= !$activeBackend ? 'active' : '' ?>" href="<?= \yii\helpers\Url::to(['index']) ?>">
                    <i class="fa fa-cog"></i> <?= Yii::t('SessionsModule.config', 'General') ?>
                </a>
            </li>
            <?php foreach ($backends as $backend): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $activeBackend === $backend->getId() ? 'active' : '' ?>" href="<?= \yii\helpers\Url::to(['index', 'backend' => $backend->getId()]) ?>">
                        <i class="fa <?= $backend->getIcon() ?>"></i>
                        <?= Html::encode($backend->getName()) ?>
                        <?php if ($backend->isConfigured()): ?>
                            <span class="badge bg-success" style="font-size: 9px;">
                                <i class="fa fa-check"></i>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if (!$activeBackend): ?>
            <!-- Global Settings -->
            <h4><?= Yii::t('SessionsModule.config', 'General Settings') ?></h4>

            <?php $form = ActiveForm::begin(); ?>
                <?= $form->field($globalForm, 'addNavItem')->checkbox() ?>
                <?= $form->field($globalForm, 'navItemLabel')->textInput(['maxlength' => 50]) ?>

                <hr>
                <h5><?= Yii::t('SessionsModule.config', 'Allowed Backends') ?></h5>
                <p class="text-muted"><?= $globalForm->attributeHints()['allowedBackends'] ?></p>

                <div class="backend-checkboxes" style="margin-bottom: 20px;">
                    <?php foreach ($globalForm->getAvailableBackends() as $backendId => $info): ?>
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input"
                                       name="GlobalSettingsForm[allowedBackends][]"
                                       value="<?= Html::encode($backendId) ?>"
                                       <?= in_array($backendId, $globalForm->allowedBackends) ? 'checked' : '' ?>
                                       <?= !$info['configured'] ? 'disabled' : '' ?>>
                                <i class="fa <?= $info['icon'] ?>"></i>
                                <?= Html::encode($info['name']) ?>
                                <?php if (!$info['configured']): ?>
                                    <span class="text-muted">(<?= Yii::t('SessionsModule.config', 'not configured') ?>)</span>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <hr>
                <?= Button::save()->submit() ?>
            <?php ActiveForm::end(); ?>

            <hr>
            <h4><?= Yii::t('SessionsModule.config', 'Backend Status') ?></h4>
            <table class="table">
                <thead>
                    <tr>
                        <th><?= Yii::t('SessionsModule.config', 'Backend') ?></th>
                        <th><?= Yii::t('SessionsModule.config', 'Status') ?></th>
                        <th><?= Yii::t('SessionsModule.config', 'Features') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backends as $backend): ?>
                        <tr>
                            <td>
                                <i class="fa <?= $backend->getIcon() ?>"></i>
                                <strong><?= Html::encode($backend->getName()) ?></strong>
                            </td>
                            <td>
                                <?php if ($backend->isConfigured()): ?>
                                    <span class="badge bg-success">
                                        <i class="fa fa-check"></i> <?= Yii::t('SessionsModule.config', 'Configured') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning">
                                        <i class="fa fa-exclamation-triangle"></i> <?= Yii::t('SessionsModule.config', 'Not configured') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($backend->supportsRecordings()): ?>
                                    <span class="badge" title="<?= Yii::t('SessionsModule.config', 'Recordings') ?>"><i class="fa fa-circle"></i></span>
                                <?php endif; ?>
                                <?php if ($backend->supportsWaitingRoom()): ?>
                                    <span class="badge" title="<?= Yii::t('SessionsModule.config', 'Waiting Room') ?>"><i class="fa fa-clock-o"></i></span>
                                <?php endif; ?>
                                <?php if ($backend->supportsPresentationUpload()): ?>
                                    <span class="badge" title="<?= Yii::t('SessionsModule.config', 'Presentations') ?>"><i class="fa fa-file-pdf-o"></i></span>
                                <?php endif; ?>
                                <?php if ($backend->supportsPublicJoin()): ?>
                                    <span class="badge" title="<?= Yii::t('SessionsModule.config', 'Public Join') ?>"><i class="fa fa-globe"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php elseif (isset($forms[$activeBackend])): ?>
            <!-- Backend-specific Settings -->
            <?php $backendObj = $backends[$activeBackend] ?? null; ?>
            <?php if ($backendObj): ?>
                <h4>
                    <i class="fa <?= $backendObj->getIcon() ?>"></i>
                    <?= Html::encode($backendObj->getName()) ?> <?= Yii::t('SessionsModule.config', 'Settings') ?>
                </h4>
                <p class="text-muted"><?= Html::encode($backendObj->getDescription()) ?></p>
                <hr>
            <?php endif; ?>

            <?php $form = ActiveForm::begin(['action' => ['index', 'backend' => $activeBackend]]); ?>
                <?php $backendForm = $forms[$activeBackend]; ?>

                <?php foreach ($backendForm->attributes() as $attr): ?>
                    <?php if (!in_array($attr, ['id'])): ?>
                        <?php
                        $value = $backendForm->$attr;
                        $rules = $backendForm->rules();
                        $isBoolean = is_bool($value);

                        // Check if field is boolean based on rules
                        foreach ($rules as $rule) {
                            if ((is_array($rule[0]) && in_array($attr, $rule[0])) || $rule[0] === $attr) {
                                if (isset($rule[1]) && $rule[1] === 'boolean') {
                                    $isBoolean = true;
                                    break;
                                }
                            }
                        }
                        ?>
                        <?php if ($isBoolean): ?>
                            <?= $form->field($backendForm, $attr)->checkbox() ?>
                        <?php elseif (strpos(strtolower($attr), 'secret') !== false || strpos(strtolower($attr), 'password') !== false || strpos(strtolower($attr), 'token') !== false): ?>
                            <?= $form->field($backendForm, $attr)->passwordInput(['autocomplete' => 'new-password']) ?>
                        <?php else: ?>
                            <?= $form->field($backendForm, $attr)->textInput() ?>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>

                <hr>
                <?= Button::save()->submit() ?>
            <?php ActiveForm::end(); ?>
        <?php endif; ?>

    </div>
</div>
