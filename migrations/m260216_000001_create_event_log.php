<?php

use yii\db\Migration;

/**
 * Creates the sessions_event_log table for tracking session start/stop/join/leave events.
 */
class m260216_000001_create_event_log extends Migration
{
    public function safeUp()
    {
        $this->createTable('sessions_event_log', [
            'id' => $this->primaryKey(),
            'session_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->null(),
            'event_type' => $this->string(20)->notNull()->comment('started, stopped, joined, left'),
            'created_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx_sel_session', 'sessions_event_log', 'session_id');
        $this->createIndex('idx_sel_user', 'sessions_event_log', 'user_id');
        $this->createIndex('idx_sel_event_type', 'sessions_event_log', 'event_type');
        $this->createIndex('idx_sel_session_event', 'sessions_event_log', ['session_id', 'event_type']);

        $this->addForeignKey(
            'fk_sel_session',
            'sessions_event_log',
            'session_id',
            'sessions_session',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_sel_user',
            'sessions_event_log',
            'user_id',
            'user',
            'id',
            'SET NULL',
            'CASCADE'
        );

        return true;
    }

    public function safeDown()
    {
        $this->dropTable('sessions_event_log');
        return true;
    }
}
