<?php

declare(strict_types=1);

return [

    'routes' => [
        'prefix' => 'mailbox',
        'name' => 'mailbox.',
        'middleware' => ['web', 'auth'],
    ],

    'pages' => [
        'compose' => 'mailbox/Compose',
        'contacts_edit' => 'mailbox/Contacts/Edit',
        'contacts_index' => 'mailbox/Contacts/Index',
        'empty' => 'mailbox/Empty',
        'index' => 'mailbox/Index',
        'settings_edit' => 'mailbox/Settings/Edit',
        'settings_index' => 'mailbox/Settings/Index',
        'show' => 'mailbox/Show',
    ],

    'per_page' => 50,

    'poll_seconds' => 30,

    'signed_url_minutes' => 30,

];
