<?php

namespace humhub\modules\sessions\tests\codeception\unit\models;

use humhub\modules\sessions\models\Session;
use humhub\modules\sessions\models\SessionUser;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use sessions\SessionsUnitTest;

class SessionUserTest extends SessionsUnitTest
{
    // ========== Validation Tests ==========

    public function testRequiredFields()
    {
        $su = new SessionUser();
        $this->assertFalse($su->validate());
        $this->assertNotEmpty($su->getErrors('session_id'));
        $this->assertNotEmpty($su->getErrors('user_id'));
    }

    public function testRoleValidation()
    {
        $su = new SessionUser();
        $su->session_id = 1;
        $su->user_id = 1;

        $su->role = 'moderator';
        $this->assertTrue($su->validate(['role']));

        $su->role = 'attendee';
        $this->assertTrue($su->validate(['role']));

        $su->role = 'invalid';
        $this->assertFalse($su->validate(['role']));
    }

    public function testDefaultValues()
    {
        $su = new SessionUser();
        $su->session_id = 1;
        $su->user_id = 1;
        $su->validate();

        $this->assertEquals('attendee', $su->role);
        $this->assertFalse((bool) $su->can_start);
        $this->assertTrue((bool) $su->can_join);
    }

    // ========== Relation Tests ==========

    public function testSessionRelation()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('su-rel', 'SU Relation', $space);

        $user = User::findOne(['username' => 'User1']);
        $su = $this->assignUser($session, $user, 'attendee');

        $relatedSession = $su->session;
        $this->assertNotNull($relatedSession);
        $this->assertEquals($session->id, $relatedSession->id);
    }

    public function testUserRelation()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('su-user-rel', 'SU User Relation', $space);

        $user = User::findOne(['username' => 'User1']);
        $su = $this->assignUser($session, $user, 'attendee');

        $relatedUser = $su->user;
        $this->assertNotNull($relatedUser);
        $this->assertEquals($user->id, $relatedUser->id);
    }

    // ========== CRUD Tests ==========

    public function testCreateModeratorAssignment()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('mod-crud', 'Mod CRUD', $space);

        $user = User::findOne(['username' => 'User1']);
        $su = $this->assignUser($session, $user, 'moderator');

        $this->assertNotNull($su->id);
        $this->assertEquals('moderator', $su->role);
        $this->assertTrue((bool) $su->can_start);
        $this->assertTrue((bool) $su->can_join);
    }

    public function testCreateAttendeeAssignment()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('att-crud', 'Att CRUD', $space);

        $user = User::findOne(['username' => 'User1']);
        $su = $this->assignUser($session, $user, 'attendee');

        $this->assertNotNull($su->id);
        $this->assertEquals('attendee', $su->role);
        $this->assertFalse((bool) $su->can_start);
        $this->assertTrue((bool) $su->can_join);
    }

    public function testDeleteAllBySessionAndRole()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('del-test', 'Delete Test', $space);

        $user1 = User::findOne(['username' => 'User1']);
        $user2 = User::findOne(['username' => 'User2']);
        $user3 = User::findOne(['username' => 'User3']);

        $this->assignUser($session, $user1, 'attendee');
        $this->assignUser($session, $user2, 'attendee');
        // Use a different user for moderator (unique constraint on session_id + user_id)
        $this->assignUser($session, $user3, 'moderator');

        // Delete all attendees
        SessionUser::deleteAll(['session_id' => $session->id, 'role' => 'attendee']);

        $attendees = SessionUser::findAll(['session_id' => $session->id, 'role' => 'attendee']);
        $moderators = SessionUser::findAll(['session_id' => $session->id, 'role' => 'moderator']);

        $this->assertCount(0, $attendees);
        $this->assertCount(1, $moderators);
    }

    public function testCustomPermissions()
    {
        $this->becomeUser('Admin');
        $space = Space::findOne(['id' => 1]);
        $session = $this->createSession('custom-perm', 'Custom Perms', $space);

        $user = User::findOne(['username' => 'User1']);
        $su = $this->assignUser($session, $user, 'attendee', [
            'can_start' => true,
            'can_join' => false,
        ]);

        $this->assertTrue((bool) $su->can_start);
        $this->assertFalse((bool) $su->can_join);
    }
}
