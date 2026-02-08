<?php

namespace humhub\modules\sessions\controllers;

use humhub\modules\sessions\services\BackendRegistry;
use Yii;

/**
 * Controller for listing all sessions in a content container.
 */
class ListController extends BaseContentController
{
    /**
     * Lists all sessions for the current content container
     * @param int|null $highlight Session ID to highlight
     * @param string|null $scope Filter scope for global view: 'global'|'spaces'|'users'|'all'
     * @return string
     */
    public function actionIndex(?int $highlight = 0, ?string $scope = null)
    {
        // If we have a content container, use the normal list (no scope filter)
        if ($this->contentContainer !== null) {
            $sessions = array_filter(
                $this->svc->list($this->contentContainer),
                fn($s) => $s->canJoin() || $s->canStart()
            );
            $currentScope = null;
        } else {
            // Global view - allow scope filtering
            $validScopes = ['global', 'spaces', 'users', 'all'];
            $currentScope = in_array($scope, $validScopes) ? $scope : 'global';

            $sessions = array_filter(
                $this->svc->listAll($currentScope),
                fn($s) => $s->canJoin() || $s->canStart()
            );
        }

        $rows = array_map(
            fn($s) => [
                'model' => $s,
                'running' => $this->svc->isRunning($s),
                'backend' => BackendRegistry::get($s->backend_type),
            ],
            $sessions
        );

        return $this->render('@sessions/views/list/index', [
            'rows' => $rows,
            'highlightId' => $highlight,
            'backends' => BackendRegistry::getConfigured(),
            'scope' => $currentScope,
            'isGlobalView' => $this->contentContainer === null,
        ]);
    }
}
