<?php

use humhub\components\Application;
use humhub\modules\space\widgets\Menu;
use humhub\modules\user\widgets\ProfileMenu;
use humhub\widgets\TopMenu;
use humhub\modules\sessions\Events;

return [
    'id' => 'sessions',
    'class' => 'humhub\modules\sessions\Module',
    'namespace' => 'humhub\modules\sessions',
    'events' => [
        ['class' => Application::class, 'event' => Application::EVENT_BEFORE_REQUEST, 'callback' => [Events::class, 'onBeforeRequest']],
        ['class' => TopMenu::class, 'event' => TopMenu::EVENT_INIT, 'callback' => [Events::class, 'onTopMenuInit']],
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
        ['class' => ProfileMenu::class, 'event' => ProfileMenu::EVENT_INIT, 'callback' => [Events::class, 'onProfileMenuInit']],
    ],
    'urlManagerRules' => [
        ['class' => 'humhub\modules\sessions\components\SessionUrlRule'],
    ]
];
