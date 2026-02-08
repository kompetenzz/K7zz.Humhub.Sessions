<?php

use yii\db\Migration;

/**
 * Uninstall migration for the Sessions module.
 * Removes all database tables created by the module.
 */
class uninstall extends Migration
{
    public function up()
    {
        $this->dropTable('sessions_session_user');
        $this->dropTable('sessions_session');
    }

    public function down()
    {
        echo "uninstall migration cannot be reverted.\n";
        return false;
    }
}
