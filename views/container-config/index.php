<?php

use humhub\modules\ui\form\widgets\ActiveForm;
use humhub\widgets\Button;
use yii\helpers\Html;

/**
 * @var \humhub\modules\ui\view\components\View $this
 * @var \humhub\modules\sessions\models\forms\ContainerSettingsForm $model
 * @var array $backends
 */

$this->pageTitle = Yii::t('SessionsModule.config', 'Sessions Settings');
$availableBackends = $model->getAvailableBackends();
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <h1><?= Yii::t('SessionsModule.config', 'Sessions Settings') ?></h1>
    </div>
    <div class="panel-body">
        <?php $form = ActiveForm::begin(['id' => 'container-settings-form']); ?>

        <?= $form->field($model, 'addNavItem')->checkbox() ?>

        <?= $form->field($model, 'navItemLabel')->textInput(['maxlength' => 50]) ?>

        <hr>
        <h5><?= Yii::t('SessionsModule.config', 'Backend Settings') ?></h5>

        <?= $form->field($model, 'inheritBackends')->checkbox([
            'id' => 'inherit-backends-checkbox'
        ])->hint($model->attributeHints()['inheritBackends']) ?>

        <div id="custom-backends-section" style="<?= $model->inheritBackends ? 'display:none;' : '' ?> margin-left: 20px; margin-bottom: 20px;">
            <p class="text-muted"><?= $model->attributeHints()['allowedBackends'] ?></p>

            <?php foreach ($availableBackends as $backendId => $info): ?>
                <div class="checkbox">
                    <label>
                        <input type="checkbox"
                               name="ContainerSettingsForm[allowedBackends][]"
                               value="<?= Html::encode($backendId) ?>"
                               <?= in_array($backendId, $model->allowedBackends) ? 'checked' : '' ?>
                               <?= !$info['globallyAllowed'] || !$info['configured'] ? 'disabled' : '' ?>>
                        <i class="fa <?= $info['icon'] ?>"></i>
                        <?= Html::encode($info['name']) ?>
                        <?php if (!$info['configured']): ?>
                            <span class="text-muted">(<?= Yii::t('SessionsModule.config', 'not configured') ?>)</span>
                        <?php elseif (!$info['globallyAllowed']): ?>
                            <span class="text-muted">(<?= Yii::t('SessionsModule.config', 'disabled globally') ?>)</span>
                        <?php endif; ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <?php
        // Build dropdown options from allowed backends
        $backendOptions = ['' => Yii::t('SessionsModule.config', '-- No default --')];
        foreach ($availableBackends as $backendId => $info) {
            if ($info['configured'] && $info['globallyAllowed']) {
                $backendOptions[$backendId] = $info['name'];
            }
        }
        ?>
        <?= $form->field($model, 'defaultBackend')->dropDownList($backendOptions)
            ->hint(Yii::t('SessionsModule.config', 'Default backend for new sessions in this space/profile.')) ?>

        <hr>
        <?= Button::save()->submit() ?>

        <?php ActiveForm::end(); ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var inheritCheckbox = document.getElementById('inherit-backends-checkbox');
    var customSection = document.getElementById('custom-backends-section');

    if (inheritCheckbox && customSection) {
        inheritCheckbox.addEventListener('change', function() {
            customSection.style.display = this.checked ? 'none' : 'block';
        });
    }
});
</script>
