<?php

namespace humhub\modules\sessions\tests\codeception\unit\services;

use humhub\modules\sessions\models\forms\SessionForm;
use humhub\modules\sessions\models\Session;
use humhub\modules\sessions\services\BackendRegistry;
use humhub\modules\sessions\services\SessionService;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use sessions\SessionsUnitTest;
use sessions\TestBackend;
use Yii;

/**
 * Tests that session settings flow correctly through SessionService to the backend API calls.
 */
class SessionServiceTest extends SessionsUnitTest
{
    private function getService(): SessionService
    {
        return Yii::createObject(SessionService::class);
    }

    // ========== Backend Resolution ==========

    public function testGetBackendResolvesCorrectType()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('resolve-test', 'Resolve Test', $space);

        $service = $this->getService();
        $backend = $service->getBackend($session);

        $this->assertNotNull($backend);
        $this->assertInstanceOf(TestBackend::class, $backend);
        $this->assertEquals('bbb', $backend->getId());
    }

    // ========== createMeeting: Settings reach the backend ==========

    public function testStartPassesAllSettingsToBackend()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);

        // Create session via form with all settings set
        $form = SessionForm::create($space);
        $form->name = 'api-settings';
        $form->title = 'API Settings Test';
        $form->description = 'Welcome to the test session';
        $form->backend_type = 'bbb';
        $form->muteOnEntry = true;
        $form->hasWaitingRoom = true;
        $form->allowRecording = false;
        $form->publicJoin = true;
        $form->joinCanStart = true;
        $form->joinCanModerate = true;
        $form->enabled = true;
        $form->backendConfig = [
            'layout' => 'PRESENTATION_FOCUS',
            'maxParticipants' => 30,
        ];
        $this->assertTrue($form->save(), 'Form save failed: ' . print_r($form->getErrors(), true));

        // Re-fetch from DB to ensure we test actual persisted values
        $session = Session::findOne($form->id);
        $this->assertNotNull($session);

        // Call start() which delegates to backend->createMeeting()
        $service = $this->getService();
        $result = $service->start($session);

        $this->assertNotNull($result, 'start() returned null');
        $this->assertArrayHasKey('meetingId', $result);

        // Verify the backend received the session with correct settings
        $backend = TestBackend::getInstance('bbb');
        $this->assertNotEmpty($backend->callLog, 'No API calls logged');

        $call = $backend->callLog[0];
        $this->assertEquals('createMeeting', $call['method']);

        /** @var Session $passedSession */
        $passedSession = $call['session'];

        // Verify all permission settings are present on the session object
        $this->assertTrue((bool) $passedSession->mute_on_entry, 'mute_on_entry not passed to backend');
        $this->assertTrue((bool) $passedSession->has_waitingroom, 'has_waitingroom not passed to backend');
        $this->assertFalse((bool) $passedSession->allow_recording, 'allow_recording not passed to backend');
        $this->assertTrue((bool) $passedSession->public_join, 'public_join not passed to backend');
        $this->assertTrue((bool) $passedSession->join_can_start, 'join_can_start not passed to backend');
        $this->assertTrue((bool) $passedSession->join_can_moderate, 'join_can_moderate not passed to backend');

        // Verify text fields
        $this->assertEquals('API Settings Test', $passedSession->title);
        $this->assertEquals('Welcome to the test session', $passedSession->description);

        // Verify backend config (JSON)
        $this->assertEquals('PRESENTATION_FOCUS', $passedSession->getBackendConfigValue('layout'));
        $this->assertEquals(30, $passedSession->getBackendConfigValue('maxParticipants'));

        // Verify public token was generated
        $this->assertNotEmpty($passedSession->public_token, 'public_token missing on session passed to backend');

        // Verify passwords are available for backend
        $this->assertNotEmpty($passedSession->moderator_pw);
        $this->assertNotEmpty($passedSession->attendee_pw);
    }

    // ========== joinUrl: User + moderator flag reach the backend ==========

    public function testJoinUrlPassesUserAndModeratorFlag()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('join-test', 'Join Test', $space);

        $user = User::findOne(['username' => 'User1']);
        $service = $this->getService();

        // Join as regular participant
        $url = $service->joinUrl($session, $user, false);
        $this->assertNotNull($url);

        $backend = TestBackend::getInstance('bbb');
        $call = $this->findLastCall($backend, 'joinUrl');
        $this->assertNotNull($call, 'joinUrl call not logged');
        $this->assertEquals($user->id, $call['user']->id);
        $this->assertFalse($call['isModerator']);

        // Join as moderator
        $url = $service->joinUrl($session, $user, true);
        $this->assertNotNull($url);

        $call = $this->findLastCall($backend, 'joinUrl');
        $this->assertTrue($call['isModerator']);
    }

    // ========== anonymousJoinUrl: Display name reaches the backend ==========

    public function testAnonymousJoinUrlPassesDisplayName()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('anon-test', 'Anon Test', $space, [
            'public_join' => true,
        ]);

        $service = $this->getService();
        $url = $service->anonymousJoinUrl($session, 'Gast Benutzer');
        $this->assertNotNull($url);

        $backend = TestBackend::getInstance('bbb');
        $call = $this->findLastCall($backend, 'anonymousJoinUrl');
        $this->assertNotNull($call, 'anonymousJoinUrl call not logged');
        $this->assertEquals('Gast Benutzer', $call['displayName']);
        $this->assertEquals($session->uuid, $call['session']->uuid);
    }

    // ========== endMeeting: Session reaches the backend ==========

    public function testEndPassesSessionToBackend()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('end-test', 'End Test', $space);

        $service = $this->getService();
        $result = $service->end($session);
        $this->assertTrue($result);

        $backend = TestBackend::getInstance('bbb');
        $call = $this->findLastCall($backend, 'endMeeting');
        $this->assertNotNull($call, 'endMeeting call not logged');
        $this->assertEquals($session->uuid, $call['session']->uuid);
        $this->assertNotEmpty($call['session']->moderator_pw, 'moderator_pw missing in endMeeting call');
    }

    // ========== Feature support checks ==========

    public function testFeatureSupportDelegatesToBackend()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('feature-test', 'Feature Test', $space);

        $service = $this->getService();

        $this->assertTrue($service->supportsWaitingRoom($session));
        $this->assertTrue($service->supportsPublicJoin($session));
        $this->assertFalse($service->supportsRecordings($session));
        $this->assertFalse($service->supportsPresentationUpload($session));
    }

    // ========== Soft delete ==========

    public function testDeleteSoftDeletesSession()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('delete-test', 'Delete Test', $space);
        $sessionId = $session->id;

        $service = $this->getService();
        $result = $service->delete($sessionId, $space);
        $this->assertTrue($result);

        // Should not appear in normal queries
        $found = $service->get($sessionId, $space);
        $this->assertNull($found, 'Soft-deleted session should not be returned by get()');

        // But still exists in DB with deleted_at set
        $raw = Session::findOne($sessionId);
        $this->assertNotNull($raw);
        $this->assertNotNull($raw->deleted_at);
        $this->assertIsInt($raw->deleted_at);
    }

    // ========== Update roundtrip: Form → DB → API ==========

    public function testUpdateRoundtrip()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);

        // 1. Create with initial values
        $form = SessionForm::create($space);
        $form->name = 'roundtrip';
        $form->title = 'Roundtrip Test';
        $form->backend_type = 'bbb';
        $form->muteOnEntry = false;
        $form->hasWaitingRoom = false;
        $form->allowRecording = true;
        $this->assertTrue($form->save());

        // 2. Update via form
        $session = Session::findOne($form->id);
        $editForm = SessionForm::edit($session);
        $editForm->muteOnEntry = true;
        $editForm->hasWaitingRoom = true;
        $editForm->allowRecording = false;
        $editForm->backendConfig = ['layout' => 'VIDEO_FOCUS'];
        $this->assertTrue($editForm->save());

        // 3. Re-fetch from DB and call API
        $updated = Session::findOne($form->id);
        $service = $this->getService();
        $service->start($updated);

        // 4. Verify the backend received updated values
        $backend = TestBackend::getInstance('bbb');
        $call = $this->findLastCall($backend, 'createMeeting');
        $this->assertNotNull($call);

        $s = $call['session'];
        $this->assertTrue((bool) $s->mute_on_entry, 'Updated mute_on_entry not passed to API');
        $this->assertTrue((bool) $s->has_waitingroom, 'Updated has_waitingroom not passed to API');
        $this->assertFalse((bool) $s->allow_recording, 'Updated allow_recording not passed to API');
        $this->assertEquals('VIDEO_FOCUS', $s->getBackendConfigValue('layout'), 'Updated layout not passed to API');
    }

    // ========== Helper ==========

    private function findLastCall(TestBackend $backend, string $method): ?array
    {
        $found = null;
        foreach ($backend->callLog as $call) {
            if ($call['method'] === $method) {
                $found = $call;
            }
        }
        return $found;
    }
}
