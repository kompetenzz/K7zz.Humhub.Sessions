<?php

namespace humhub\modules\sessions\tests\codeception\unit\models;

use humhub\modules\sessions\models\Session;
use humhub\modules\sessions\models\SessionUser;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use humhub\libs\UUID;
use sessions\SessionsUnitTest;

class SessionTest extends SessionsUnitTest
{
    // ========== Validation Tests ==========

    public function testRequiredFields()
    {
        $session = new Session();
        $this->assertFalse($session->validate());
        $this->assertNotEmpty($session->getErrors('uuid'));
        $this->assertNotEmpty($session->getErrors('name'));
        $this->assertNotEmpty($session->getErrors('backend_type'));
        $this->assertNotEmpty($session->getErrors('moderator_pw'));
        $this->assertNotEmpty($session->getErrors('attendee_pw'));
        $this->assertNotEmpty($session->getErrors('creator_user_id'));
    }

    public function testUuidUniqueness()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);

        $session1 = $this->createSession('session-1', 'Session 1', $space);
        $uuid = $session1->uuid;

        // Try to create a second session with the same UUID
        $session2 = new Session();
        $session2->uuid = $uuid;
        $session2->name = 'session-2';
        $session2->backend_type = 'bbb';
        $session2->moderator_pw = 'mod123';
        $session2->attendee_pw = 'att123';
        $session2->creator_user_id = \Yii::$app->user->id;
        $session2->content->container = $space;

        $this->assertFalse($session2->save());
        $this->assertNotEmpty($session2->getErrors('uuid'));
    }

    // ========== Backend Config Tests ==========

    public function testBackendConfigGetSet()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('config-test', 'Config Test', $space);

        // Set config
        $session->setBackendConfig(['layout' => 'SMART_LAYOUT', 'maxParticipants' => 50]);
        $this->assertTrue($session->save(false));

        // Reload and verify
        $session->refresh();
        $config = $session->getBackendConfig();
        $this->assertEquals('SMART_LAYOUT', $config['layout']);
        $this->assertEquals(50, $config['maxParticipants']);
    }

    public function testBackendConfigValueGetSet()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('config-val-test', 'Config Value Test', $space);

        $session->setBackendConfigValue('layout', 'VIDEO_FOCUS');
        $session->setBackendConfigValue('welcome', 'Hello!');
        $this->assertTrue($session->save(false));

        $session->refresh();
        $this->assertEquals('VIDEO_FOCUS', $session->getBackendConfigValue('layout'));
        $this->assertEquals('Hello!', $session->getBackendConfigValue('welcome'));
        $this->assertNull($session->getBackendConfigValue('nonexistent'));
        $this->assertEquals('default', $session->getBackendConfigValue('nonexistent', 'default'));
    }

    public function testEmptyBackendConfig()
    {
        $session = new Session();
        $session->backend_config = null;
        $this->assertEquals([], $session->getBackendConfig());

        $session->backend_config = '';
        $this->assertEquals([], $session->getBackendConfig());
    }

    public function testInvalidBackendConfigJson()
    {
        $session = new Session();
        $session->backend_config = 'not valid json{{{';
        $this->assertEquals([], $session->getBackendConfig());
    }

    // ========== Public Token Tests ==========

    public function testEnsurePublicToken()
    {
        $session = new Session();
        $this->assertNull($session->public_token);

        $session->ensurePublicToken();
        $this->assertNotNull($session->public_token);
        $this->assertEquals(48, strlen($session->public_token));
    }

    public function testPublicTokenNotOverwritten()
    {
        $session = new Session();
        $session->public_token = 'existing-token';

        $session->ensurePublicToken();
        $this->assertEquals('existing-token', $session->public_token);
    }

    public function testPublicTokenGeneratedOnSaveWhenPublicJoin()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);

        $session = $this->createSession('public-test', 'Public Test', $space, [
            'public_join' => true,
        ]);

        $this->assertNotNull($session->public_token);
        $this->assertNotEmpty($session->public_token);
    }

    public function testPublicTokenNotGeneratedWhenNotPublicJoin()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);

        $session = $this->createSession('private-test', 'Private Test', $space, [
            'public_join' => false,
        ]);

        $this->assertNull($session->public_token);
    }

    // ========== Relations Tests ==========

    public function testSessionUsersRelation()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('rel-test', 'Relation Test', $space);

        $user1 = User::findOne(['username' => 'User1']);
        $user2 = User::findOne(['username' => 'User2']);

        $this->assignUser($session, $user1, 'moderator');
        $this->assignUser($session, $user2, 'attendee');

        $session->refresh();
        $this->assertCount(2, $session->sessionUsers);
        $this->assertCount(2, $session->users);
    }

    public function testAttendeeUsersRelation()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('attendee-rel', 'Attendee Relation', $space);

        $user1 = User::findOne(['username' => 'User1']);
        $user2 = User::findOne(['username' => 'User2']);

        $this->assignUser($session, $user1, 'moderator');
        $this->assignUser($session, $user2, 'attendee');

        $session->refresh();
        $attendees = $session->getAttendeeUsers()->all();
        $this->assertCount(1, $attendees);
        $this->assertEquals($user2->id, $attendees[0]->id);
    }

    public function testModeratorUsersRelation()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('mod-rel', 'Moderator Relation', $space);

        $user1 = User::findOne(['username' => 'User1']);
        $user2 = User::findOne(['username' => 'User2']);

        $this->assignUser($session, $user1, 'moderator');
        $this->assignUser($session, $user2, 'attendee');

        $session->refresh();
        $moderators = $session->getModeratorUsers()->all();
        $this->assertCount(1, $moderators);
        $this->assertEquals($user1->id, $moderators[0]->id);
    }

    // ========== Content Methods Tests ==========

    public function testGetContentName()
    {
        $session = new Session();
        $session->title = 'My Meeting';
        $this->assertEquals('My Meeting', $session->getContentName());
    }

    public function testGetContentNameFallback()
    {
        $session = new Session();
        $session->title = null;
        $this->assertNotEmpty($session->getContentName());
    }

    public function testGetContentDescription()
    {
        $session = new Session();
        $session->description = 'A detailed description';
        $this->assertEquals('A detailed description', $session->getContentDescription());
    }

    public function testGetContentDescriptionFallback()
    {
        $session = new Session();
        $session->description = null;
        $this->assertNotEmpty($session->getContentDescription());
    }

    public function testGetIcon()
    {
        $session = new Session();
        $this->assertEquals('fa-video-camera', $session->getIcon());
    }

    // ========== Permission Tests ==========

    public function testModeratorViaSessionUser()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('perm-mod', 'Permission Mod', $space);

        $user1 = User::findOne(['username' => 'User1']);
        $this->assignUser($session, $user1, 'moderator');

        // Switch to User1 and check
        $this->becomeUser('User1');
        $this->assertTrue($session->isModerator());
    }

    public function testJoinCanModerateFlag()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('perm-jcm', 'Permission JCM', $space, [
            'join_can_moderate' => true,
        ]);

        $user1 = User::findOne(['username' => 'User1']);
        $this->assignUser($session, $user1, 'attendee');

        $this->becomeUser('User1');
        $session->refresh();
        $this->assertTrue($session->isModerator());
    }

    public function testJoinCanStartFlag()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('perm-jcs', 'Permission JCS', $space, [
            'join_can_start' => true,
        ]);

        $user1 = User::findOne(['username' => 'User1']);
        $this->assignUser($session, $user1, 'attendee');

        $this->becomeUser('User1');
        $session->refresh();
        $this->assertTrue($session->canStart());
    }

    public function testAttendeeCanJoinButNotStart()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('perm-att', 'Permission Att', $space);

        $user1 = User::findOne(['username' => 'User1']);
        $this->assignUser($session, $user1, 'attendee');

        $this->becomeUser('User1');
        $session->refresh();
        $this->assertTrue($session->canJoin());
        $this->assertFalse($session->isModerator());
    }

    public function testModeratorCanStartAndJoin()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('perm-mod-full', 'Permission Mod Full', $space);

        $user1 = User::findOne(['username' => 'User1']);
        $this->assignUser($session, $user1, 'moderator');

        $this->becomeUser('User1');
        $session->refresh();
        $this->assertTrue($session->canStart());
        $this->assertTrue($session->canJoin());
        $this->assertTrue($session->isModerator());
    }
}
