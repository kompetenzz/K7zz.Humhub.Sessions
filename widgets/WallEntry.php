<?php

namespace humhub\modules\sessions\widgets;

use humhub\modules\content\widgets\WallEntry as BaseWallEntry;
use humhub\modules\file\widgets\ShowFiles;
use humhub\modules\sessions\services\BackendRegistry;
use humhub\modules\sessions\services\SessionService;

/**
 * Wall entry widget for sessions in the activity stream.
 */
class WallEntry extends BaseWallEntry
{
    /**
     * @inheritdoc
     */
    public $editRoute = '/sessions/session/edit';

    /**
     * @inheritdoc
     * Disable default file preview - we show the image ourselves.
     */
    public $showFiles = false;

    /**
     * @inheritdoc
     */
    public $addonOptions = [
        ShowFiles::class => ['preview' => false, 'active' => false],
    ];

    /**
     * @inheritdoc
     */
    public function run()
    {
        try {
            $session = $this->contentObject;
            $backend = BackendRegistry::get($session->backend_type);

            $running = false;
            try {
                $svc = new SessionService(\Yii::$app->getModule('sessions'));
                $running = $svc->isRunning($session);
            } catch (\Throwable $e) {
                // Backend not configured - ignore
            }

            return $this->render('@sessions/views/widgets/wall-entry', [
                'session' => $session,
                'backend' => $backend,
                'running' => $running,
            ]);
        } catch (\Throwable $e) {
            \Yii::error('WallEntry render failed: ' . $e->getMessage(), 'sessions');
            return '';
        }
    }
}
