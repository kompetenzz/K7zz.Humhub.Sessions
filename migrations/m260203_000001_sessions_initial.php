<?php

use yii\db\Migration;

/**
 * Initial migration for the Sessions module.
 * Creates the main session table with multi-backend support and the session_user pivot table.
 */
class m260203_000001_sessions_initial extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Main sessions table with multi-backend support
        $this->createTable('sessions_session', [
            'id' => $this->primaryKey(),

            // Core identifiers
            'uuid' => $this->string(36)->notNull()->unique()->comment('Unique meeting identifier'),
            'backend_type' => $this->string(20)->notNull()->comment('Backend identifier: bbb, jitsi, opentalk, zoom'),
            'backend_meeting_id' => $this->string(255)->null()->comment('Backend-specific meeting ID'),
            'name' => $this->string(100)->notNull()->comment('URL slug'),
            'title' => $this->string(200)->null()->comment('Session title'),
            'description' => $this->text()->null()->comment('Session description'),

            // Authentication
            'moderator_pw' => $this->string(255)->notNull()->comment('Moderator password'),
            'attendee_pw' => $this->string(255)->notNull()->comment('Attendee password'),

            // Relationships
            'contentcontainer_id' => $this->integer()->null()->comment('Space/User container'),
            'creator_user_id' => $this->integer()->notNull()->comment('Creator user ID'),

            // Timestamps
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'deleted_at' => $this->integer()->null(),

            // Status & ordering
            'enabled' => $this->boolean()->notNull()->defaultValue(true)->comment('Is session enabled'),
            'ord' => $this->integer()->notNull()->defaultValue(0)->comment('Display order'),

            // Permissions & access
            'public_join' => $this->boolean()->notNull()->defaultValue(false)->comment('Allow public/anonymous join'),
            'public_token' => $this->string(64)->null()->comment('Token for public join'),
            'join_can_start' => $this->boolean()->notNull()->defaultValue(false)->comment('Join users can start meeting'),
            'join_can_moderate' => $this->boolean()->notNull()->defaultValue(false)->comment('Join users are moderators'),

            // Meeting features
            'has_waitingroom' => $this->boolean()->notNull()->defaultValue(false)->comment('Enable waiting room'),
            'allow_recording' => $this->boolean()->notNull()->defaultValue(false)->comment('Allow recording'),
            'mute_on_entry' => $this->boolean()->notNull()->defaultValue(false)->comment('Mute participants on entry'),

            // File attachments
            'image_file_id' => $this->integer()->null()->comment('Session thumbnail image'),
            'camera_bg_image_file_id' => $this->integer()->null()->comment('Camera background image'),
            'presentation_file_id' => $this->integer()->null()->comment('Presentation file (PDF)'),
            'presentation_preview_file_id' => $this->integer()->null()->comment('Presentation preview image'),

            // Backend-specific configuration (JSON)
            'backend_config' => $this->text()->null()->comment('JSON config for backend-specific settings'),
        ]);

        // Indexes
        $this->createIndex('idx_sessions_session_backend', 'sessions_session', 'backend_type');
        $this->createIndex('idx_sessions_session_uuid', 'sessions_session', 'uuid');
        $this->createIndex('idx_sessions_session_container', 'sessions_session', 'contentcontainer_id');
        $this->createIndex('idx_sessions_session_creator', 'sessions_session', 'creator_user_id');
        $this->createIndex('idx_sessions_session_enabled', 'sessions_session', 'enabled');
        $this->createIndex('idx_sessions_session_public_token', 'sessions_session', 'public_token');

        // Foreign keys
        $this->addForeignKey(
            'fk_sessions_session_container',
            'sessions_session',
            'contentcontainer_id',
            'contentcontainer',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_sessions_session_creator',
            'sessions_session',
            'creator_user_id',
            'user',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // Pivot table for user-specific permissions
        $this->createTable('sessions_session_user', [
            'id' => $this->primaryKey(),
            'session_id' => $this->integer()->notNull()->comment('Session ID'),
            'user_id' => $this->integer()->notNull()->comment('User ID'),
            'role' => "ENUM('moderator','attendee') NOT NULL DEFAULT 'attendee' COMMENT 'User role in session'",
            'can_start' => $this->boolean()->notNull()->defaultValue(false)->comment('Can start meeting'),
            'can_join' => $this->boolean()->notNull()->defaultValue(true)->comment('Can join meeting'),
            'created_at' => $this->integer()->notNull(),
        ]);

        // Indexes for session_user
        $this->createIndex(
            'idx_sessions_su_session',
            'sessions_session_user',
            'session_id'
        );

        $this->createIndex(
            'idx_sessions_su_user',
            'sessions_session_user',
            'user_id'
        );

        $this->createIndex(
            'idx_sessions_su_unique',
            'sessions_session_user',
            ['session_id', 'user_id'],
            true
        );

        // Foreign keys for session_user
        $this->addForeignKey(
            'fk_sessions_su_session',
            'sessions_session_user',
            'session_id',
            'sessions_session',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_sessions_su_user',
            'sessions_session_user',
            'user_id',
            'user',
            'id',
            'CASCADE',
            'CASCADE'
        );

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('sessions_session_user');
        $this->dropTable('sessions_session');

        return true;
    }
}
