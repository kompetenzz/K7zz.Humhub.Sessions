<?php

namespace humhub\modules\sessions\controllers;

use humhub\modules\sessions\models\forms\SessionForm;
use humhub\modules\sessions\models\SessionEventLog;
use Yii;
use yii\helpers\Url;
use yii\web\{ForbiddenHttpException, NotFoundHttpException};

/**
 * Controller for handling session CRUD and meeting operations.
 */
class SessionController extends BaseContentController
{
    /**
     * Redirects to edit action
     * @param int|null $id
     * @return \yii\web\Response
     */
    public function actionIndex(?int $id = null)
    {
        if ($id === null) {
            throw new NotFoundHttpException();
        }
        return $this->actionEdit($id);
    }

    /**
     * Creates a new session
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $form = SessionForm::create($this->contentContainer);

        if ($form->load(Yii::$app->request->post()) && $form->save()) {
            $this->view->success(Yii::t('SessionsModule.base', 'Session created.'));
            return $this->redirect([$this->getUrl('/sessions/list'), 'highlight' => $form->id]);
        }

        return $this->render('edit', [
            'model' => $form,
        ]);
    }

    /**
     * Edits an existing session
     * @param int|null $id
     * @return string|\yii\web\Response
     */
    public function actionEdit(?int $id = null)
    {
        if ($id === null) {
            throw new NotFoundHttpException();
        }

        $session = $this->svc->get($id, $this->contentContainer);
        if (!$session) {
            throw new NotFoundHttpException(Yii::t('SessionsModule.base', 'Session not found.'));
        }

        if (!$session->canAdminister()) {
            throw new ForbiddenHttpException();
        }

        $form = SessionForm::edit($session);

        if ($form->load(Yii::$app->request->post()) && $form->save()) {
            $this->view->success(Yii::t('SessionsModule.base', 'Session saved.'));
            return $this->redirect([$this->getUrl('/sessions/list'), 'highlight' => $form->id]);
        }

        return $this->render('edit', [
            'model' => $form,
        ]);
    }

    /**
     * Starts or joins a session
     * @param int|null $id
     * @param bool $embed
     * @return \yii\web\Response|string
     */
    public function actionStart(?int $id = null, bool $embed = false)
    {
        if ($id === null) {
            throw new NotFoundHttpException();
        }

        $session = $this->svc->get($id, $this->contentContainer);
        if (!$session) {
            throw new NotFoundHttpException(Yii::t('SessionsModule.base', 'Session not found.'));
        }

        // Check if meeting is running
        if (!$this->svc->isRunning($session)) {
            // Need to start meeting
            if (!$session->canStart()) {
                throw new ForbiddenHttpException(Yii::t('SessionsModule.base', 'You are not allowed to start this session.'));
            }

            $result = $this->svc->start($session);
            if (!$result) {
                $this->view->error(Yii::t('SessionsModule.base', 'Failed to start session.'));
                return $this->redirect($this->getUrl('/sessions/list'));
            }
            SessionEventLog::log($session->id, SessionEventLog::EVENT_STARTED, Yii::$app->user->id);
        }

        // Get join URL
        $isModerator = $session->isModerator();
        $joinUrl = $this->svc->joinUrl($session, Yii::$app->user->identity, $isModerator);

        if (!$joinUrl) {
            $this->view->error(Yii::t('SessionsModule.base', 'Failed to get join URL.'));
            return $this->redirect($this->getUrl('/sessions/list'));
        }

        SessionEventLog::log($session->id, SessionEventLog::EVENT_JOINED, Yii::$app->user->id);

        $backend = $this->svc->getBackend($session);
        if ($embed && $backend && $backend->supportsEmbed()) {
            return $this->render('join', [
                'session' => $session,
                'joinUrl' => $joinUrl,
            ]);
        }

        return $this->redirect($joinUrl);
    }

    /**
     * Joins an existing running session
     * @param int|null $id
     * @param bool $embed
     * @return \yii\web\Response|string
     */
    public function actionJoin(?int $id = null, bool $embed = false)
    {
        if ($id === null) {
            throw new NotFoundHttpException();
        }

        $session = $this->svc->get($id, $this->contentContainer);
        if (!$session) {
            throw new NotFoundHttpException(Yii::t('SessionsModule.base', 'Session not found.'));
        }

        if (!$session->canJoin()) {
            throw new ForbiddenHttpException(Yii::t('SessionsModule.base', 'You are not allowed to join this session.'));
        }

        if (!$this->svc->isRunning($session)) {
            $this->view->error(Yii::t('SessionsModule.base', 'This session is not running.'));
            return $this->redirect($this->getUrl('/sessions/list'));
        }

        $isModerator = $session->isModerator();
        $joinUrl = $this->svc->joinUrl($session, Yii::$app->user->identity, $isModerator);

        if (!$joinUrl) {
            $this->view->error(Yii::t('SessionsModule.base', 'Failed to get join URL.'));
            return $this->redirect($this->getUrl('/sessions/list'));
        }

        SessionEventLog::log($session->id, SessionEventLog::EVENT_JOINED, Yii::$app->user->id);

        $backend = $this->svc->getBackend($session);
        if ($embed && $backend && $backend->supportsEmbed()) {
            return $this->render('join', [
                'session' => $session,
                'joinUrl' => $joinUrl,
            ]);
        }

        return $this->redirect($joinUrl);
    }

    /**
     * Lobby page for members — shows session info with contextual Start/Join/Waiting UI.
     * Intended as the shareable member link (instead of direct start).
     * @param int|null $id
     * @return string
     */
    public function actionLobby(?int $id = null)
    {
        if ($id === null) {
            throw new NotFoundHttpException();
        }

        $session = $this->svc->get($id, $this->contentContainer);
        if (!$session) {
            throw new NotFoundHttpException(Yii::t('SessionsModule.base', 'Session not found.'));
        }

        if (!$session->canJoin()) {
            throw new ForbiddenHttpException(Yii::t('SessionsModule.base', 'You are not allowed to join this session.'));
        }

        $backend = $this->svc->getBackend($session);
        $alwaysJoinable = $backend && $backend->isAlwaysJoinable();

        $running = false;
        if (!$alwaysJoinable) {
            try {
                $running = $this->svc->isRunning($session);
            } catch (\Throwable $e) {
                // Backend not reachable
            }
        }

        $container = $session->content->container ?? null;
        $urlFunc = $container
            ? fn($route, $params = []) => $container->createUrl($route, $params)
            : fn($route, $params = []) => array_merge([$route], $params);

        return $this->render('lobby', [
            'session' => $session,
            'running' => $running,
            'alwaysJoinable' => $alwaysJoinable,
            'urlFunc' => $urlFunc,
        ]);
    }

    /**
     * Exit action (redirect after leaving meeting)
     * @param int|null $id Session ID for logging
     * @param int|null $highlight
     * @return \yii\web\Response
     */
    public function actionExit(?int $id = null, ?int $highlight = null)
    {
        if ($id !== null) {
            SessionEventLog::log($id, SessionEventLog::EVENT_LEFT, Yii::$app->user->id);
        }
        return $this->redirect([$this->getUrl('/sessions/list'), 'highlight' => $highlight]);
    }

    /**
     * Stops a running session
     * @param int|null $id
     * @return \yii\web\Response
     */
    public function actionStop(?int $id = null)
    {
        if ($id === null) {
            throw new NotFoundHttpException();
        }

        $session = $this->svc->get($id, $this->contentContainer);
        if (!$session) {
            throw new NotFoundHttpException(Yii::t('SessionsModule.base', 'Session not found.'));
        }

        if (!$session->canStart()) {
            throw new ForbiddenHttpException(Yii::t('SessionsModule.base', 'You are not allowed to stop this session.'));
        }

        if ($this->svc->end($session)) {
            SessionEventLog::log($session->id, SessionEventLog::EVENT_STOPPED, Yii::$app->user->id);
            $this->view->success(Yii::t('SessionsModule.base', 'Session stopped.'));
        } else {
            $this->view->error(Yii::t('SessionsModule.base', 'Failed to stop session.'));
        }

        return $this->redirect($this->getUrl('/sessions/list'));
    }

    /**
     * Deletes a session
     * @param int|null $id
     * @return \yii\web\Response
     */
    public function actionDelete(?int $id = null)
    {
        if ($id === null) {
            throw new NotFoundHttpException();
        }

        $session = $this->svc->get($id, $this->contentContainer);
        if (!$session) {
            throw new NotFoundHttpException(Yii::t('SessionsModule.base', 'Session not found.'));
        }

        if (!$session->canAdminister()) {
            throw new ForbiddenHttpException();
        }

        if ($this->svc->delete($id, $this->contentContainer)) {
            $this->view->success(Yii::t('SessionsModule.base', 'Session deleted.'));
        } else {
            $this->view->error(Yii::t('SessionsModule.base', 'Failed to delete session.'));
        }

        return $this->redirect($this->getUrl('/sessions/list'));
    }

    /**
     * Get recordings for a session.
     * Admins see all recordings, members only published ones.
     * @param int|null $id
     * @return string
     */
    public function actionRecordings(?int $id = null)
    {
        if ($id === null) {
            throw new NotFoundHttpException();
        }

        $session = $this->svc->get($id, $this->contentContainer);
        if (!$session) {
            throw new NotFoundHttpException(Yii::t('SessionsModule.base', 'Session not found.'));
        }

        if (!$session->canJoin()) {
            throw new ForbiddenHttpException();
        }

        $isAdmin = $session->canAdminister();
        $recordings = $this->svc->getRecordings($session);

        // Non-admins only see published recordings
        if (!$isAdmin) {
            $recordings = array_filter($recordings, fn($r) => $r->isPublished());
        }

        return $this->render('recordings', [
            'session' => $session,
            'recordings' => $recordings,
            'canAdminister' => $isAdmin,
        ]);
    }

    /**
     * Publish/unpublish a recording
     * @param int|null $id Session ID
     * @param string|null $recordingId
     * @param bool $publish
     * @return \yii\web\Response
     */
    public function actionPublishRecording(?int $id = null, ?string $recordingId = null, bool $publish = true)
    {
        if ($id === null || $recordingId === null) {
            throw new NotFoundHttpException();
        }

        $session = $this->svc->get($id, $this->contentContainer);
        if (!$session || !$session->canAdminister()) {
            throw new ForbiddenHttpException();
        }

        if ($this->svc->publishRecording($session, $recordingId, $publish)) {
            $this->view->success(Yii::t('SessionsModule.base', 'Recording updated.'));
        } else {
            $this->view->error(Yii::t('SessionsModule.base', 'Failed to update recording.'));
        }

        return $this->redirect(['recordings', 'id' => $id]);
    }
}
