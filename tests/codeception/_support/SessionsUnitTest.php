<?php

namespace sessions;

use humhub\modules\content\models\Content;
use humhub\modules\sessions\models\Session;
use humhub\modules\sessions\models\SessionUser;
use humhub\modules\sessions\services\BackendLoader;
use humhub\modules\sessions\services\BackendRegistry;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use humhub\libs\UUID;
use tests\codeception\_support\HumHubDbTestCase;
use Yii;

class SessionsUnitTest extends HumHubDbTestCase
{
    public function _before()
    {
        parent::_before();

        // Trigger module init so all real backends get loaded first
        Yii::$app->getModule('sessions');

        // Capture which backend IDs were registered, then replace ALL with test stubs
        $registeredIds = array_keys(BackendRegistry::getAll());
        if (empty($registeredIds)) {
            $registeredIds = ['bbb'];
        }

        BackendRegistry::clear();
        BackendLoader::clearCache();
        foreach ($registeredIds as $id) {
            BackendRegistry::register(new TestBackend($id));
        }

        // Ensure module settings allow all test backends
        $module = Yii::$app->getModule('sessions');
        $module->settings->set('allowedBackends', json_encode($registeredIds));
    }

    /**
     * Create a test session with sensible defaults.
     *
     * @param string $name URL slug
     * @param string $title Session title
     * @param Space|null $container Content container (space)
     * @param array $attributes Additional attributes to set
     * @return Session
     */
    protected function createSession(
        string $name = 'test-session',
        string $title = 'Test Session',
        ?Space $container = null,
        array $attributes = []
    ): Session {
        $session = new Session();
        $session->uuid = UUID::v4();
        $session->name = $name;
        $session->title = $title;
        $session->backend_type = $attributes['backend_type'] ?? 'bbb';
        $session->moderator_pw = $attributes['moderator_pw'] ?? 'mod-' . Yii::$app->security->generateRandomString(6);
        $session->attendee_pw = $attributes['attendee_pw'] ?? 'att-' . Yii::$app->security->generateRandomString(6);
        $session->creator_user_id = $attributes['creator_user_id'] ?? Yii::$app->user->id;
        $session->enabled = $attributes['enabled'] ?? true;
        $session->public_join = $attributes['public_join'] ?? false;
        $session->join_can_start = $attributes['join_can_start'] ?? false;
        $session->join_can_moderate = $attributes['join_can_moderate'] ?? false;
        $session->has_waitingroom = $attributes['has_waitingroom'] ?? false;
        $session->allow_recording = $attributes['allow_recording'] ?? true;
        $session->mute_on_entry = $attributes['mute_on_entry'] ?? false;
        $session->content->visibility = $attributes['visibility'] ?? Content::VISIBILITY_PUBLIC;

        if ($container) {
            $session->content->container = $container;
        }

        // Apply any remaining attributes
        foreach ($attributes as $key => $value) {
            if ($session->hasAttribute($key) && !in_array($key, [
                'backend_type', 'moderator_pw', 'attendee_pw', 'creator_user_id',
                'enabled', 'public_join', 'join_can_start', 'join_can_moderate',
                'has_waitingroom', 'allow_recording', 'mute_on_entry', 'visibility',
            ])) {
                $session->$key = $value;
            }
        }

        $this->assertTrue($session->save(), 'Failed to create session: ' . print_r($session->getErrors(), true));
        return $session;
    }

    /**
     * Create a SessionUser assignment.
     */
    protected function assignUser(Session $session, User $user, string $role = 'attendee', array $options = []): SessionUser
    {
        $su = new SessionUser();
        $su->session_id = $session->id;
        $su->user_id = $user->id;
        $su->role = $role;
        $su->can_start = $options['can_start'] ?? ($role === 'moderator');
        $su->can_join = $options['can_join'] ?? true;
        $su->created_at = time();
        $this->assertTrue($su->save(), 'Failed to create SessionUser: ' . print_r($su->getErrors(), true));
        return $su;
    }
}
