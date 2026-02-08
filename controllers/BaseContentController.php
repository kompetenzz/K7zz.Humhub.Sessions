<?php

namespace humhub\modules\sessions\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\sessions\services\SessionService;
use humhub\modules\sessions\Module;
use Yii;
use yii\helpers\Url;

/**
 * Base controller for Sessions content container controllers.
 * Provides common properties and helpers for all controllers
 * that operate in a content container context (space or user).
 */
abstract class BaseContentController extends ContentContainerController
{
    /**
     * @var bool Whether a content container is required
     */
    public $requireContainer = false;

    /**
     * @var bool Whether to hide the sidebar
     */
    public $hideSidebar = true;

    /**
     * @var SessionService|null
     */
    protected ?SessionService $svc = null;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->svc = Yii::$container->get(SessionService::class);
    }

    /**
     * Helper to generate URLs in the context of the current content container
     * @param string $url
     * @return string
     */
    protected function getUrl($url)
    {
        if ($this->contentContainer) {
            return $this->contentContainer->createUrl($url);
        }
        return Url::to($url);
    }

    /**
     * Get the module instance
     * @return Module
     */
    protected function getModule(): Module
    {
        return Yii::$app->getModule('sessions');
    }
}
