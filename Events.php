<?php

namespace humhub\modules\sessions;

use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\helpers\ControllerHelper;
use humhub\modules\ui\menu\MenuLink;
use humhub\modules\ui\menu\widgets\LeftNavigation;
use humhub\widgets\TopMenu;
use humhub\modules\sessions\models\forms\ContainerSettingsForm;
use Yii;
use yii\helpers\Html;

class Events
{
    public static function onBeforeRequest()
    {
        try {
            static::registerAutoloader();
        } catch (\Throwable $e) {
            Yii::error($e, 'sessions');
        }
    }

    /**
     * Register composer autoloader for vendor dependencies
     */
    public static function registerAutoloader()
    {
        $autoloadPath = Yii::getAlias('@sessions/vendor/autoload.php');
        if (file_exists($autoloadPath)) {
            require $autoloadPath;
        }
    }

    private static function addNavItem(
        $menu,
        $label,
        ContentContainerActiveRecord $container = null,
        int $order = 0
    ): void {
        $url = $container ? $container->createUrl('/sessions/list') : '/sessions/list';
        $is_active = ControllerHelper::isActivePath('sessions', ['list', 'session']);

        if ($container) {
            $is_active = $is_active && Yii::$app->controller->contentContainer &&
                Yii::$app->controller->contentContainer->id === $container->id;
        } else {
            $is_active = $is_active && Yii::$app->controller->contentContainer === null;
        }

        $menu->addEntry(new MenuLink([
            'id' => 'sessions-link',
            'label' => Html::encode($label),
            'url' => $url,
            'icon' => 'video-camera',
            'isActive' => $is_active,
            'sortOrder' => $order ?: 1000,
        ]));
    }

    public static function initNav(TopMenu $menu)
    {
        $addNavigationEntry = Yii::$app->getModule('sessions')->settings->get('addNavItem', true);

        if ($addNavigationEntry) {
            self::addNavItem(
                $menu,
                Yii::$app->getModule('sessions')->settings->get('navItemLabel', 'Sessions'),
            );
        }
    }

    /**
     * Initialize Space/Profile menu items
     *
     * @param ContentContainerActiveRecord $container
     * @param LeftNavigation $menu
     */
    public static function initContainerNav(ContentContainerActiveRecord $container, LeftNavigation $menu)
    {
        if (empty($container) || !$container->moduleManager->isEnabled('sessions')) {
            return;
        }

        $addNavigationEntry = Yii::$app->getModule('sessions')->settings->contentContainer($container)->get('addNavItem', true);

        if ($addNavigationEntry) {
            $settings = new ContainerSettingsForm(['contentContainer' => $container]);
            self::addNavItem(
                $menu,
                $settings->navItemLabel,
                $container
            );
        }
    }

    public static function onTopMenuInit($event)
    {
        try {
            self::initNav($event->sender);
        } catch (\Throwable $e) {
            Yii::error($e, 'sessions');
        }
    }

    public static function onSpaceMenuInit($event)
    {
        try {
            $spaceMenu = $event->sender;
            self::initContainerNav($spaceMenu->space, $spaceMenu);
        } catch (\Throwable $e) {
            Yii::error($e, 'sessions');
        }
    }

    public static function onProfileMenuInit($event)
    {
        try {
            $profileMenu = $event->sender;
            self::initContainerNav($profileMenu->user, $profileMenu);
        } catch (\Throwable $e) {
            Yii::error($e, 'sessions');
        }
    }
}
