<?php

namespace humhub\modules\sessions\components;

use humhub\modules\content\components\ContentContainerActiveRecord;
use yii\base\Component;
use yii\web\UrlRuleInterface;
use yii\web\UrlManager;
use humhub\components\ContentContainerUrlRuleInterface;
use humhub\modules\sessions\models\Session;

/**
 * URL rule component for sessions module routes in HumHub.
 *
 * Handles pretty URLs for session actions, both global and within content containers (spaces, users).
 * Converts session IDs to slugs (names) and vice versa for cleaner URLs.
 *
 * Implements both UrlRuleInterface and ContentContainerUrlRuleInterface.
 */
class SessionUrlRule extends Component implements UrlRuleInterface, ContentContainerUrlRuleInterface
{
    /**
     * Prefix for all session routes.
     * @var string
     */
    private string $routePrefix = 'sessions/session/';

    /**
     * @inheritdoc
     * Handles global (non-container) URLs for sessions.
     */
    public function createUrl($manager, $route, $params)
    {
        // If in content container
        if (isset($params['cguid'])) {
            return false;
        }

        if (strpos($route, $this->routePrefix) !== 0 || !isset($params['id'])) {
            return false;
        }

        $session = Session::findOne(['id' => $params['id']]);

        if ($session === null) {
            return false;
        }

        unset($params['id']);
        $url = $route . '/' . $session->name;

        if (!empty($params) && ($query = http_build_query($params)) !== '') {
            $url .= '?' . $query;
        }
        return $url;
    }

    /**
     * @inheritdoc
     * Handles parsing of global session URLs.
     * Example: 'sessions/session/<action>/<slug>' => 'sessions/session/<action>'
     */
    public function parseRequest($manager, $request)
    {
        $pathInfo = $request->getPathInfo();
        if (strpos($pathInfo, $this->routePrefix) === 0) {
            return $this->getSessionRoute($pathInfo, $request->get());
        }
        return false;
    }

    /**
     * @inheritdoc
     * Handles content container URLs for sessions.
     * Example: 's/<space>/sessions/session/<action>/<slug>' => 'sessions/session/<action>'
     */
    public function createContentContainerUrl(UrlManager $manager, string $containerUrlPath, string $route, array $params)
    {
        if (strpos($route, $this->routePrefix) !== 0 || !isset($params['id'])) {
            return false;
        }

        $session = Session::findOne(['id' => $params['id']]);

        if ($session === null) {
            return false;
        }

        unset($params['id']);
        $url = $containerUrlPath . '/' . $route . '/' . $session->name;
        if (!empty($params) && ($query = http_build_query($params)) !== '') {
            $url .= "?$query";
        }
        return $url;
    }

    /**
     * @inheritdoc
     * Handles parsing of content container session URLs.
     * Example: 's/<space>/sessions/session/<action>/<slug>' => 'sessions/session/<action>'
     */
    public function parseContentContainerRequest(ContentContainerActiveRecord $container, UrlManager $manager, string $containerUrlPath, array $urlParams)
    {
        if (strpos($containerUrlPath, $this->routePrefix) === 0) {
            return $this->getSessionRoute($containerUrlPath, $urlParams);
        }

        return false;
    }

    /**
     * Helper to resolve a session route from a path and parameters.
     * Converts a slug (name) back to a session ID for routing.
     * @param string $path
     * @param array $getParams
     * @return array|bool
     */
    private function getSessionRoute(string $path, array $getParams): array|bool
    {
        $parts = explode('/', $path);
        $action = $parts[2] ?? null;
        $name = $parts[3] ?? null;
        $session = Session::find()
            ->where(['name' => $name])
            ->one();
        if (isset($action) && isset($name) && $session !== null) {
            $route = $this->routePrefix . $action;
            $params = $getParams;
            $params['id'] = $session->id;
            return [$route, $params];
        }

        return false;
    }
}
