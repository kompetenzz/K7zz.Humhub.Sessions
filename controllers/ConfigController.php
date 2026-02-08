<?php

namespace humhub\modules\sessions\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\sessions\services\BackendRegistry;
use humhub\modules\sessions\models\forms\GlobalSettingsForm;
use Yii;

/**
 * Admin controller for backend configuration.
 */
class ConfigController extends Controller
{
    /**
     * Main configuration page with tabs for each backend
     * @param string|null $backend Backend ID to show (defaults to first configured)
     * @return string
     */
    public function actionIndex(?string $backend = null)
    {
        $backends = BackendRegistry::getAll();
        $forms = [];

        // Load all backend settings forms
        foreach ($backends as $b) {
            $formClass = $b->getSettingsFormClass();
            if ($formClass && class_exists($formClass)) {
                $forms[$b->getId()] = new $formClass();
            }
        }

        // Handle form submission
        if (Yii::$app->request->isPost && $backend) {
            if (isset($forms[$backend])) {
                $form = $forms[$backend];
                if ($form->load(Yii::$app->request->post()) && $form->save()) {
                    $this->view->success(Yii::t('SessionsModule.config', 'Settings saved.'));
                    return $this->redirect(['index', 'backend' => $backend]);
                }
            }
        }

        // Global settings form
        $globalForm = new GlobalSettingsForm();
        if (Yii::$app->request->isPost && !$backend) {
            if ($globalForm->load(Yii::$app->request->post()) && $globalForm->save()) {
                $this->view->success(Yii::t('SessionsModule.config', 'Settings saved.'));
                return $this->redirect(['index']);
            }
        }

        return $this->render('@sessions/views/config/index', [
            'backends' => $backends,
            'forms' => $forms,
            'activeBackend' => $backend,
            'globalForm' => $globalForm,
        ]);
    }
}
