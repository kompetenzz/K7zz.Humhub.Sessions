<?php

namespace humhub\modules\sessions\controllers;

use humhub\components\Controller;
use humhub\modules\sessions\services\SessionService;
use humhub\modules\sessions\Module;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Controller for public/anonymous session access.
 */
class PublicController extends Controller
{
    /**
     * @var SessionService
     */
    private $svc;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->svc = Yii::$container->get(SessionService::class);
    }

    /**
     * @inheritdoc
     */
    public function getAccessRules()
    {
        return [
            ['login' => ['download']],
            ['guestAccess' => ['join']],
        ];
    }

    /**
     * Public join via token
     * @param string|null $token
     * @return string|\yii\web\Response
     */
    public function actionJoin(?string $token = null)
    {
        if (empty($token)) {
            throw new NotFoundHttpException();
        }

        $session = $this->svc->getByPublicToken($token);
        if (!$session) {
            throw new NotFoundHttpException(Yii::t('SessionsModule.base', 'Invalid or expired join link.'));
        }

        if (!$session->public_join) {
            throw new NotFoundHttpException(Yii::t('SessionsModule.base', 'Public join is not enabled for this session.'));
        }

        // Check if meeting is running
        if (!$this->svc->isRunning($session)) {
            return $this->render('@sessions/views/public/waiting', [
                'session' => $session,
                'token' => $token,
            ]);
        }

        // If user is logged in, redirect to regular join
        if (!Yii::$app->user->isGuest) {
            $container = $session->content->container ?? null;
            $url = $container
                ? $container->createUrl('/sessions/session/join', ['id' => $session->id])
                : ['/sessions/session/join', 'id' => $session->id];
            return $this->redirect($url);
        }

        // Show guest join form
        $displayName = Yii::$app->request->post('displayName');
        if ($displayName) {
            $joinUrl = $this->svc->anonymousJoinUrl($session, $displayName);
            if ($joinUrl) {
                return $this->render('@sessions/views/public/join', [
                    'session' => $session,
                    'joinUrl' => $joinUrl,
                ]);
            }
        }

        return $this->render('@sessions/views/public/guest-form', [
            'session' => $session,
            'token' => $token,
        ]);
    }

    /**
     * Download file (presentation, camera background)
     * @param int|null $id Session ID
     * @param string|null $type File type (presentation, camera-bg-image)
     * @param bool $inline
     * @param bool $embeddable
     * @return \yii\web\Response
     */
    public function actionDownload(?int $id = null, ?string $type = null, bool $inline = false, bool $embeddable = false)
    {
        if ($id === null || $type === null) {
            throw new NotFoundHttpException();
        }

        $session = $this->svc->get($id, null, true);
        if (!$session) {
            throw new NotFoundHttpException();
        }

        $file = null;
        switch ($type) {
            case 'presentation':
                $file = $session->getPresentationFile();
                break;
            case 'camera-bg-image':
                $file = $session->getCameraBgImageFile();
                break;
            case 'image':
                $file = $session->getImageFile();
                break;
        }

        if (!$file) {
            throw new NotFoundHttpException();
        }

        $options = [];
        if ($inline) {
            $options['inline'] = true;
        }

        return Yii::$app->response->sendFile(
            $file->store->get(),
            $file->file_name,
            $options
        );
    }
}
