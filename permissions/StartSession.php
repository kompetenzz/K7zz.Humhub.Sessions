<?php

namespace humhub\modules\sessions\permissions;

use humhub\modules\space\models\Space;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\User;
use Yii;

/**
 * Permission to start a video conference session.
 */
class StartSession extends \humhub\libs\BasePermission
{
    protected $moduleId = 'sessions';

    public $defaultAllowedGroups = [
        Space::USERGROUP_ADMIN,
        Space::USERGROUP_MODERATOR,
        User::USERGROUP_SELF,
    ];

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->defaultAllowedGroups[] = Group::getAdminGroupId();
        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function getTitle()
    {
        return Yii::t('SessionsModule.permissions', 'Start video conference session');
    }

    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return Yii::t('SessionsModule.permissions', 'Allows the user to start a video conference session.');
    }
}
