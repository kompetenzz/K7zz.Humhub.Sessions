<?php

namespace humhub\modules\sessions\permissions;

use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use humhub\modules\admin\components\BaseAdminPermission;
use Yii;

/**
 * Admin permission for the Sessions module.
 * Allows users to administer video conference sessions.
 */
class Admin extends BaseAdminPermission
{
    protected $moduleId = 'sessions';

    public $defaultAllowedGroups = [
        Space::USERGROUP_ADMIN,
        Space::USERGROUP_OWNER,
        User::USERGROUP_SELF,
    ];

    protected $fixedGroups = [
        Space::USERGROUP_USER,
        User::USERGROUP_FRIEND,
        User::USERGROUP_GUEST,
        Space::USERGROUP_GUEST,
    ];

    /**
     * @inheritdoc
     */
    public function getTitle()
    {
        return Yii::t('SessionsModule.permissions', 'Administer video conference sessions');
    }

    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return Yii::t('SessionsModule.permissions', 'Allows the user to maintain all video conference sessions.');
    }
}
