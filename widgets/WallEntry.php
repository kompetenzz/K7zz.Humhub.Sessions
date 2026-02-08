<?php

namespace humhub\modules\sessions\widgets;

use humhub\modules\content\widgets\WallEntry as BaseWallEntry;
use humhub\modules\sessions\services\BackendRegistry;

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
     */
    public function renderContent()
    {
        $session = $this->contentObject;
        $backend = BackendRegistry::get($session->backend_type);

        return $this->render('@sessions/views/widgets/wall-entry', [
            'session' => $session,
            'backend' => $backend,
        ]);
    }
}
