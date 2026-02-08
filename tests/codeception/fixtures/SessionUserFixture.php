<?php

namespace humhub\modules\sessions\tests\codeception\fixtures;

use yii\test\ActiveFixture;

class SessionUserFixture extends ActiveFixture
{
    public $modelClass = 'humhub\modules\sessions\models\SessionUser';
    public $dataFile = '@sessions/tests/codeception/fixtures/data/sessionUser.php';
}
