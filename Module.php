<?php

/**
 * This module provides a flexible video conferencing solution with pluggable backends.
 * It supports multiple video conferencing providers (BigBlueButton, Jitsi, Opentalk, Zoom)
 * through a plugin architecture, allowing users to choose their preferred backend per session.
 *
 * Backends are automatically discovered from the plugins/ directory.
 * To add a new backend, create a subdirectory with a *Backend.php class
 * that implements VideoBackendInterface.
 */

namespace humhub\modules\sessions;

use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\content\components\ContentContainerModule;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use humhub\modules\sessions\models\Session;
use humhub\modules\sessions\services\BackendLoader;
use humhub\modules\sessions\services\SessionService;
use humhub\modules\sessions\permissions\{
    Admin,
    StartSession,
    JoinSession
};
use Yii;
use yii\helpers\Url;

class Module extends ContentContainerModule
{
    public $guid = 'sessions';
    public $controllerNamespace = 'humhub\modules\sessions\controllers';
    public $resourcesPath = 'resources';

    public function init()
    {
        parent::init();

        // Dynamically load all backends from plugins/ directory
        $loader = new BackendLoader($this);
        $loader->loadAll();

        // Register SessionService in DI container
        Yii::$container->set(SessionService::class, function () {
            return new SessionService($this);
        });
    }

    /**
     * @inheritdoc
     */
    public function getContentContainerTypes()
    {
        return [
            Space::class,
            User::class,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getContainerPermissions($contentContainer = null)
    {
        return [
            new Admin(),
            new StartSession(),
            new JoinSession()
        ];
    }

    /**
     * @inheritdoc
     */
    public function getPermissions($contentContainer = null)
    {
        return [
            new Admin(),
            new StartSession(),
            new JoinSession()
        ];
    }

    /**
     * @inheritdoc
     */
    public function getContentClasses(): array
    {
        return [Session::class];
    }

    /**
     * @inheritdoc
     */
    public function getConfigUrl()
    {
        return Url::to(['/sessions/config/']);
    }

    /**
     * @inheritdoc
     */
    public function getContentContainerConfigUrl(ContentContainerActiveRecord $container)
    {
        return $container->createUrl('/sessions/container-config');
    }

    /**
     * @inheritdoc
     */
    public function getContentContainerDescription(ContentContainerActiveRecord $container)
    {
        if ($container instanceof Space) {
            return Yii::t('SessionsModule.base', 'Adds video conferencing sessions to this space.');
        } elseif ($container instanceof User) {
            return Yii::t('SessionsModule.base', 'Adds video conferencing sessions to your profile.');
        }
        return Yii::t('SessionsModule.base', 'Adds video conferencing sessions to this container.');
    }
}
