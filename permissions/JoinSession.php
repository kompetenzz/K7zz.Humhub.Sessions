<?php

namespace humhub\modules\sessions\permissions;

use Yii;

/**
 * Permission to join a video conference session.
 * By default, all users are allowed to join.
 */
class JoinSession extends \humhub\libs\BasePermission
{
    protected $moduleId = 'sessions';

    protected $defaultState = self::STATE_ALLOW;

    /**
     * @inheritdoc
     */
    public function getTitle()
    {
        return Yii::t('SessionsModule.permissions', 'Join video conference session');
    }

    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return Yii::t('SessionsModule.permissions', 'Allows the user to join a video conference session.');
    }
}
