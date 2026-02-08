<?php

namespace humhub\modules\sessions\tests\codeception\fixtures;

use yii\test\ActiveFixture;

class SessionFixture extends ActiveFixture
{
    public $modelClass = 'humhub\modules\sessions\models\Session';
    public $dataFile = '@sessions/tests/codeception/fixtures/data/session.php';

    public $depends = [
        SessionUserFixture::class,
    ];
}
