<?php

namespace humhub\modules\sessions\controllers;

use humhub\modules\sessions\models\forms\ContainerSettingsForm;
use humhub\modules\sessions\services\BackendRegistry;
use Yii;

/**
 * Controller for container-specific settings (Space/Profile).
 */
class ContainerConfigController extends BaseContentController
{
    /**
     * @var bool Require container for this controller
     */
    public $requireContainer = true;

    /**
     * Container settings page
     * @return string
     */
    public function actionIndex()
    {
        $form = new ContainerSettingsForm([
            'contentContainer' => $this->contentContainer
        ]);

        if ($form->load(Yii::$app->request->post()) && $form->save()) {
            $this->view->success(Yii::t('SessionsModule.config', 'Settings saved.'));
            return $this->redirect(['index']);
        }

        return $this->render('@sessions/views/container-config/index', [
            'model' => $form,
            'backends' => BackendRegistry::getConfigured(),
        ]);
    }
}
