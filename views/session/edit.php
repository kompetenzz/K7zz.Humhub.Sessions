<?php

use humhub\modules\sessions\assets\SessionAssets;
use humhub\modules\ui\form\widgets\ActiveForm;
use humhub\modules\user\widgets\UserPickerField;
use humhub\modules\topic\widgets\TopicPicker;
use humhub\widgets\Button;
use yii\helpers\Html;

/**
 * @var \humhub\modules\ui\view\components\View $this
 * @var \humhub\modules\sessions\models\forms\SessionForm $model
 */

SessionAssets::register($this);
$isNew = $model->id === null;
$backendOptions = $model->getBackendOptions();
$allBackendFields = $model->getAllBackendConfigFields();
$this->setPageTitle($isNew
    ? Yii::t('SessionsModule.views', 'Create Session')
    : Yii::t('SessionsModule.views', 'Edit Session'));
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <h1><?= $this->pageTitle ?></h1>
    </div>
    <div class="panel-body">
        <?php $form = ActiveForm::begin([
            'enableClientValidation' => false,
            'options' => ['enctype' => 'multipart/form-data']
        ]); ?>

        <div class="row">
            <div class="col-md-8">
                <?= $form->field($model, 'title')->textInput(['maxlength' => 200]) ?>

                <?= $form->field($model, 'name')->textInput([
                    'maxlength' => 100,
                    'style' => 'text-transform: lowercase;',
                    'placeholder' => Yii::t('SessionsModule.form', 'URL-friendly name (e.g., team-meeting)'),
                    'data-slugify' => 'true',
                    'data-slugify-title-selector' => '#sessionform-title',
                    'data-slugify-autogenerate' => $isNew ? 'true' : 'false',
                ])->hint(Yii::t('SessionsModule.form', 'Used in URLs. Only lowercase letters, numbers and hyphens.')) ?>

                <?= $form->field($model, 'description')->textarea(['rows' => 4]) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($model, 'backend_type')->dropDownList(
                    $backendOptions,
                    ['prompt' => Yii::t('SessionsModule.form', 'Select backend...')]
                )->hint(Yii::t('SessionsModule.form', 'The video conferencing system to use for this session.')) ?>

                <?php if (empty($backendOptions)): ?>
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        <?= Yii::t('SessionsModule.form', 'No video backends are available. Please contact an administrator.') ?>
                    </div>
                <?php endif; ?>

                <?= $form->field($model, 'enabled')->checkbox() ?>

                <?= $form->field($model, 'hidden')->checkbox([
                    'label' => Yii::t('SessionsModule.form', 'Hide stream entry'),
                ])->hint(Yii::t('SessionsModule.form', 'Hidden sessions do not appear in the stream.')) ?>

                <?php $imageFile = $model->getImageFile(); ?>
                <?php if ($imageFile !== null): ?>
                    <label><?= Yii::t('SessionsModule.form', 'Current session image') ?></label>
                    <div style="margin-bottom: 10px;">
                        <img src="<?= $imageFile->getUrl() ?>"
                             class="img-responsive img-thumbnail"
                             alt="<?= Yii::t('SessionsModule.form', 'Session image') ?>"
                             style="max-height: 150px; max-width: 100%;">
                    </div>
                <?php endif; ?>
                <?= $form->field($model, 'imageUpload')
                    ->fileInput()
                    ->label($imageFile
                        ? Yii::t('SessionsModule.form', 'Change session image')
                        : Yii::t('SessionsModule.form', 'Upload session image'))
                    ->hint(Yii::t('SessionsModule.form', 'Optional image displayed in the session list. Recommended: 800x600px.')) ?>
            </div>
        </div>

        <hr>
        <h4><?= Yii::t('SessionsModule.views', 'Meeting Options') ?></h4>

        <div class="row">
            <div class="col-md-6">
                <?= $form->field($model, 'publicJoin')->checkbox()
                    ->hint(Yii::t('SessionsModule.form', 'Creates a public join link which can be used by anyone without a HumHub account.')) ?>

                <?= $form->field($model, 'joinByPermissions')->checkbox([
                    'id' => 'join-by-permissions-toggle',
                ])->hint(Yii::t('SessionsModule.form', 'Allow everyone with HumHub access to join. Uncheck to select specific participants.')) ?>

                <div id="attendee-picker-box" <?= $model->joinByPermissions ? 'style="display:none"' : '' ?>>
                    <?= $form->field($model, 'attendeeRefs')->widget(UserPickerField::class)
                        ->label(Yii::t('SessionsModule.form', 'Select specific attendees for this session.')) ?>
                </div>

                <?= $form->field($model, 'joinCanStart')->checkbox()
                    ->hint(Yii::t('SessionsModule.form', 'Allow participants to start the meeting.')) ?>

                <?= $form->field($model, 'joinCanModerate')->checkbox()
                    ->hint(Yii::t('SessionsModule.form', 'Give all participants moderator rights.')) ?>

                <?= $form->field($model, 'moderateByPermissions')->checkbox([
                    'id' => 'moderate-by-permissions-toggle',
                ])->hint(Yii::t('SessionsModule.form', 'Allow everyone with manage access to moderate. Uncheck to select specific moderators.')) ?>

                <div id="moderator-picker-box" <?= $model->moderateByPermissions ? 'style="display:none"' : '' ?>>
                    <?= $form->field($model, 'moderatorRefs')->widget(UserPickerField::class)
                        ->label(Yii::t('SessionsModule.form', 'Select specific moderators for this session.')) ?>
                </div>
            </div>
            <div class="col-md-6">
                <?= $form->field($model, 'visibility')->checkbox([
                    'label' => Yii::t('SessionsModule.form', 'Public'),
                ])->hint(Yii::t('SessionsModule.form', 'Also visible to people who are not logged in.')) ?>

                <?= $form->field($model, 'hasWaitingRoom')->checkbox()
                    ->hint(Yii::t('SessionsModule.form', 'Participants wait until a moderator accepts them.')) ?>

                <?= $form->field($model, 'allowRecording')->checkbox()
                    ->hint(Yii::t('SessionsModule.form', 'Allow moderators to record the session.')) ?>

                <?= $form->field($model, 'muteOnEntry')->checkbox()
                    ->hint(Yii::t('SessionsModule.form', 'Mute participants when they join.')) ?>
            </div>
        </div>

        <?php /* Backend-specific settings */ ?>
        <?php if (!empty($allBackendFields)): ?>
            <?php foreach ($allBackendFields as $backendId => $fields): ?>
                <?php if (!empty($fields)): ?>
                    <div class="backend-config-fields" data-backend="<?= Html::encode($backendId) ?>"
                        style="<?= $model->backend_type !== $backendId ? 'display:none;' : '' ?>">
                        <hr>
                        <h4><?= Html::encode($backendOptions[$backendId] ?? $backendId) ?> <?= Yii::t('SessionsModule.views', 'Settings') ?></h4>
                        <div class="row">
                            <?php foreach ($fields as $fieldName => $fieldConfig): ?>
                                <div class="<?= ($fieldConfig['type'] ?? 'text') === 'radio' ? 'col-md-12' : 'col-md-6' ?>">
                                    <?php
                                    $inputName = "SessionForm[backendConfig][{$fieldName}]";
                                    $inputId = "sessionform-backendconfig-{$fieldName}";
                                    $value = $model->backendConfig[$fieldName] ?? $fieldConfig['default'] ?? null;
                                    $label = $fieldConfig['label'] ?? $fieldName;
                                    $hint = $fieldConfig['hint'] ?? '';
                                    ?>

                                    <?php if ($fieldConfig['type'] === 'checkbox'): ?>
                                        <div class="form-group">
                                            <div class="checkbox">
                                                <label>
                                                    <?= Html::hiddenInput($inputName, '0') ?>
                                                    <?= Html::checkbox($inputName, (bool) $value, ['id' => $inputId, 'value' => '1']) ?>
                                                    <?= Html::encode($label) ?>
                                                </label>
                                            </div>
                                            <?php if ($hint): ?>
                                                <div class="help-block"><?= Html::encode($hint) ?></div>
                                            <?php endif; ?>
                                        </div>

                                    <?php elseif ($fieldConfig['type'] === 'radio'): ?>
                                        <div class="form-group">
                                            <?= Html::label($label, null, ['class' => 'control-label']) ?>
                                            <?php foreach ($fieldConfig['options'] ?? [] as $optionValue => $optionLabel): ?>
                                                <?php $desc = $fieldConfig['descriptions'][$optionValue] ?? ''; ?>
                                                <div class="radio">
                                                    <label>
                                                        <?= Html::radio($inputName, $value === $optionValue, ['value' => $optionValue]) ?>
                                                        <strong><?= Html::encode($optionLabel) ?></strong>
                                                        <?php if ($desc): ?>
                                                            <br><small class="text-muted"><?= Html::encode($desc) ?></small>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if ($hint): ?>
                                                <div class="help-block"><?= Html::encode($hint) ?></div>
                                            <?php endif; ?>
                                        </div>

                                    <?php elseif ($fieldConfig['type'] === 'select'): ?>
                                        <div class="form-group">
                                            <?= Html::label($label, $inputId, ['class' => 'control-label']) ?>
                                            <?= Html::dropDownList($inputName, $value, $fieldConfig['options'] ?? [], [
                                                'id' => $inputId,
                                                'class' => 'form-control'
                                            ]) ?>
                                            <?php if ($hint): ?>
                                                <div class="help-block"><?= Html::encode($hint) ?></div>
                                            <?php endif; ?>
                                        </div>

                                    <?php elseif ($fieldConfig['type'] === 'number'): ?>
                                        <div class="form-group">
                                            <?= Html::label($label, $inputId, ['class' => 'control-label']) ?>
                                            <?= Html::input('number', $inputName, $value, [
                                                'id' => $inputId,
                                                'class' => 'form-control',
                                                'min' => $fieldConfig['min'] ?? null,
                                                'max' => $fieldConfig['max'] ?? null,
                                            ]) ?>
                                            <?php if ($hint): ?>
                                                <div class="help-block"><?= Html::encode($hint) ?></div>
                                            <?php endif; ?>
                                        </div>

                                    <?php elseif ($fieldConfig['type'] === 'textarea'): ?>
                                        <div class="form-group">
                                            <?= Html::label($label, $inputId, ['class' => 'control-label']) ?>
                                            <?= Html::textarea($inputName, $value, [
                                                'id' => $inputId,
                                                'class' => 'form-control',
                                                'rows' => $fieldConfig['rows'] ?? 3,
                                            ]) ?>
                                            <?php if ($hint): ?>
                                                <div class="help-block"><?= Html::encode($hint) ?></div>
                                            <?php endif; ?>
                                        </div>

                                    <?php else: /* text input */ ?>
                                        <div class="form-group">
                                            <?= Html::label($label, $inputId, ['class' => 'control-label']) ?>
                                            <?= Html::textInput($inputName, $value, [
                                                'id' => $inputId,
                                                'class' => 'form-control',
                                                'maxlength' => $fieldConfig['maxlength'] ?? 255,
                                            ]) ?>
                                            <?php if ($hint): ?>
                                                <div class="help-block"><?= Html::encode($hint) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php /* Backend-specific File Uploads */ ?>
        <div class="row">
            <div class="col-md-4 backend-file-field" data-backends="bbb">
                <?php /* Presentation (BBB only) */ ?>
                <div class="form-group">
                    <?php $presentationFile = $model->getPresentationFile(); ?>
                    <?php $presentationPreview = $model->getPresentationPreviewImage(); ?>
                    <?php if ($presentationFile !== null): ?>
                        <label><?= Yii::t('SessionsModule.form', 'Current presentation') ?></label>
                        <div style="margin-bottom: 10px;">
                            <?php if ($presentationPreview !== null): ?>
                                <img src="<?= $presentationPreview->getUrl() ?>"
                                     class="img-responsive img-thumbnail"
                                     alt="<?= Yii::t('SessionsModule.form', 'PDF preview') ?>"
                                     style="max-height: 150px; max-width: 100%;">
                            <?php endif; ?>
                            <div class="small text-muted">
                                <?= Html::encode($presentationFile->file_name) ?>
                                (<?= round($presentationFile->size / 1024 / 1024, 2) ?> MB)
                            </div>
                        </div>
                    <?php endif; ?>
                    <?= $form->field($model, 'presentationUpload')
                        ->fileInput()
                        ->label($presentationFile
                            ? Yii::t('SessionsModule.form', 'Change presentation')
                            : Yii::t('SessionsModule.form', 'Upload presentation'))
                        ->hint(Yii::t('SessionsModule.form', 'Optional PDF presentation (landscape recommended). Only for BigBlueButton.')) ?>
                </div>
            </div>

            <div class="col-md-4 backend-file-field" data-backends="bbb">
                <?php /* Camera Background (BBB only) */ ?>
                <div class="form-group">
                    <?php $cameraBgFile = $model->getCameraBgImageFile(); ?>
                    <?php if ($cameraBgFile !== null): ?>
                        <label><?= Yii::t('SessionsModule.form', 'Current camera background') ?></label>
                        <div style="margin-bottom: 10px;">
                            <img src="<?= $cameraBgFile->getUrl() ?>"
                                 class="img-responsive img-thumbnail"
                                 alt="<?= Yii::t('SessionsModule.form', 'Camera background') ?>"
                                 style="max-height: 150px; max-width: 100%;">
                        </div>
                    <?php endif; ?>
                    <?= $form->field($model, 'cameraBgImageUpload')
                        ->fileInput()
                        ->label($cameraBgFile
                            ? Yii::t('SessionsModule.form', 'Change camera background')
                            : Yii::t('SessionsModule.form', 'Upload camera background'))
                        ->hint(Yii::t('SessionsModule.form', 'Optional background for webcams. Only for BigBlueButton.')) ?>
                </div>
            </div>
        </div>

        <?php if (class_exists('humhub\modules\topic\widgets\TopicPicker') && $model->contentContainer !== null): ?>
            <hr>
            <h4><?= Yii::t('SessionsModule.views', 'Topics') ?></h4>
            <?= $form->field($model, 'topics')->widget(TopicPicker::class, [
                'contentContainer' => $model->contentContainer
            ])->label(false) ?>
        <?php endif; ?>

        <hr>
        <div class="form-group">
            <?= Button::save()->submit() ?>
            <?= Button::defaultType(Yii::t('SessionsModule.views', 'Cancel'))
                ->link($this->context->contentContainer
                    ? $this->context->contentContainer->createUrl('/sessions/list')
                    : ['/sessions/list']) ?>

            <?php if (!$isNew): ?>
                <?= Button::danger(Yii::t('SessionsModule.views', 'Delete'))
                    ->link($this->context->contentContainer
                        ? $this->context->contentContainer->createUrl('/sessions/session/delete', ['id' => $model->id])
                        : ['/sessions/session/delete', 'id' => $model->id])
                    ->confirm(Yii::t('SessionsModule.views', 'Are you sure you want to delete this session?'))
                    ->right() ?>
            <?php endif; ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>
</div>

<?php
// JavaScript for dynamic backend field switching, file field visibility, and permission toggles
$js = <<<JS
$(document).ready(function() {
    var backendSelect = $('#sessionform-backend_type');

    function showBackendFields() {
        var selectedBackend = backendSelect.val();

        // Toggle backend-specific config fields
        $('.backend-config-fields').hide();
        $('.backend-config-fields[data-backend="' + selectedBackend + '"]').show();

        // Toggle backend-specific file fields
        $('.backend-file-field').each(function() {
            var supportedBackends = ($(this).data('backends') || '').split(',');
            if (supportedBackends.indexOf(selectedBackend) !== -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    // Backend switching
    showBackendFields();
    backendSelect.on('change', function() {
        showBackendFields();
    });

    // Permission toggles
    function togglePermissionPickers() {
        $('#attendee-picker-box').toggle(!$('#join-by-permissions-toggle').is(':checked'));
        $('#moderator-picker-box').toggle(!$('#moderate-by-permissions-toggle').is(':checked'));
    }

    $('#join-by-permissions-toggle, #moderate-by-permissions-toggle').on('change', function() {
        togglePermissionPickers();
    });
    togglePermissionPickers();
});
JS;
$this->registerJs($js);
?>
