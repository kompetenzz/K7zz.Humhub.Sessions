<?php

return [
    'humhub_root' => '/opt/humhub',
    'modules' => ['sessions'],
    'fixtures' => [
        'default',
        'session' => 'humhub\modules\sessions\tests\codeception\fixtures\SessionFixture',
        'session_user' => 'humhub\modules\sessions\tests\codeception\fixtures\SessionUserFixture',
    ],
];
