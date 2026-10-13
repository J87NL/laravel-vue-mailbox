<?php

declare(strict_types=1);

return [

    'routes' => [
        'prefix' => 'mailbox',
        'name' => 'mailbox.',
        'middleware' => ['web', 'auth'],
    ],

    'pages' => [
        'compose' => 'Mailbox/Compose',
        'index' => 'Mailbox/Index',
        'settings_edit' => 'Mailbox/Settings/Edit',
        'settings_index' => 'Mailbox/Settings/Index',
        'show' => 'Mailbox/Show',
    ],

    'per_page' => 50,

    'signed_url_minutes' => 30,

];
