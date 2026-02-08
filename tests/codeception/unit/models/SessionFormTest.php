<?php

namespace humhub\modules\sessions\tests\codeception\unit\models;

use humhub\modules\sessions\models\forms\SessionForm;
use humhub\modules\sessions\models\Session;
use humhub\modules\sessions\models\SessionUser;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use sessions\SessionsUnitTest;

class SessionFormTest extends SessionsUnitTest
{
    // ========== Factory Method Tests ==========

    public function testCreateFormDefaults()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);

        $form = SessionForm::create($space);
        $this->assertNull($form->id);
        $this->assertNotEmpty($form->moderator_pw);
        $this->assertNotEmpty($form->attendee_pw);
        $this->assertTrue($form->enabled);
        $this->assertTrue($form->joinByPermissions);
        $this->assertTrue($form->moderateByPermissions);
        $this->assertFalse($form->publicJoin);
        $this->assertTrue($form->allowRecording);
    }

    public function testCreateFormGeneratesPasswords()
    {
        $this->becomeUser('Admin');
        $form = SessionForm::create();

        $this->assertNotEmpty($form->moderator_pw);
        $this->assertNotEmpty($form->attendee_pw);
        $this->assertNotEquals($form->moderator_pw, $form->attendee_pw);
    }

    public function testEditFormLoadsSessionData()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('edit-test', 'Edit Test', $space, [
            'public_join' => true,
            'has_waitingroom' => true,
            'mute_on_entry' => true,
        ]);

        $form = SessionForm::edit($session);
        $this->assertEquals($session->id, $form->id);
        $this->assertEquals('edit-test', $form->name);
        $this->assertEquals('Edit Test', $form->title);
        $this->assertEquals('bbb', $form->backend_type);
        $this->assertTrue($form->publicJoin);
        $this->assertTrue($form->hasWaitingRoom);
        $this->assertTrue($form->muteOnEntry);
    }

    public function testEditFormLoadsUserAssignments()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('user-load', 'User Load', $space);

        $user1 = User::findOne(['username' => 'User1']);
        $user2 = User::findOne(['username' => 'User2']);

        $this->assignUser($session, $user1, 'moderator');
        $this->assignUser($session, $user2, 'attendee');

        $form = SessionForm::edit($session);

        // When users are manually assigned, permissions toggles should be off
        $this->assertFalse($form->joinByPermissions);
        $this->assertFalse($form->moderateByPermissions);
        $this->assertContains($user1->guid, $form->moderatorRefs);
        $this->assertContains($user2->guid, $form->attendeeRefs);
    }

    public function testEditFormNoUserAssignmentsMeansPermissionBased()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('perm-based', 'Permission Based', $space);

        // No user assignments → joinByPermissions/moderateByPermissions should be true
        $form = SessionForm::edit($session);
        $this->assertTrue($form->joinByPermissions);
        $this->assertTrue($form->moderateByPermissions);
        $this->assertEmpty($form->attendeeRefs);
        $this->assertEmpty($form->moderatorRefs);
    }

    // ========== Validation Tests ==========

    public function testValidationRequiresSlug()
    {
        $this->becomeUser('Admin');
        $form = SessionForm::create();
        $form->name = '';
        $form->backend_type = 'bbb';

        $this->assertFalse($form->validate(['name']));
        $this->assertNotEmpty($form->getErrors('name'));
    }

    public function testValidationSlugPattern()
    {
        $this->becomeUser('Admin');
        $form = SessionForm::create();
        $form->backend_type = 'bbb';

        // Valid slugs
        $form->name = 'valid-slug';
        $this->assertTrue($form->validate(['name']));

        $form->name = 'slug-123';
        $this->assertTrue($form->validate(['name']));

        $form->name = 'a';
        $this->assertTrue($form->validate(['name']));

        // Invalid slugs
        $form->name = 'Invalid Slug';
        $this->assertFalse($form->validate(['name']));

        $form->name = 'UPPERCASE';
        $this->assertFalse($form->validate(['name']));

        $form->name = 'with spaces';
        $this->assertFalse($form->validate(['name']));

        $form->name = 'special!chars';
        $this->assertFalse($form->validate(['name']));
    }

    public function testValidationTitleMaxLength()
    {
        $this->becomeUser('Admin');
        $form = SessionForm::create();

        $form->title = str_repeat('a', 200);
        $this->assertTrue($form->validate(['title']));

        $form->title = str_repeat('a', 201);
        $this->assertFalse($form->validate(['title']));
    }

    // ========== Save Tests ==========

    public function testSaveCreatesNewSession()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);

        $form = SessionForm::create($space);
        $form->name = 'new-session';
        $form->title = 'New Session';
        $form->backend_type = 'bbb';

        $result = $form->save();
        $this->assertTrue($result, 'Save failed: ' . print_r($form->getErrors(), true));
        $this->assertNotNull($form->id);

        // Verify record exists in DB
        $session = Session::findOne($form->id);
        $this->assertNotNull($session);
        $this->assertEquals('new-session', $session->name);
        $this->assertEquals('New Session', $session->title);
        $this->assertEquals('bbb', $session->backend_type);
        $this->assertNotEmpty($session->uuid);
    }

    public function testSaveAllFieldsPersistCorrectly()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);

        $form = SessionForm::create($space);
        $form->name = 'full-persist';
        $form->title = 'Full Persistence Test';
        $form->description = 'A detailed description for the session.';
        $form->backend_type = 'bbb';
        $form->publicJoin = true;
        $form->joinCanStart = true;
        $form->joinCanModerate = true;
        $form->hasWaitingRoom = true;
        $form->allowRecording = false;
        $form->muteOnEntry = true;
        $form->enabled = false;
        $form->backendConfig = [
            'layout' => 'PRESENTATION_FOCUS',
            'maxParticipants' => 50,
            'webcamsOnlyForModerator' => true,
        ];

        $result = $form->save();
        $this->assertTrue($result, 'Save failed: ' . print_r($form->getErrors(), true));

        // Re-fetch from DB to ensure persistence (not just in-memory)
        $session = Session::findOne($form->id);
        $this->assertNotNull($session);

        // Core fields
        $this->assertEquals('full-persist', $session->name);
        $this->assertEquals('Full Persistence Test', $session->title);
        $this->assertEquals('A detailed description for the session.', $session->description);
        $this->assertEquals('bbb', $session->backend_type);

        // Permission toggles → DB columns
        $this->assertTrue((bool) $session->public_join, 'public_join not persisted');
        $this->assertTrue((bool) $session->join_can_start, 'join_can_start not persisted');
        $this->assertTrue((bool) $session->join_can_moderate, 'join_can_moderate not persisted');
        $this->assertTrue((bool) $session->has_waitingroom, 'has_waitingroom not persisted');
        $this->assertFalse((bool) $session->allow_recording, 'allow_recording not persisted');
        $this->assertTrue((bool) $session->mute_on_entry, 'mute_on_entry not persisted');
        $this->assertFalse((bool) $session->enabled, 'enabled not persisted');

        // Public token should be generated when publicJoin=true
        $this->assertNotEmpty($session->public_token, 'public_token not generated');

        // Backend config (JSON)
        $this->assertEquals('PRESENTATION_FOCUS', $session->getBackendConfigValue('layout'));
        $this->assertEquals(50, $session->getBackendConfigValue('maxParticipants'));
        $this->assertTrue($session->getBackendConfigValue('webcamsOnlyForModerator'));

        // Timestamps should be integers
        $this->assertIsInt($session->created_at);
        $this->assertIsInt($session->updated_at);
        $this->assertGreaterThan(0, $session->created_at);
    }

    public function testSaveUpdatesExistingSession()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('update-me', 'Update Me', $space);

        $form = SessionForm::edit($session);
        $form->title = 'Updated Title';
        $form->hasWaitingRoom = true;

        $result = $form->save();
        $this->assertTrue($result);

        $session->refresh();
        $this->assertEquals('Updated Title', $session->title);
        $this->assertTrue((bool)$session->has_waitingroom);
    }

    public function testSaveWithBackendConfig()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);

        $form = SessionForm::create($space);
        $form->name = 'config-session';
        $form->title = 'Config Session';
        $form->backend_type = 'bbb';
        $form->backendConfig = [
            'layout' => 'PRESENTATION_FOCUS',
            'maxParticipants' => 25,
        ];

        $result = $form->save();
        $this->assertTrue($result);

        $session = Session::findOne($form->id);
        $this->assertEquals('PRESENTATION_FOCUS', $session->getBackendConfigValue('layout'));
        $this->assertEquals(25, $session->getBackendConfigValue('maxParticipants'));
    }

    // ========== User Assignment Tests ==========

    public function testSaveWithManualModerators()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);

        $user1 = User::findOne(['username' => 'User1']);

        $form = SessionForm::create($space);
        $form->name = 'manual-mod';
        $form->title = 'Manual Moderator';
        $form->backend_type = 'bbb';
        $form->moderateByPermissions = false;
        $form->moderatorRefs = [$user1->guid];

        $result = $form->save();
        $this->assertTrue($result);

        $moderators = SessionUser::findAll([
            'session_id' => $form->id,
            'role' => 'moderator',
        ]);
        $this->assertCount(1, $moderators);
        $this->assertEquals($user1->id, $moderators[0]->user_id);
        $this->assertTrue((bool)$moderators[0]->can_start);
    }

    public function testSaveWithManualAttendees()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);

        $user1 = User::findOne(['username' => 'User1']);
        $user2 = User::findOne(['username' => 'User2']);

        $form = SessionForm::create($space);
        $form->name = 'manual-att';
        $form->title = 'Manual Attendees';
        $form->backend_type = 'bbb';
        $form->joinByPermissions = false;
        $form->attendeeRefs = [$user1->guid, $user2->guid];

        $result = $form->save();
        $this->assertTrue($result);

        $attendees = SessionUser::findAll([
            'session_id' => $form->id,
            'role' => 'attendee',
        ]);
        $this->assertCount(2, $attendees);
    }

    public function testSavePermissionBasedClearsAssignments()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('clear-test', 'Clear Test', $space);

        $user1 = User::findOne(['username' => 'User1']);
        $this->assignUser($session, $user1, 'attendee');

        // Now switch to permission-based (should clear assignments)
        $form = SessionForm::edit($session);
        $form->joinByPermissions = true;
        $form->attendeeRefs = [];

        $result = $form->save();
        $this->assertTrue($result);

        $attendees = SessionUser::findAll([
            'session_id' => $session->id,
            'role' => 'attendee',
        ]);
        $this->assertCount(0, $attendees);
    }

    public function testSaveExcludesModeratorsFromAttendees()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);

        $user1 = User::findOne(['username' => 'User1']);

        $form = SessionForm::create($space);
        $form->name = 'overlap-test';
        $form->title = 'Overlap Test';
        $form->backend_type = 'bbb';
        $form->joinByPermissions = false;
        $form->moderateByPermissions = false;
        // Same user in both lists
        $form->moderatorRefs = [$user1->guid];
        $form->attendeeRefs = [$user1->guid];

        $result = $form->save();
        $this->assertTrue($result);

        // User should only be moderator, not also attendee
        $modRecords = SessionUser::findAll([
            'session_id' => $form->id,
            'role' => 'moderator',
            'user_id' => $user1->id,
        ]);
        $attRecords = SessionUser::findAll([
            'session_id' => $form->id,
            'role' => 'attendee',
            'user_id' => $user1->id,
        ]);
        $this->assertCount(1, $modRecords);
        $this->assertCount(0, $attRecords);
    }

    public function testSaveDeleteFirstPattern()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('delete-first', 'Delete First', $space);

        $user1 = User::findOne(['username' => 'User1']);
        $user2 = User::findOne(['username' => 'User2']);

        // First save: assign User1 as attendee
        $this->assignUser($session, $user1, 'attendee');

        // Edit: change to User2 only
        $form = SessionForm::edit($session);
        $form->joinByPermissions = false;
        $form->attendeeRefs = [$user2->guid];
        $form->save();

        $attendees = SessionUser::findAll([
            'session_id' => $session->id,
            'role' => 'attendee',
        ]);
        $this->assertCount(1, $attendees);
        $this->assertEquals($user2->id, $attendees[0]->user_id);
    }

    // ========== Visibility / Hidden Tests ==========

    public function testSaveWithVisibility()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);

        $form = SessionForm::create($space);
        $form->name = 'vis-test';
        $form->title = 'Visibility Test';
        $form->backend_type = 'bbb';
        $form->visibility = 1;  // Public
        $form->hidden = 1;

        $result = $form->save();
        $this->assertTrue($result);

        $session = Session::findOne($form->id);
        $this->assertEquals(1, $session->content->visibility);
        $this->assertEquals(1, $session->content->hidden);
    }

    // ========== Public Join Tests ==========

    public function testSavePublicJoinGeneratesToken()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);

        $form = SessionForm::create($space);
        $form->name = 'public-join';
        $form->title = 'Public Join';
        $form->backend_type = 'bbb';
        $form->publicJoin = true;

        $result = $form->save();
        $this->assertTrue($result);

        $session = Session::findOne($form->id);
        $this->assertTrue((bool)$session->public_join);
        $this->assertNotEmpty($session->public_token);
    }

    // ========== Attribute Labels Tests ==========

    public function testAttributeLabels()
    {
        $form = new SessionForm();
        $labels = $form->attributeLabels();
        $this->assertArrayHasKey('name', $labels);
        $this->assertArrayHasKey('title', $labels);
        $this->assertArrayHasKey('backend_type', $labels);
        $this->assertArrayHasKey('joinByPermissions', $labels);
        $this->assertArrayHasKey('moderateByPermissions', $labels);
    }

    // ========== getRecord Tests ==========

    public function testGetRecordNullForNewForm()
    {
        $this->becomeUser('Admin');
        $form = SessionForm::create();
        $this->assertNull($form->getRecord());
    }

    public function testGetRecordReturnsSessionAfterSave()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);

        $form = SessionForm::create($space);
        $form->name = 'record-test';
        $form->title = 'Record Test';
        $form->backend_type = 'bbb';
        $form->save();

        $this->assertNotNull($form->getRecord());
        $this->assertInstanceOf(Session::class, $form->getRecord());
    }

    public function testGetRecordReturnsSessionForEdit()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('get-rec', 'Get Record', $space);

        $form = SessionForm::edit($session);
        $this->assertNotNull($form->getRecord());
        $this->assertEquals($session->id, $form->getRecord()->id);
    }
}
